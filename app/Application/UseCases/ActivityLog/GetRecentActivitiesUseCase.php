<?php

namespace App\Application\UseCases\ActivityLog;

use App\Application\DTOs\ActivityLog\ActivityLogListDTO;
use App\Application\Repositories\ActivityLogRepositoryInterface;



/**
 * Use case for retrieving recent activities.
 * 
 * Orchestrates fetching recent activities from the repository.
 * Follows the single responsibility principle: only coordinates,
 * does not contain business logic or data access logic.
 */
final readonly class GetRecentActivitiesUseCase
{
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 200;

    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    /**
     * Execute the use case.
     * 
     * @param int|null $limit Number of activities to return (optional)
     * @param string|null $entityType Filter by entity type
     * @param string|null $action Filter by action type
     * @param int|null $userId Filter by user ID
     * @param string|null $dateFrom Filter by date from
     * @param string|null $dateTo Filter by date to
     * @param string|null $search Search term for entity name
     * @return ActivityLogListDTO List of recent activities
     * 
     * @throws \InvalidArgumentException If the limit is invalid
     */
    public function execute(
        ?int $limit = null,
        ?string $entityType = null,
        ?string $action = null,
        ?int $userId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null
    ): ActivityLogListDTO {
        $limit = $this->normalizeLimit($limit);

        // If filters are present, use the filtered method
        if ($entityType || $action || $userId || $dateFrom || $dateTo || $search) {
            return $this->activityLogRepository->getRecentActivitiesWithFilters(
                limit: $limit,
                entityType: $entityType,
                action: $action,
                userId: $userId,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                search: $search
            );
        }

        // Without filters, use simple method
        return $this->activityLogRepository->getRecentActivities($limit);
    }

    /**
     * Normalize and validate the limit.
     * 
     * @param int|null $limit
     * @return int
     * @throws \InvalidArgumentException
     */
    private function normalizeLimit(?int $limit): int
    {
        if ($limit === null) {
            return self::DEFAULT_LIMIT;
        }

        return max(1, min($limit, self::MAX_LIMIT));
    }
}