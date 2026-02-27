<?php

namespace App\Application\UseCases\Task;

use App\Application\Repositories\TaskRepositoryInterface;
use App\Domain\Entities\Task;
use App\Application\DTOs\Task\TaskDto;

class GetTasksUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * Get tasks with filtering and pagination
     * 
     * @param int $userId User ID
     * @param int $page Page number (1-based)
     * @param int $limit Items per page
     * @param array $filters Optional filters
     * @return array{tasks: TaskDto[], pagination: array, stats: array}
     */
    public function execute(int $userId, int $page = 1, int $limit = 50, array $filters = []): array
    {
        // Calculate offset
        $offset = ($page - 1) * $limit;

        // Get tasks from repository
        $tasks = $this->taskRepository->findByUserId(
            $userId, 
            $limit, 
            $filters,
            $offset
        );

        // Get total count
        $totalCount = $this->taskRepository->countByUserId($userId, $filters);

        // Get statistics
        $stats = $this->taskRepository->getStatistics($userId);

        // Convert to DTOs
        $taskDtos = array_map(function (Task $task) {
            return TaskDto::fromEntity($task);
        }, $tasks);

        // Calculate pagination metadata
        $totalPages = ceil($totalCount / $limit);

        return [
            'tasks' => $taskDtos,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
            'stats' => $stats,
        ];
    }

    /**
     * Get task by ID
     * 
     * @param int $taskId Task ID
     * @return Task|null
     */
    public function getById(int $taskId): ?Task
    {
        return $this->taskRepository->findById($taskId);
    }
}