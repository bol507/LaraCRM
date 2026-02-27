<?php

namespace App\Application\Repositories;

use App\Application\DTOs\Task\CreateTaskRequest;
use App\Application\DTOs\UpdateTaskStatusRequest;
use App\Domain\Entities\Task;

interface TaskRepositoryInterface
{
    /**
     * Get tasks for a specific user
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of tasks to return
     * @param array $filters Optional filters (status, priority, date range)
     * @return Task[]
     */
    public function findByUserId(int $userId, int $limit = 50, array $filters = []): array;

    /**
     * Get tasks for dashboard (recent/pending tasks)
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of tasks
     * @return Task[]
     */
    public function findDashboardTasks(int $userId, int $limit = 10): array;

    /**
     * Find task by ID
     * 
     * @param int $taskId Task ID
     * @return Task|null
     */
    public function findById(int $taskId): ?Task;

    /**
     * Create a new task
     * 
     * @param CreateTaskRequest $request
     * @return int Created task ID
     */
    public function create(CreateTaskRequest $request): int;

    /**
     * Update task status
     * 
     * @param int $taskId Task ID
     * @param UpdateTaskStatusRequest $request
     * @return bool Success
     */
    public function updateStatus(int $taskId, UpdateTaskStatusRequest $request): bool;

    /**
     * Get task statistics for a user
     * 
     * @param int $userId User ID
     * @return array{total: int, completed: int, pending: int, overdue: int, highPriority: int}
     */
    public function getStatistics(int $userId): array;

    /**
     * Get tasks grouped by status
     * 
     * @param int $userId User ID
     * @return array<string, int>
     */
    public function getCountByStatus(int $userId): array;

    /**
     * Get tasks grouped by priority
     * 
     * @param int $userId User ID
     * @return array<string, int>
     */
    public function getCountByPriority(int $userId): array;
}