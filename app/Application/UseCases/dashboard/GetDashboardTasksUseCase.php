<?php

namespace App\Application\UseCases\Dashboard;

use App\Application\Repositories\TaskRepositoryInterface;
use App\Domain\Entities\Task;
use App\Application\DTOs\Task\TaskDto;

class GetDashboardTasksUseCase
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * Get dashboard tasks for a user
     * 
     * Returns a limited, optimized set of tasks for dashboard widget display.
     * Only includes pending/in-progress tasks, sorted by priority and due date.
     * 
     * @param int $userId Authenticated user ID
     * @param int $limit Maximum number of tasks (default: 5 for widget)
     * @return array{tasks: TaskDto[], stats: array}
     */
    public function execute(int $userId, int $limit = 5): array
    {
        // Get dashboard-specific tasks (only pending/in-progress)
        $tasks = $this->taskRepository->findDashboardTasks($userId, $limit);

        // Get statistics for dashboard
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