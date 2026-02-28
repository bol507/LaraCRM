<?php

namespace App\Application\Repositories;

use App\Application\DTOs\Task\CreateTaskRequest;
use App\Application\DTOs\Task\UpdateTaskStatusRequest;
use App\Domain\Entities\Task;

interface TaskRepositoryInterface
{
    /**
     * Get tasks for a specific user with pagination
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of tasks to return
     * @param array $filters Optional filters (status, priority, date range)
     * @param int $offset Offset for pagination
     * @return Task[]
     */
    public function findByUserId(int $userId, int $limit = 50, array $filters = [], int $offset = 0): array;

    /**
     * Count tasks for a user
     * 
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @return int
     */
    public function countByUserId(int $userId, array $filters = []): int;

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
     * Get ALL tasks for dashboard (pending + completed)
     * Ordered by most recent first (createdtime DESC)
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of tasks
     * @return Task[]
     */
    public function findAllDashboardTasks(int $userId, int $limit = 10): array;

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
    public function updateStatus(int $taskId, UpdateTaskStatusRequest $request, int $modifiedBy): bool;

    /**
     * Delete task (soft delete)
     * 
     * @param int $taskId Task ID
     * @return bool Success
     */
    public function delete(int $taskId): bool;

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