<?php

namespace App\Application\Repositories;

use App\Domain\Entities\Activity;

interface ActivityRepositoryInterface
{
    /**
     * Find activity by ID
     * 
     * @param int $activityId The activity ID to find
     * @return object|null Activity entity if found, null otherwise
     * 
     */
    public function findById(int $activityId): ?object;
    /**
     * Finds tasks belonging to multiple owners with pagination support.
     *
     * @param array $ownerIds List of owner/user IDs to filter tasks by
     * @param integer $limit  Number of tasks to return per page
     * @param array $filters Additional filters (status, priority, search terms, etc.)
     * @param integer $offset Number of records to skip (for pagination)
     * @return array  Array of task records for the current, page Array containing total, per_page, current_page, and total_pages
     */
    public function findTasksByOwnerIds(array $ownerIds, int $limit, array $filters, int $offset): array;

    /**
     * Calculate task statistics for a set of owner IDs
     * 
     * @param array<int>|null $ownerIds Array of user IDs, or null for global view (admin)
     * @param array $filters Optional filters to apply
     * @return array{total: int, completed: int, pending: int, overdue: int, highPriority: int}
     */
    public function calculateStats(?array $ownerIds, array $filters = []): array;

    public function insert(array $data): int;
    public function update(int $activityId, array $data): bool;
}
