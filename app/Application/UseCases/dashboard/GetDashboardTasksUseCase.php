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
            Log::error('UseCase: Error en findAllDashboardTasks', [
                'user_id' => $userId,
                'limit' => $limit,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            throw new \RuntimeException(
                'Failed to fetch tasks: ' . $e->getMessage(),
                previous: $e
            );
        }

        // Get statistics for dashboard
        try {
            $stats = $this->taskRepository->getStatistics($userId);
        } catch (\Throwable $e) {
            Log::error('UseCase: Error en getStatistics', [
                'user_id' => $userId,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            $stats = [];
        }

        // Convert to DTOs
        try {
            $taskDtos = array_map(function ($task) {
                
                if (!$task instanceof Task) {
                    Log::warning('UseCase: Task no es instancia de Task entity', [
                        'task_type' => gettype($task),
                        'task_class' => is_object($task) ? get_class($task) : null,
                    ]);
                    return null;
                }
                return TaskDto::fromEntity($task);
            }, $tasks);

            
            $taskDtos = array_filter($taskDtos, fn($dto) => $dto !== null);
        } catch (\Throwable $e) {
            Log::error('UseCase: Error en mapeo de DTOs', [
                'tasks_count' => is_array($tasks) ? count($tasks) : 0,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
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
