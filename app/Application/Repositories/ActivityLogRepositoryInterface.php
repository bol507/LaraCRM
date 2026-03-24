<?php

namespace App\Application\Repositories;

use App\Application\DTOs\ActivityLog\ActivityLogListDTO;

/**
 * Interface for the activity repository.
 * 
 * Defines the contract for retrieving activity records.
 */
interface ActivityLogRepositoryInterface
{
    /**
     * Get recent activities.
     * 
     * @param int $limit Maximum number of activities to return
     * @return ActivityLogListDTO List of recent activities
     */
    public function getRecentActivities(int $limit = 20): ActivityLogListDTO;

    /**
     *  get recent activities with advanced filters
     *
     * @param integer $limit
     * @param string|null $entityType
     * @param string|null $action
     * @param integer|null $userId
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param string|null $search
     * @return ActivityLogListDTO
     */
    public function getRecentActivitiesWithFilters(
        int $limit = 50,
        ?string $entityType = null,
        ?string $action = null,
        ?int $userId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null
    ): ActivityLogListDTO;

    /**
     * Get activities by entity type.
     * 
     * @param string $entityType Entity type (project, client, etc.)
     * @param int $limit Maximum number of activities
     * @return ActivityLogListDTO List of filtered activities
     */
    public function getByEntityType(string $entityType, int $limit = 20): ActivityLogListDTO;

    /**
     * Get activities by user.
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of activities
     * @return ActivityLogListDTO List of user activities
     */
    public function getByUser(int $userId, int $limit = 20): ActivityLogListDTO;
}