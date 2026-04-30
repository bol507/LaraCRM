<?php

namespace App\Application\UseCases\Calendar;

use App\Application\DTOs\Calendar\ActivityDto;
use App\Application\Repositories\ActivityRepositoryInterface;
use App\Domain\Entities\Activity;
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
class GetActivityUseCase
{
    /**
     * Task repository for data access
     */
    public function __construct(
        private readonly ActivityRepositoryInterface $activityRepository
    ) {}

    public function execute(int $id): ?ActivityDto
    {
        // Execute use case to retrieve task
        $row = $this->activityRepository->findById($id);

        if (!$row) {
            return null;
        }
        
        return  ActivityDto::fromArray($row);
    }
}