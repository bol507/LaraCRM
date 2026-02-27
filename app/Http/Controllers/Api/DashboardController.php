<?php

namespace App\Http\Controllers\Api;

use App\Application\DTOs\Task\TaskDto;
use App\Application\UseCases\Task\GetDashboardTasksUseCase;
use App\Application\UseCases\Task\UpdateTaskStatusUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Constructor
     * 
     * @param GetDashboardTasksUseCase $getDashboardTasksUseCase
     * @param UpdateTaskStatusUseCase $updateTaskStatusUseCase
     */
    public function __construct(
        private readonly GetDashboardTasksUseCase $getDashboardTasksUseCase,
        private readonly UpdateTaskStatusUseCase $updateTaskStatusUseCase
    ) {}

    /**
     * Get activity data for charts
     * 
     * Retrieves sales and leads data grouped by time period for dashboard visualization.
     * 
     * @param string $period Time period filter: '7days', '30days', '90days', '12months'
     * @return JsonResponse JSON response with chart data and summary statistics
     * 
     * @throws \Exception If database query fails
     */
    public function getActivityData(string $period = '12months'): JsonResponse
    {
        try {
            // Calculate date range based on period
            [$startDate, $endDate] = $this->getDateRange($period);

            // Get sales data (projects + invoices)
            $salesData = $this->getSalesData($startDate, $endDate, $period);

            // Get leads data
            $leadsData = $this->getLeadsData($startDate, $endDate, $period);

            // Merge and format data
            $chartData = $this->mergeData($salesData, $leadsData, $period);

            return response()->json([
                'data' => $chartData,
                'period' => $period,
                'summary' => [
                    'totalSales' => array_sum(array_column($chartData, 'ventas')),
                    'totalLeads' => array_sum(array_column($chartData, 'leads')),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error fetching activity data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate date range based on period parameter
     * 
     * @param string $period Period identifier: '7days', '30days', '90days', '12months'
     * @return array{0: Carbon, 1: Carbon} Array with start and end dates
     */
    private function getDateRange(string $period): array
    {
        $endDate = Carbon::now();
        
        switch ($period) {
            case '7days':
                $startDate = Carbon::now()->subDays(7);
                break;
            case '30days':
                $startDate = Carbon::now()->subDays(30);
                break;
            case '90days':
                $startDate = Carbon::now()->subDays(90);
                break;
            case '12months':
            default:
                $startDate = Carbon::now()->subYear();
                break;
        }

        return [$startDate, $endDate];
    }

    /**
     * Get sales data from projects and invoices
     * 
     * Aggregates target budgets from projects and totals from invoices,
     * grouped by the specified time period format.
     * 
     * @param Carbon $startDate Start of date range
     * @param Carbon $endDate End of date range
     * @param string $period Period type for date formatting
     * @return array<string, float> Sales amounts keyed by period label
     */
    private function getSalesData(Carbon $startDate, Carbon $endDate, string $period): array
    {
        $dateFormat = $this->getDateFormat($period);

        // Sales from projects (targetbudget field)
        $projectsSales = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->whereBetween('vtiger_crmentity.createdtime', [$startDate, $endDate])
            ->whereNotNull('vtiger_project.targetbudget')
            ->where('vtiger_project.targetbudget', '>', 0)
            ->selectRaw("DATE_FORMAT(vtiger_crmentity.createdtime, '{$dateFormat}') as period, 
                        SUM(vtiger_project.targetbudget) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();

        // Sales from invoices (total field)
        $invoicesSales = DB::connection('vtiger')
            ->table('vtiger_invoice')
            ->join('vtiger_crmentity', 'vtiger_invoice.invoiceid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->whereBetween('vtiger_crmentity.createdtime', [$startDate, $endDate])
            ->whereNotNull('vtiger_invoice.total')
            ->where('vtiger_invoice.total', '>', 0)
            ->selectRaw("DATE_FORMAT(vtiger_crmentity.createdtime, '{$dateFormat}') as period, 
                        SUM(vtiger_invoice.total) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();

        // Combine projects + invoices sales
        $allPeriods = array_unique(array_merge(
            array_keys($projectsSales),
            array_keys($invoicesSales)
        ));

        $salesData = [];
        foreach ($allPeriods as $periodLabel) {
            $salesData[$periodLabel] = ($projectsSales[$periodLabel] ?? 0) + 
                                        ($invoicesSales[$periodLabel] ?? 0);
        }

        return $salesData;
    }

    /**
     * Get leads data grouped by time period
     * 
     * Counts leads created within the specified date range,
     * grouped by the period format.
     * 
     * @param Carbon $startDate Start of date range
     * @param Carbon $endDate End of date range
     * @param string $period Period type for date formatting
     * @return array<string, int> Lead counts keyed by period label
     */
    private function getLeadsData(Carbon $startDate, Carbon $endDate, string $period): array
    {
        $dateFormat = $this->getDateFormat($period);

        // Count leads created
        $leads = DB::connection('vtiger')
            ->table('vtiger_leaddetails')
            ->join('vtiger_crmentity', 'vtiger_leaddetails.leadid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->whereBetween('vtiger_crmentity.createdtime', [$startDate, $endDate])
            ->selectRaw("DATE_FORMAT(vtiger_crmentity.createdtime, '{$dateFormat}') as period, 
                        COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();

        return $leads;
    }

    /**
     * Merge sales and leads data into chart-ready format
     * 
     * Combines both datasets, fills missing periods with zeros,
     * and formats period labels for display.
     * 
     * @param array<string, float> $salesData Sales amounts by period
     * @param array<string, int> $leadsData Lead counts by period
     * @param string $period Period type for label formatting
     * @return array<int, array{name: string, ventas: float, leads: int}> Chart data array
     */
    private function mergeData(array $salesData, array $leadsData, string $period): array
    {
        $allPeriods = array_unique(array_merge(
            array_keys($salesData),
            array_keys($leadsData)
        ));

        // Sort periods chronologically
        sort($allPeriods);

        $chartData = [];
        foreach ($allPeriods as $periodLabel) {
            $chartData[] = [
                'name' => $this->formatPeriodLabel($periodLabel, $period),
                'ventas' => round($salesData[$periodLabel] ?? 0, 2),
                'leads' => $leadsData[$periodLabel] ?? 0,
            ];
        }

        return $chartData;
    }

    /**
     * Get date format string based on period type
     * 
     * @param string $period Period identifier
     * @return string MySQL DATE_FORMAT pattern
     */
    private function getDateFormat(string $period): string
    {
        return match($period) {
            '7days', '30days' => '%Y-%m-%d',
            '90days' => '%Y-%m',
            '12months' => '%Y-%m',
            default => '%Y-%m',
        };
    }

    /**
     * Format period label for display in charts
     * 
     * Converts database period values (e.g., '2024-03') to 
     * user-friendly labels (e.g., 'Mar').
     * 
     * @param string $period Raw period value from database
     * @param string $periodType Period type for formatting logic
     * @return string Formatted label for display
     */
    private function formatPeriodLabel(string $period, string $periodType): string
    {
        $months = [
            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec',
        ];

        if (in_array($periodType, ['7days', '30days'])) {
            // For day-based periods, show short date format
            $date = Carbon::parse($period);
            return $date->format('d M');
        }

        // For month-based periods, show abbreviated month name
        if (preg_match('/(\d{4})-(\d{2})/', $period, $matches)) {
            $month = $matches[2];
            return $months[$month] ?? $period;
        }

        return $period;
    }

    /**
     * Get user's dashboard tasks
     * 
     * Retrieves pending and recent tasks for the authenticated user,
     * limited to a configurable number of results.
     * 
     * @param Request $request HTTP request containing query parameters
     * @return JsonResponse JSON response with tasks array and statistics
     * 
     * @throws \Exception If task retrieval fails
     */
    public function getTasks(Request $request): JsonResponse
    {
        try {
            // Get authenticated user from middleware
            $user = $request->attributes->get('auth_user');
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Get limit from query (default: 10, max: 50)
            $limit = min((int) $request->get('limit', 10), 50);

            // Execute use case
            $result = $this->getDashboardTasksUseCase->execute($user->id, $limit);

            return response()->json([
                'data' => array_map(fn(TaskDto $dto) => $dto->toArray(), $result['tasks']),
                'stats' => $result['stats'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error fetching tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update task status (mark as complete/incomplete)
     * 
     * Allows changing a task's status through the API.
     * Validates that the new status is one of the allowed values.
     * 
     * @param Request $request HTTP request containing new status
     * @param int $taskId ID of the task to update
     * @return JsonResponse JSON response with operation result
     * 
     * @throws \Illuminate\Validation\ValidationException If status validation fails
     * @throws \InvalidArgumentException If status value is invalid
     * @throws \Exception If update operation fails
     */
    public function updateTaskStatus(Request $request, int $taskId): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:Not Started,In Progress,Completed,Pending Input,Planned',
            ]);

            // Execute use case
            $success = $this->updateTaskStatusUseCase->execute(
                $taskId,
                $request->input('status')
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