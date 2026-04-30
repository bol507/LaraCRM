<?php

namespace App\Application\UseCases\Task;

use App\Application\DTOs\Task\TaskDto;
use App\Application\Repositories\TaskRepositoryInterface;
use App\Domain\Entities\Task;
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
class GetTasksUseCase
{
    /**
     * Task repository for data access
     */
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * Execute the get tasks use case
     * 
     * Retrieves tasks for a specific user with pagination, filters, and statistics.
     * 
     * @param int $userId User ID to get tasks for
     * @param int $page Page number (1-based, default: 1)
     * @param int $limit Items per page (1-100, default: 50)
     * @param array $filters Optional filters (status, priority, dateFrom, dateTo, search)
     * 
     * @return array{
     *     tasks: TaskDto[],
     *     pagination: array{
     *         current_page: int,
     *         per_page: int,
     *         total: int,
     *         total_pages: int,
     *         has_more: bool
     *     },
     *     stats: array{
     *         total: int,
     *         completed: int,
     *         pending: int,
     *         overdue: int,
     *         highPriority: int
     *     }
     * }
     * 
     * @throws InvalidArgumentException If userId, page, or limit is invalid
     * 
     * @example
     * // Get first page of tasks
     * $result = $useCase->execute(userId: 5, page: 1, limit: 20);
     * 
     * @example
     * // Get tasks with filters
     * $result = $useCase->execute(
     *     userId: 5,
     *     page: 1,
     *     limit: 20,
     *     filters: ['status' => ['In Progress'], 'priority' => ['High']]
     * );
     */
    public function execute(
        int $userId,
        int $page = 1,
        int $limit = 50,
        array $filters = [],
        ?int $requestedUserId = null,
        array $subordinateIds = []
    ): array {
        // Validate input parameters
        if ($userId <= 0) throw new InvalidArgumentException("User ID must be positive, got {$userId}");
        if ($page < 1) throw new InvalidArgumentException("Page must be at least 1, got {$page}");
        if ($limit < 1 || $limit > 100) throw new InvalidArgumentException("Limit must be between 1 and 100, got {$limit}");

        // Calculate offset from page number
        $offset = ($page - 1) * $limit;
        $allowedOwnerIds = array_values(array_unique(array_merge([$userId], $subordinateIds)));

        $result = $this->taskRepository->findTasksByOwnerIds($allowedOwnerIds, $limit, $filters, $offset);


        if (isset($result['tasks']) && is_array($result['tasks'])) {
            // Estructura correcta: ['tasks' => [...], 'pagination' => [...]]
            $tasks = $result['tasks'];
            $pagination = $result['pagination'] ?? [];
        } elseif (is_array($result) && !empty($result) && array_keys($result)[0] === 0) {
            // Repository retorna array directo de Tasks
            $tasks = $result;
            $pagination = [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => count($result),
                'total_pages' => ceil(count($result) / $limit),
            ];
        } else {
            // Caso inesperado: inicializar array vacío
            $tasks = [];
            $pagination = [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => 0,
                'total_pages' => 0,
            ];
        }



        if (!is_array($tasks)) {

            $tasks = $tasks instanceof Task ? [$tasks] : [];
        }

        $taskDtos = array_map(function ($task) {
            if ($task instanceof TaskDto) {
                return $task;
            }

            if ($task instanceof Task) {
                return TaskDto::fromEntity($task);
            }


            throw new \InvalidArgumentException(
                "Expected Task or TaskDto, got " . gettype($task)
            );
        }, $tasks);

        $stats = $this->taskRepository->calculateStats($allowedOwnerIds, $filters);

        return [
            'tasks' => $taskDtos,
            'pagination' => $pagination,
            'stats' => $stats,
        ];
    }

    /**
     * Get task by ID
     * 
     * Retrieves a single task by its unique identifier.
     * 
     * @param int $taskId Task ID to retrieve
     * @return Task|null Task entity if found, null otherwise
     * 
     * @throws InvalidArgumentException If taskId is invalid
     * 
     * @example
     * $task = $useCase->getById(123);
     * if ($task) {
     *     echo $task->getSubject();
     * }
     */
    public function getById(int $taskId): ?TaskDto
    {
        if ($taskId <= 0) {
            throw new InvalidArgumentException("Task ID must be positive, got {$taskId}");
        }

        return $this->taskRepository->findById($taskId);
    }

    /**
     * Retrieves task statistics for a user and their subordinates
     *
     * @param int $userId The ID of the main user
     * @param array $subordinateIds List of subordinate user IDs
     * @param array $filters Additional filters (status, priority, search terms, etc.)
     * @return array Task statistics from calculateStats method
     * @throws InvalidArgumentException If userId is not positive
     */
    public function getStats(int $userId, array $subordinateIds = [], array $filters = []): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException("User ID must be positive, got {$userId}");
        }

        $allowedOwnerIds = array_values(array_unique(array_merge([$userId], $subordinateIds)));

        return $this->taskRepository->calculateStats($allowedOwnerIds, $filters);
    }
}
