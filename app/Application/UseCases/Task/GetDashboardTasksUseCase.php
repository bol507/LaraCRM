<?php

namespace App\Application\UseCases\Task;

use App\Application\DTOs\Task\TaskDto;
use App\Application\Repositories\TaskRepositoryInterface;

use App\Domain\Entities\Task;

class GetDashboardTasksUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * Get dashboard tasks for a user
     * 
     * @param int $userId Authenticated user ID
     * @param int $limit Maximum number of tasks (default: 10)
     * @return array{tasks: TaskDto[], stats: array}
     */
    public function execute(int $userId, int $limit = 10): array
    {
        // Get tasks
        $tasks = $this->taskRepository->findDashboardTasks($userId, $limit);

        // Get statistics
        $stats = $this->taskRepository->getStatistics($userId);

        // Convert to DTOs
        $taskDtos = array_map(function (Task $task) {
            return TaskDto::fromEntity($task);
        }, $tasks);

        return [
            'tasks' => $taskDtos,
            'stats' => $stats,
        ];
    }
}