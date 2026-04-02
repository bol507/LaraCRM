<?php

namespace App\Application\UseCases\Task;

use App\Application\DTOs\Task\CreateTaskRequest;
use App\Application\UseCases\Core\Activity\CreateTaskActivityUseCase;
use App\Application\UseCases\Core\Entity\CreateEntityUseCase;
use App\Application\ValueObjects\Task\TaskPriority;
use App\Application\ValueObjects\Task\TaskStatus;
use App\Domain\Entities\Task;
use App\Infrastructure\Mappers\TaskMapper;
use App\Infrastructure\Repositories\Core\IdGeneratorRepository;
use App\Infrastructure\Repositories\Core\SeActivityRelRepository;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class CreateTaskUseCase
{
    private const TASK_ID_LOCK_NAME = 'vtiger_task_id_generation';

    private const MAX_CREATE_RETRIES = 3;

    private const RETRY_DELAY_MICROSECONDS = 100000;

    private const ENTITY_SETYPE = 'Calendar';

    public function __construct(
        private readonly IdGeneratorRepository $idGenerator,
        private readonly CreateEntityUseCase $createEntity,
        private readonly CreateTaskActivityUseCase $createActivity,
        private readonly SeActivityRelRepository $relations,
    ) {}

    /**
     * Create a new independent task (vtiger_activity).
     *
     * Orquestación:
     * 1. Generar ID único
     * 2. Crear vtiger_crmentity (CreateEntityUseCase)
     * 3. Crear vtiger_activity (CreateTaskActivityUseCase)
     * 4. Crear relaciones si aplica
     *
     * @param  CreateTaskRequest  $request  Task creation data
     * @param  int|null  $createdByUserId  ID of the user creating the task
     * @return int ID of the created task
     */
    public function execute(CreateTaskRequest $request, ?int $createdByUserId = null): int
    {
        $userId = $createdByUserId ?? CurrentUserService::idOr(1);

        $this->validateRequest($request);

        Log::debug('CreateTaskUseCase::execute', [
            'subject' => $request->subject,
            'userId' => $userId,
            'activitytype' => 'Task',
        ]);

        $attempt = 0;

        while ($attempt < self::MAX_CREATE_RETRIES) {
            try {
                return DB::connection('vtiger')->transaction(function () use ($request, $userId) {
                    // 1. Generate unique ID from crmentity
                    $activityId = $this->idGenerator->generateNextId(
                        table: 'vtiger_crmentity',
                        column: 'crmid',
                        lockName: self::TASK_ID_LOCK_NAME
                    );

                    // 2. Create vtiger_crmentity using generic use case
                    $this->createEntity->execute(
                        data: [
                            'label' => trim($request->subject),
                            'description' => $request->description ?? '',
                            'smownerid' => $request->assignedUserId ?? $userId,
                            'smcreatorid' => $userId,
                        ],
                        setype: self::ENTITY_SETYPE,
                        table: 'vtiger_crmentity',
                        userId: $userId
                    );

                    // 3. Build domain entity for mapping
                    $task = $this->buildTaskEntity($activityId, $request, $userId);
                    $mapped = TaskMapper::toPersistence($task);

                    // 4. Create vtiger_activity using task activity use case
                    $this->createActivity->execute($activityId, $mapped['activity']);

                    // 5. Create relationship if linked to another entity
                    if ($request->relatedRecordId && $request->relatedModuleType) {
                        $this->relations->link(
                            activityId: $activityId,
                            crmid: $request->relatedRecordId,
                            setype: $request->relatedModuleType
                        );
                    }

                    // Log activity (non-blocking)
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
                    'Failed to create task: '.$e->getMessage(),
                    previous: $e
                );
            }
        }

        throw new RuntimeException(
            sprintf('Failed to create task after %d retry attempts', self::MAX_CREATE_RETRIES)
        );
    }

    private function validateRequest(CreateTaskRequest $request): void
    {
        if (empty(trim($request->subject))) {
            throw new InvalidArgumentException('Task subject is required');
        }

        if (strlen($request->subject) > 255) {
            throw new InvalidArgumentException('Task subject cannot exceed 255 characters');
        }

        if (empty($request->dateStart)) {
            throw new InvalidArgumentException('Task start date is required');
        }

        if ($request->dueDate && $request->dueDate < $request->dateStart) {
            throw new InvalidArgumentException('Due date cannot be before start date');
        }

        if ($request->timeStart && $request->timeEnd && $request->timeEnd <= $request->timeStart) {
            throw new InvalidArgumentException('End time must be after start time');
        }

        if ($request->status && ! TaskStatus::isValid($request->status)) {
            throw new InvalidArgumentException(
                "Invalid status: {$request->status}. Valid statuses are: ".implode(', ', TaskStatus::all())
            );
        }

        if ($request->priority && ! TaskPriority::isValid($request->priority)) {
            throw new InvalidArgumentException(
                "Invalid priority: {$request->priority}. Valid priorities are: ".implode(', ', TaskPriority::all())
            );
        }

        if ($request->location && strlen($request->location) > 150) {
            throw new InvalidArgumentException('Location cannot exceed 150 characters');
        }
    }

    private function buildTaskEntity(int $id, CreateTaskRequest $request, int $userId): Task
    {
        return new Task(
            id: $id,
            subject: $request->subject,
            activityType: $request->activityType,
            dateStart: new \DateTimeImmutable($request->dateStart),
            dueDate: $request->dueDate ? new \DateTimeImmutable($request->dueDate) : null,
            timeStart: $request->timeStart,
            timeEnd: $request->timeEnd,
            status: $request->status ?? 'Not Started',
            priority: $request->priority ?? 'Medium',
            location: $request->location,
            description: $request->description,
            assignedUserId: $request->assignedUserId,
            assignedUserName: null,
            assignedUserEmail: null,
            createdByUserId: $userId,
            createdAt: new \DateTimeImmutable('now'),
            updatedAt: new \DateTimeImmutable('now'),
            relatedRecordId: $request->relatedRecordId,
            relatedModuleType: $request->relatedModuleType,
            sendNotification: $request->sendNotification,
            durationHours: $request->durationHours,
            durationMinutes: $request->durationMinutes,
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
            Log::info('Activity logged for task creation', [
                'activityId' => $taskId,
                'module' => 'Calendar',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log activity', [
                'activityId' => $taskId,
                'error' => $e->getMessage(),
            ]);
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
