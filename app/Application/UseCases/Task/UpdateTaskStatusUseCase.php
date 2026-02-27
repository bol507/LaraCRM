<?php

namespace App\Application\UseCases\Task;

use App\Application\Repositories\TaskRepositoryInterface;
use App\Application\DTOs\Task\UpdateTaskStatusRequest;
use App\Application\ValueObjects\TaskStatus;
use RuntimeException;
use InvalidArgumentException;

class UpdateTaskStatusUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * Update task status
     * 
     * @param int $taskId Task ID
     * @param string $status New status
     * @return bool Success
     * @throws InvalidArgumentException If status is invalid
     * @throws RuntimeException If task not found
     */
    public function execute(int $taskId, string $status): bool
    {
        // Validate status
        if (!TaskStatus::isValid($status)) {
            throw new InvalidArgumentException(
                "Invalid status: {$status}. Valid statuses are: " . 
                implode(', ', TaskStatus::all())
            );
        }

        // Check if task exists
        $task = $this->taskRepository->findById($taskId);
        if (!$task) {
            throw new RuntimeException("Task {$taskId} not found");
        }

        // Business rule: Cannot change status of already completed task
        if ($task->isCompleted() && $status !== 'Completed') {
            throw new InvalidArgumentException('Cannot change status of a completed task');
        }

        // Update status
        $request = new UpdateTaskStatusRequest($status);
        return $this->taskRepository->updateStatus($taskId, $request);
    }
}