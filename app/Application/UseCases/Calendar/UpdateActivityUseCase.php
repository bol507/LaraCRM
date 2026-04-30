<?php
// app/Application/UseCases/Calendar/UpdateActivityUseCase.php

namespace App\Application\UseCases\Calendar;

use App\Application\DTOs\Calendar\ActivityDto;
use App\Application\DTOs\Calendar\UpdateActivityRequest;
use App\Application\Repositories\ActivityRepositoryInterface;
use App\Application\UseCases\User\CanAssignToUserUseCase;
use App\Application\UseCases\User\IsAdminUseCase;
use App\Domain\Entities\Activity;
use App\Infrastructure\Mappers\ActivityMapper;
use App\Infrastructure\Repositories\Core\CrmentityRepository;
use App\Infrastructure\Repositories\Core\SeActivityRelRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use DomainException;

class UpdateActivityUseCase
{
    private const MAX_UPDATE_RETRIES = 3;
    private const RETRY_DELAY_MICROSECONDS = 100000;
    private const ENTITY_SETYPE = 'Calendar';

    public function __construct(
        private readonly ActivityRepositoryInterface $activityRepository,
        private readonly CrmentityRepository $crmentityRepository,
        private readonly SeActivityRelRepository $relations,
        private readonly IsAdminUseCase $isAdminUseCase,
        private readonly CanAssignToUserUseCase $canAssignToUser,
    ) {}

    /**
     * Execute the update activity use case
     * 
     * @param int $activityId The activity to update
     * @param UpdateActivityRequest $request Validated update data
     * @param int|null $assignedUserId User requesting the update
     * @return bool True if update was successful
     * 
     * @throws InvalidArgumentException If parameters are invalid
     * @throws DomainException If user is not authorized or business rules violated
     * @throws RuntimeException If persistence fails
     */
    public function execute(
        int $activityId,
        UpdateActivityRequest $request,
        ?int $assignedUserId = null
    ): bool {
        $userId = CurrentUserService::idOr(1);

        $this->validateRequest($request);

        $attempt = 0;
        while ($attempt < self::MAX_UPDATE_RETRIES) {
            try {
                return DB::connection('vtiger')->transaction(function () use ($activityId, $request, $userId) {

                    // 1. Fetch existing activity to validate permissions and current state
                    $existing = $this->activityRepository->findById($activityId);
                    if (!$existing) {
                        throw new InvalidArgumentException("Activity {$activityId} not found");
                    }
                    $existing = ActivityDto::fromArray($existing);

                    // 2. Validate user has permission to update (owner or admin)
                    $this->verifyUpdatePermission($existing, $userId, $request->assignedUserId);

                    // 3. Validate business rules against current state
                    $this->validateBusinessRules($existing, $request);

                    $now = new DateTimeImmutable();

                    // 4. Update vtiger_crmentity metadata
                    $crmentityUpdates = array_filter([
                        'label' => $request->subject !== null ? trim($request->subject) : null,
                        'description' => $request->description,
                        'smownerid' => $request->assignedUserId,
                        'modifiedby' => $userId,
                        'modifiedtime' => $now,
                    ], fn($v) => $v !== null);  // Only include fields that were provided

                    if (!empty($crmentityUpdates)) {
                        $this->crmentityRepository->update($activityId, $crmentityUpdates);
                    }

                    $activityUpdates = array_filter([
                        'subject' => $request->subject,  // subject also goes in activity (for searching)
                        'status' => $request->status,
                        'priority' => $request->priority,
                        'date_start' => $request->dateStart,
                        'due_date' => $request->dueDate,
                        'time_start' => $request->timeStart,
                        'time_end' => $request->timeEnd,
                        'location' => $request->location,
                        'visibility' => $request->visibility,
                        'sendnotification' => $request->sendNotification,
                        'notime' => $request->timeStart === null ? '1' : '0',
                        'duration_hours' => $request->durationHours,
                        'duration_minutes' => $request->durationMinutes,
                        'semodule' => $request->relatedModuleType,  // If related module changes
                    ], fn($v) => $v !== null);

                    if (!empty($activityUpdates)) {
                        $this->activityRepository->update($activityId, $activityUpdates);
                    }

                    // Handle relationship changes if related record changed
                    $this->handleRelationshipChanges($activityId, $existing, $request);

                    // Log activity for audit (non-blocking)
                    $this->logActivity($activityId, $userId, 'updated');

                    return true;
                });
            } catch (QueryException $e) {
                $attempt++;
                if ($attempt < self::MAX_UPDATE_RETRIES && $this->isConstraintError($e)) {
                    usleep(self::RETRY_DELAY_MICROSECONDS);
                    continue;
                }
                throw new RuntimeException(
                    sprintf('Failed to update activity after %d attempt(s): %s', $attempt, $e->getMessage()),
                    previous: $e
                );
            } catch (DomainException | InvalidArgumentException $e) {
                // Business rule violations should not retry
                throw $e;
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to update activity: ' . $e->getMessage(),
                    previous: $e
                );
            }
        }

