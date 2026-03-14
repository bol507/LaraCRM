<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Task\TaskDto;
use App\Application\UseCases\Dashboard\GetActivityDataUseCase;
use App\Application\UseCases\Dashboard\GetDashboardTasksUseCase;
use App\Application\UseCases\Dashboard\GetDashboardMetricsUseCase;
use App\Application\UseCases\Task\UpdateTaskStatusUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Constructor
     * 
     * @param GetDashboardTasksUseCase $getDashboardTasksUseCase
     * @param GetActivityDataUseCase $getActivityDataUseCase
     * @param GetDashboardMetricsUseCase $getDashboardMetricsUseCase
     */
    public function __construct(
        private readonly GetDashboardTasksUseCase $getDashboardTasksUseCase,
        private readonly GetActivityDataUseCase $getActivityDataUseCase,
        private readonly GetDashboardMetricsUseCase $getDashboardMetricsUseCase,
        private readonly UpdateTaskStatusUseCase $updateTaskStatusUseCase,
    ) {}

    /**
     * Get dashboard-specific tasks for widget display
     * 
     * @param Request $request HTTP request with authenticated user
     * @return JsonResponse JSON response with tasks array and statistics
     */
    public function getTasks(Request $request): JsonResponse
    {
        try {
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $limit = min((int) $request->get('limit', 5), 50);

            $result = $this->getDashboardTasksUseCase->execute($user->getId(), limit: $limit);

            return response()->json([
                'data' => array_map(fn(TaskDto $dto) => $dto->toArray(), $result['tasks']),
                'stats' => $result['stats'],
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard tasks error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error fetching dashboard tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity chart data for dashboard visualization
     * 
     * @param string $period Time period filter: '7days', '30days', '90days', '12months'
     * @return JsonResponse JSON response with chart data and summary
     */
    public function getActivityData(string $period = '12months'): JsonResponse
    {
        try {
            $result = $this->getActivityDataUseCase->execute($period);

            return response()->json([
                'data' => $result['chartData'],
                'period' => $period,
                'summary' => $result['summary'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error fetching activity data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard summary metrics
     * 
     * @param Request $request HTTP request with authenticated user
     * @return JsonResponse JSON response with metrics
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $metrics = $this->getDashboardMetricsUseCase->execute($user->getId());

            return response()->json($metrics);
        } catch (\Exception $e) {
            
            Log::error('Dashboard metrics error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error fetching metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update task status (mark as complete/incomplete)
     * 
     * @param Request $request HTTP request containing new status
     * @param int $taskId ID of the task to update
     * @return JsonResponse JSON response with operation result
     */
    public function updateTaskStatus(Request $request, int $taskId): JsonResponse
    {
        try {

            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request->validate([
                'status' => 'required|string|in:Not Started,In Progress,Completed,Pending Input,Planned',
            ]);

            $success = $this->updateTaskStatusUseCase->execute(
                $taskId,
                $request->input('status'),
                $user->getId()
            );

            if (!$success) {
                return response()->json([
                    'error' => 'Could not update task'
                ], 500);
            }

            return response()->json([
                'message' => 'Task updated successfully',
                'task_id' => $taskId,
                'status' => $request->input('status'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error updating task: ' . $e->getMessage()
            ], 500);
        }
    }
}
