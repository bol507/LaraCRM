<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Task\TaskDto;
use App\Application\UseCases\Dashboard\GetActivityDataUseCase;
use App\Application\UseCases\Dashboard\GetDashboardTasksUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Constructor
     * 
     * @param GetDashboardTasksUseCase $getDashboardTasksUseCase
     * @param GetActivityDataUseCase $getActivityDataUseCase
     */
    public function __construct(
        private readonly GetDashboardTasksUseCase $getDashboardTasksUseCase,
        private readonly GetActivityDataUseCase $getActivityDataUseCase,
    ) {}

    /**
     * Get dashboard-specific tasks for widget display
     * 
     * Returns a limited, filtered set of pending tasks optimized for dashboard widget.
     * Not intended for full task management - use /api/tasks endpoint for that.
     * 
     * @param Request $request HTTP request with authenticated user
     * @return JsonResponse JSON response with tasks array and statistics
     * 
     * @throws \Exception If task retrieval fails
     */
    public function getTasks(Request $request): JsonResponse
    {
        try {
            // Get authenticated user from JWT middleware
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Execute use case: get limited pending tasks for dashboard widget
            $result = $this->getDashboardTasksUseCase->execute($user->id, limit: 5);

            return response()->json([
                'data' => array_map(fn(TaskDto $dto) => $dto->toArray(), $result['tasks']),
                'stats' => $result['stats'], // { total, completed, pending, overdue, highPriority }
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error fetching dashboard tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity chart data for dashboard visualization
     * 
     * Returns sales and leads data grouped by time period for area chart display.
     * 
     * @param string $period Time period filter: '7days', '30days', '90days', '12months'
     * @return JsonResponse JSON response with chart data and summary
     * 
     * @throws \Exception If data retrieval fails
     */
    public function getActivityData(string $period = '12months'): JsonResponse
    {
        try {
            // Execute use case: get aggregated activity data
            $result = $this->getActivityDataUseCase->execute($period);

            return response()->json([
                'data' => $result['chartData'],
                'period' => $period,
                'summary' => $result['summary'], // { totalSales, totalLeads }
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error fetching activity data: ' . $e->getMessage()
            ], 500);
        }
    }
}