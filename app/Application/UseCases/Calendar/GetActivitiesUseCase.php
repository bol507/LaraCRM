<?php

namespace App\Application\UseCases\Calendar;

use App\Application\DTOs\Calendar\ActivityDto;
use App\Application\Repositories\ActivityRepositoryInterface;
use App\Domain\Entities\Activity;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Get Tasks Use Case
 * 
 * Orchestrates the retrieval of tasks for a specific user with pagination and filters.
 * Transforms domain entities to DTOs for API response.
 * 
 * @package App\Application\UseCases\Task
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 * 
 * @see \App\Application\Repositories\TaskRepositoryInterface
 * @see \App\Application\DTOs\TaskDto
 */
class GetActivitiesUseCase
{
    /**
     * Task repository for data access
     */
    public function __construct(
        private readonly ActivityRepositoryInterface $activityRepository
    ) {}

    public function execute(
        int $userId,
        int $page = 1,
        int $limit = 50,
        array $filters = [],
        ?int $requestedUserId = null,
        ?array $subordinateIds = []
    ): array {
        // Validations
        if ($userId <= 0) throw new InvalidArgumentException("User ID must be positive, got {$userId}");
        if ($page < 1) throw new InvalidArgumentException("Page must be at least 1, got {$page}");
        if ($limit < 1 || $limit > 100) throw new InvalidArgumentException("Limit must be between 1 and 100, got {$limit}");

        $offset = ($page - 1) * $limit;

        $allowedOwnerIds = $subordinateIds === null
            ? null
            : array_values(array_unique(array_merge([$userId], $subordinateIds)));

        $result = $this->activityRepository->findTasksByOwnerIds($allowedOwnerIds, $limit, $filters, $offset);

        $activities = $result['activities'] ?? [];
        if ($activities instanceof \Illuminate\Support\Collection) {
            $activities = $activities->all();
        }

        $pagination = $result['pagination'] ?? [];

        // Flexible mapping: accepts Activity, ActivityDto, stdClass, or array
        $activityDtos = array_map(function ($item) {
            if ($item instanceof ActivityDto) {
                return $item;
            }
            // Handle stdClass or array directly from database
            if (is_object($item) || is_array($item)) {
                return ActivityDto::fromArray($item);
            }
            throw new \InvalidArgumentException(
                "Expected ActivityDto, stdClass or array, got " . gettype($item) .
                    (is_object($item) ? ' (' . get_class($item) . ')' : '')
            );
        }, $activities);

        // Stats also use allowed IDs
        $stats = $this->activityRepository->calculateStats($allowedOwnerIds, $filters);

        return [
            'activities' => $activityDtos,
            'pagination' => $pagination,
            'stats' => $stats,
        ];
    }
}
