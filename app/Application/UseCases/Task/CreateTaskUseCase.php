<?php

namespace App\Application\UseCases\Task;

use App\Application\Repositories\TaskRepositoryInterface;
use App\Application\DTOs\Task\CreateTaskRequest;
use App\Application\ValueObjects\Task\TaskPriority;
use App\Application\ValueObjects\Task\TaskStatus;
use App\Services\CurrentUserService;
use App\Services\VtigerActivityTracker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CreateTaskUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * Create a new independent task (vtiger_activity).
     * 
     * @param CreateTaskRequest $request Task creation data
     * @param int|null $createdByUserId ID of the user creating the task (optional, fallback to JWT)
     * @return int ID of the created task
     */
    public function execute(CreateTaskRequest $request, ?int $createdByUserId = null): int
    {
        // Determine the creating user (JWT or parameter)
        $userId = $createdByUserId ?? CurrentUserService::idOr(1);

        // Validations
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

        if ($request->timeStart && $request->timeEnd) {
            if ($request->timeEnd <= $request->timeStart) {
                throw new InvalidArgumentException('End time must be after start time');
            }
        }

        if ($request->status && !TaskStatus::isValid($request->status)) {
            throw new InvalidArgumentException(
                "Invalid status: {$request->status}. Valid statuses are: " .
                    implode(', ', TaskStatus::all())
            );
        }

        if ($request->priority && !TaskPriority::isValid($request->priority)) {
            throw new InvalidArgumentException(
                "Invalid priority: {$request->priority}. Valid priorities are: " .
                    implode(', ', TaskPriority::all())
            );
        }

        if ($request->location && strlen($request->location) > 150) {
            throw new InvalidArgumentException('Location cannot exceed 150 characters');
        }

        // Logging for debug
        Log::debug('CreateTaskUseCase::execute', [
            'subject' => $request->subject,
            'userId' => $userId,
            'activitytype' => 'Task',
        ]);

        // Create task in the repository (returns the ID)
        $taskId = $this->taskRepository->create($request, $userId);

        if (!$taskId || !is_numeric($taskId)) {
            Log::error('Failed to create task in repository', ['subject' => $request->subject]);
            throw new \Exception('Failed to create task');
        }

        try {
            VtigerActivityTracker::created(
                module: 'Calendar',  
                crmid: (int) $taskId,
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
            // No relanzar: la tarea ya fue creada
        }

        

        return $taskId;
    }
}