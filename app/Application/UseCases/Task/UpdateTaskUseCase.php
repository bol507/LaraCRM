<?php

namespace App\Application\UseCases\Task;

use App\Application\DTOs\Task\UpdateTaskData;
use App\Application\DTOs\Task\UpdateTaskRequest;
use App\Application\Repositories\TaskRepositoryInterface;
use App\Application\UseCases\Core\Activity\UpdateTaskActivityUseCase;
use App\Application\UseCases\Core\Entity\UpdateEntityUseCase;
use App\Domain\Entities\Task;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class UpdateTaskUseCase
{
    private const CONFIG_DEFAULTS = [
        'allow_admin_override' => false,
        'allow_status_reopen' => true,
    ];

    private array $config;

    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly UpdateEntityUseCase $updateEntity,
        private readonly UpdateTaskActivityUseCase $updateActivity,
        array $config = [],
    ) {
        $this->config = array_merge(self::CONFIG_DEFAULTS, $config);
    }

    /**
     * Execute the update task use case
     *
     * Orquestación:
     * 1. Validar parámetros y permisos
     * 2. Validar reglas de negocio
     * 3. Convertir request a UpdateTaskData DTO
     * 4. Actualizar vtiger_activity (UpdateTaskActivityUseCase)
     * 5. Actualizar vtiger_crmentity (UpdateEntityUseCase)
     * 6. Sincronizar label si cambió subject
     */
    public function execute(int $taskId, UpdateTaskRequest $request, ?int $userId = null): bool
    {
        $userId = $userId ?? CurrentUserService::idOr(1);

        $this->validateParameters($taskId, $userId);

        $task = $this->taskRepository->findById($taskId);
        if (! $task) {
            Log::warning('Task not found', ['taskId' => $taskId]);

            return false;
        }

        $this->verifyUpdatePermission($task, $userId);

        if ($request->status !== null && $request->status !== '') {
            $this->validateStatusTransition($task, $request->status);
        }

        if ($request->hasField('dateStart') || $request->hasField('dueDate')) {
            $this->validateDateConsistency($request, $task);
        }

        $updateData = UpdateTaskData::fromRequest($taskId, $request, $userId);

        if (! $updateData->hasChanges()) {
            return true;
        }

        Log::info('=== Task Update Debug ===', [
            'taskId' => $taskId,
            'activityData' => $updateData->getActivityData(),
            'crmentityData' => $updateData->getCrmentityData(),
        ]);

        // Orquestar actualización en transacción
        DB::connection('vtiger')->transaction(function () use ($taskId, $updateData, $userId) {
            // Update vtiger_activity
            if (! empty($updateData->getActivityData())) {
                $activityData = $updateData->getActivityData();
                unset($activityData['modifiedtime']);

                $updated = $this->updateActivity->execute($taskId, $activityData);
                if (! $updated) {
                    throw new RuntimeException("Failed to update vtiger_activity for task {$taskId}");
                }
            }

            // Update vtiger_crmentity using generic use case
            if (! empty($updateData->getCrmentityData())) {
                $updated = $this->updateEntity->execute($taskId, $updateData->getCrmentityData(), $userId);
                if (! $updated) {
                    throw new RuntimeException("Failed to update vtiger_crmentity for task {$taskId}");
                }
            }

            // Sync label if subject changed
            $subjectForLabel = $updateData->getSubjectForLabelSync();
            if ($subjectForLabel !== null) {
                $this->updateEntity->updateLabel($taskId, trim($subjectForLabel));
            }
        });

        Log::info("Task {$taskId} updated successfully", [
            'task_id' => $taskId,
            'updated_by' => $userId,
            'timestamp' => now()->toDateTimeString(),
        ]);

        $this->logActivity($taskId, $userId);

        return true;
    }

    private function validateParameters(int $taskId, int $userId): void
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException("Task ID must be a positive integer, got {$taskId}");
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException("User ID must be a positive integer, got {$userId}");
        }
    }

    private function verifyUpdatePermission(Task $task, int $userId): void
    {
        if ($task->getAssignedUserId() === $userId) {
            return;
        }
        if ($task->getCreatedByUserId() === $userId) {
            return;
        }
        if ($this->config['allow_admin_override'] && $this->isAdmin($userId)) {
            return;
        }
        throw new \DomainException(
            "User {$userId} is not authorized to update task {$task->getId()}. ".
            'Only the assigned user, creator, or an administrator can update tasks.'
        );
    }

    private function validateStatusTransition(Task $task, ?string $newStatus): void
    {
        if ($newStatus === null) {
            return;
        }
        $currentStatus = $task->getStatus();
        if ($currentStatus === 'Completed' && $newStatus !== 'Completed') {
            if (! $this->config['allow_status_reopen']) {
                throw new \DomainException(
                    'Cannot change status of completed tasks. '.
                    'Completed tasks cannot be reopened without administrator permission.'
                );
            }
        }
    }

    private function validateDateConsistency(UpdateTaskRequest $request, Task $task): void
    {
        $dateStart = $request->dateStart ?? $task->getDateStart()->format('Y-m-d');
        $dueDate = $request->dueDate ?? ($task->getDueDate()?->format('Y-m-d'));

        if ($dateStart && $dueDate) {
            if (strtotime($dueDate) < strtotime($dateStart)) {
                throw new InvalidArgumentException('Due date cannot be before start date');
            }
        }
    }

    private function isAdmin(int $userId): bool
    {
        return false;
    }

    private function logActivity(int $taskId, int $userId): void
    {
        try {
            VtigerActivityTracker::updated(
                module: 'Calendar',
                crmid: $taskId,
                userId: $userId
            );
        } catch (\Exception $e) {
            Log::error('Failed to log activity', [
                'taskId' => $taskId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
