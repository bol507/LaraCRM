<?php
// app/Application/UseCases/Calendar/CreateActivityUseCase.php

namespace App\Application\UseCases\Calendar;

use App\Application\DTOs\Calendar\CreateActivityRequest;
use App\Application\Repositories\ActivityRepositoryInterface;
use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Domain\Entities\Activity;
use App\Infrastructure\Mappers\ActivityMapper;
use App\Infrastructure\Repositories\Core\SeActivityRelRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CreateActivityUseCase
{
    
    private const MAX_CREATE_RETRIES = 3;
    private const RETRY_DELAY_MICROSECONDS = 100000;
    private const ENTITY_SETYPE = 'Calendar';

    public function __construct(
        private readonly ActivityRepositoryInterface $activityRepository,
        private readonly CreateEntityUseCase $createEntity,
        private readonly SeActivityRelRepository $relations,
    ) {}

    public function execute(CreateActivityRequest $request, ?int $createdByUserId = null): int
    {
        $userId = $createdByUserId ?? CurrentUserService::idOr(1);

        $this->validateRequest($request);

        $attempt = 0;
        while ($attempt < self::MAX_CREATE_RETRIES) {
            try {
                return DB::connection('vtiger')->transaction(function () use ($request, $userId) {
                    // 1. Create vtiger_crmentity via generic UseCase → returns crmid
                    $activityId = $this->createEntity->execute(
                        data: [
                            'label' => trim($request->subject),
                            'description' => $request->description ?? '',
                            'smownerid' => $request->assignedUserId ?? $userId,
                            'smcreatorid' => $userId,
                        ],
                        setype: self::ENTITY_SETYPE,  // 'Calendar'
                        userId: $userId
                    );
                    
                    // 2. Build Activity domain entity with the generated ID
                    $activity = $this->buildActivityEntity($activityId, $request, $userId);
                    
                    // 3. Map Entity → DB array via Mapper
                    $activityData = ActivityMapper::toDatabaseRow($activity);
                    
                    // 4. Insert vtiger_activity via Repository
                    $this->activityRepository->insert($activityData);

                    // 5. Create relationship if linked to another CRM entity
                    if ($request->relatedRecordId && $request->relatedModuleType) {
                        $this->relations->link(
                            activityId: $activityId,
                            crmid: $request->relatedRecordId,
                            setype: $request->relatedModuleType
                        );
                    }

                    // 6. Log activity for audit (non-blocking)
                    $this->logActivity($activityId, $userId);

                    return $activityId;
                });
            } catch (QueryException $e) {
                $attempt++;
                if ($attempt < self::MAX_CREATE_RETRIES && $this->isConstraintError($e)) {
                    usleep(self::RETRY_DELAY_MICROSECONDS);
                    continue;
                }
                throw new RuntimeException(
                    sprintf('Failed to create task after %d attempt(s): %s', $attempt, $e->getMessage()),
                    previous: $e
                );
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to create task: ' . $e->getMessage(),
                    previous: $e
                );
            }
        }

        throw new RuntimeException(
            sprintf('Failed to create task after %d retry attempts', self::MAX_CREATE_RETRIES)
        );
    }

    // ==================== VALIDATION ====================

    private function validateRequest(CreateActivityRequest $request): void
    {
        if (empty(trim($request->subject))) {
            throw new InvalidArgumentException('Activity subject is required');
        }
        if (strlen($request->subject) > 255) {
            throw new InvalidArgumentException('Activity subject cannot exceed 255 characters');
        }
        if (empty($request->dateStart)) {
            throw new InvalidArgumentException('Activity start date is required');
        }
        if ($request->dueDate && $request->dueDate < $request->dateStart) {
            throw new InvalidArgumentException('Due date cannot be before start date');
        }
        if ($request->timeStart && $request->timeEnd && $request->timeEnd <= $request->timeStart) {
            throw new InvalidArgumentException('End time must be after start time');
        }
        if ($request->location && strlen($request->location) > 150) {
            throw new InvalidArgumentException('Location cannot exceed 150 characters');
        }
        // Enum/value object validations if you use them:
        // if ($request->status && !TaskStatus::isValid($request->status)) { ... }
        // if ($request->priority && !TaskPriority::isValid($request->priority)) { ... }
    }

    // ==================== ENTITY BUILDER ====================
    private function buildActivityEntity(int $activityId, CreateActivityRequest $request, int $userId): Activity
    {
        $now = new DateTimeImmutable();
        
        return new Activity(
            id: $activityId,
            subject: trim($request->subject),
            activityType: $request->activityType ?? 'Task',
            status: $request->status ?? 'Not Started',
            priority: $request->priority ?? 'Medium',
            dateStart: new DateTimeImmutable($request->dateStart),
            dateDue: $request->dueDate ? new DateTimeImmutable($request->dueDate) : null,
            timeStart: $request->timeStart,
            timeEnd: $request->timeEnd,
            sendNotification: $request->sendNotification ?? '0',
            location: $request->location,
            visibility: $request->visibility ?? 'all',
            assignedUserId: $request->assignedUserId ?? $userId,
            assignedUserName: null,  // Calculated when reading, not when writing
            assignedUserEmail: null,
            createdAt: $now,
            updatedAt: $now,
            relatedRecordId: $request->relatedRecordId,
            relatedModuleType: $request->relatedModuleType,
            durationHours: $request->durationHours ?? 0,
            durationMinutes: $request->durationMinutes ?? 0,
        );
    }

    private function logActivity(int $taskId, int $userId): void
    {
        try {
            VtigerActivityTracker::created(
                module: 'Calendar',
                crmid: $taskId,
                userId: $userId
            );
        } catch (\Exception $e) {
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