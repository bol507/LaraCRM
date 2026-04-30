<?php

namespace App\Application\UseCases\Dashboard;

use App\Application\Repositories\TaskRepositoryInterface;
use App\Domain\Entities\Task;
use App\Application\DTOs\Task\TaskDto;
use Illuminate\Support\Facades\Log;

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

        try {
            $tasks = $this->taskRepository->findAllDashboardTasks($userId, $limit);
        } catch (\Throwable $e) {
            
            throw new \RuntimeException(
                'Failed to fetch tasks: ' . $e->getMessage(),
                previous: $e
            );
        }

        // Get statistics for dashboard
        try {
            $stats = $this->taskRepository->getStatistics($userId);
        } catch (\Throwable $e) {
            

            $stats = [];
        }

        // Convert to DTOs
        try {
            $taskDtos = array_map(function ($task) {
                
                if (!$task instanceof Task) {
                    
                }
                return TaskDto::fromEntity($task);
            }, $tasks);

            
            $taskDtos = array_filter($taskDtos, fn($dto) => $dto !== null);
        } catch (\Throwable $e) {
            
            throw new \RuntimeException(
                'Failed to map tasks to DTOs: ' . $e->getMessage(),
                previous: $e
            );
        }

        return [
            'tasks' => $taskDtos,
            'stats' => $stats,
        ];
    }
}
