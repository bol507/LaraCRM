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