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
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    /**
     * Execute the use case.
     * 
     * @param int|null $limit Number of activities to return (optional)
     * @return ActivityLogListDTO List of recent activities
     * 
     * @throws \InvalidArgumentException If the limit is invalid
     */
    public function execute(?int $limit = null): ActivityLogListDTO
    {
        $limit = $this->normalizeLimit($limit);

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

        if ($limit < 1) {
            throw new \InvalidArgumentException('Limit must be at least 1');
        }

        if ($limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit cannot exceed ' . self::MAX_LIMIT);
        }

        return $limit;
    }
}