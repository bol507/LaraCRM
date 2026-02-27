<?php

namespace App\Application\UseCases\Task;

use App\Application\Repositories\TaskRepositoryInterface;
use RuntimeException;

class DeleteTaskUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * Delete a task (soft delete)
     * 
     * @param int $taskId Task ID
     * @return bool Success
     * @throws RuntimeException If task not found
     */
    public function execute(int $taskId): bool
    {
        // Check if task exists
        $task = $this->taskRepository->findById($taskId);
        if (!$task) {
            throw new RuntimeException("Task {$taskId} not found");
        }

        // Delete task (soft delete via repository)
        return $this->taskRepository->delete($taskId);
    }
}