        throw new RuntimeException(
            sprintf('Failed to update activity after %d retry attempts', self::MAX_UPDATE_RETRIES)
        );
    }

    // ==================== VALIDATION ====================

    private function validateRequest(UpdateActivityRequest $request): void
    {
        if ($request->subject !== null && empty(trim($request->subject))) {
            throw new InvalidArgumentException('Activity subject cannot be empty');
        }
        if ($request->subject !== null && strlen($request->subject) > 255) {
            throw new InvalidArgumentException('Activity subject cannot exceed 255 characters');
        }
        if ($request->dateStart !== null && empty($request->dateStart)) {
            throw new InvalidArgumentException('Activity start date is required');
        }
        if ($request->dueDate !== null && $request->dateStart !== null && $request->dueDate < $request->dateStart) {
            throw new InvalidArgumentException('Due date cannot be before start date');
        }
        if ($request->timeStart !== null && $request->timeEnd !== null && $request->timeEnd <= $request->timeStart) {
            throw new InvalidArgumentException('End time must be after start time');
        }
        if ($request->location !== null && strlen($request->location) > 150) {
            throw new InvalidArgumentException('Location cannot exceed 150 characters');
        }
    }

    private function verifyUpdatePermission(
        ActivityDto $activity,
        int $userId,
        ?int $newAssignedUserId
    ): void {
        // ==================== 1. Can they EDIT this activity? ====================
        $canEdit =
            $activity->assignedUserId === $userId ||  // Owner
            $this->isAdminUseCase->execute($userId) ||  // Admin
            $this->canAssignToUser->execute($userId, $activity->assignedUserId);  // Supervisor of current assignee

        if (!$canEdit) {
            throw new DomainException(
                "User {$userId} is not authorized to update activity {$activity->id}. " .
                    "Only the assigned user, their supervisor, or an administrator can modify this activity."
            );
        }

        // ==================== 2. Can they REASSIGN to the new user? ====================
        // Only validate if assignedUserId is actually changing
        if ($newAssignedUserId !== null && $newAssignedUserId !== $activity->assignedUserId) {
            $canAssignToNew =
                $this->isAdminUseCase->execute($userId) ||  // Admin can assign to anyone
                $this->canAssignToUser->execute($userId, $newAssignedUserId);  // Supervisor of NEW assignee

            if (!$canAssignToNew) {
                throw new DomainException(
                    "You do not have permission to assign tasks to this user (ID: {$newAssignedUserId}). " .
                        "You can only assign to yourself or your direct subordinates."
                );
            }
        }

        // Both validations passed
        return;
    }

    private function validateBusinessRules(ActivityDto $existing, UpdateActivityRequest $request): void
    {
        // Status transition rules (optional, customize per your workflow)
        if ($request->status !== null) {
            $validTransitions = [
                'Not Started' => ['In Progress', 'Completed', 'Deferred', 'Pending Input'], 
                'In Progress' => ['Completed', 'Deferred', 'Not Started', 'Pending Input'],  
                'Pending Input' => ['In Progress', 'Completed', 'Not Started'], 
                'Completed' => ['Not Started'],
                'Deferred' => ['In Progress', 'Not Started', 'Pending Input'],
            ];

            $currentStatus = $existing->status;
            $newStatus = $request->status;

            if ($currentStatus !== $newStatus) {
                $allowed = $validTransitions[$currentStatus] ?? [];
                if (!empty($allowed) && !in_array($newStatus, $allowed, true)) {
                    throw new DomainException(
                        "Cannot transition status from '{$currentStatus}' to '{$newStatus}'. " .
                            "Allowed transitions: " . implode(', ', $allowed)
                    );
                }
            }
        }

        // Cannot update completed activity's dates (optional rule)
        if ($existing->completed && ($request->dateStart !== null || $request->dueDate !== null)) {
            throw new DomainException('Cannot modify dates of a completed activity');
        }
    }

    // ==================== RELATIONSHIP HANDLING ====================

    private function handleRelationshipChanges(
        int $activityId,
        ActivityDto $existing,
        UpdateActivityRequest $request
    ): void {
        // If related record changed, update the relationship
        $existingRelId = $existing->relatedRecordId;
        $newRelId = $request->relatedRecordId;
        $existingRelType = $existing->relatedModuleType;
        $newRelType = $request->relatedModuleType;

        // Remove old relationship if changed
        if ($existingRelId !== $newRelId || $existingRelType !== $newRelType) {
            if ($existingRelId && $existingRelType) {
                $this->relations->unlink($activityId, $existingRelId);
            }
            // Add new relationship if provided
            if ($newRelId && $newRelType) {
                $this->relations->link(
                    activityId: $activityId,
                    crmid: $newRelId,
                    setype: $newRelType
                );
            }
        }
    }

    // ==================== UTILITIES ====================

    private function logActivity(int $activityId, int $userId, string $action): void
    {
        try {
            $trackerMethod = $action === 'updated'
                ? [VtigerActivityTracker::class, 'updated']
                : [VtigerActivityTracker::class, 'created'];

            $trackerMethod(
                module: 'Calendar',
                crmid: $activityId,
                userId: $userId
            );
        } catch (\Exception) {
            // Silently fail - activity logging is non-critical
        }
    }

    private function isConstraintError(QueryException $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'Duplicate entry')
            || str_contains($message, '1062')
            || str_contains($message, 'foreign key constraint fails')
            || str_contains($message, '1452');
    }
}
