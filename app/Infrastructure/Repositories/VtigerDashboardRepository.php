<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\DashboardRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VtigerDashboardRepository implements DashboardRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function getSalesData(string $period): array
    {
        [$startDate, $endDate, $dateFormat] = $this->getDateParams($period);

        // Sales from projects (targetbudget)
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

        // Sales from invoices (total)
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

        // Combine both sources
        return $this->mergeSalesData($projectsSales, $invoicesSales);
    }

    /**
     * {@inheritDoc}
     */
    public function getLeadsData(string $period): array
    {
        [$startDate, $endDate, $dateFormat] = $this->getDateParams($period);

        return DB::connection('vtiger')
            ->table('vtiger_leaddetails')
            ->join('vtiger_crmentity', 'vtiger_leaddetails.leadid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->whereBetween('vtiger_crmentity.createdtime', [$startDate, $endDate])
            ->selectRaw("DATE_FORMAT(vtiger_crmentity.createdtime, '{$dateFormat}') as period, 
                        COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function getOpportunitiesData(string $period): array
    {
        [$startDate, $endDate, $dateFormat] = $this->getDateParams($period);

        return DB::connection('vtiger')
            ->table('vtiger_potential')
            ->join('vtiger_crmentity', 'vtiger_potential.potentialid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->whereBetween('vtiger_crmentity.createdtime', [$startDate, $endDate])
            ->selectRaw("DATE_FORMAT(vtiger_crmentity.createdtime, '{$dateFormat}') as period, 
                        COUNT(*) as count,
                        SUM(vtiger_potential.amount) as total_amount")
            ->groupBy('period')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->period => [
                    'count' => (int) $row->count,
                    'amount' => (float) $row->total_amount,
                ]];
            })
            ->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function getConversionRateData(string $period): array
    {
        [$startDate, $endDate, $dateFormat] = $this->getDateParams($period);

        // Get leads by period
        $leads = DB::connection('vtiger')
            ->table('vtiger_leaddetails')
            ->join('vtiger_crmentity', 'vtiger_leaddetails.leadid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->whereBetween('vtiger_crmentity.createdtime', [$startDate, $endDate])
            ->selectRaw("DATE_FORMAT(vtiger_crmentity.createdtime, '{$dateFormat}') as period, 
                        COUNT(*) as total,
                        SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as converted")
            ->groupBy('period')
            ->get();

        // Calculate conversion rate per period
        $result = [];
        foreach ($leads as $row) {
            $rate = $row->total > 0 ? ($row->converted / $row->total) * 100 : 0;
            $result[$row->period] = round($rate, 2);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getSummaryMetrics(int $userId): array
    {
        // Active clients (unique accounts with active projects)
        $activeClients = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_project.projectstatus', 'En Curso')
            ->where('vtiger_project.linktoaccountscontacts', '>', 0)
            ->distinct('vtiger_project.linktoaccountscontacts')
            ->count('vtiger_project.linktoaccountscontacts');

        // Monthly sales (projects + invoices for current month)
        $monthlySales = DB::connection('vtiger')
            ->table('vtiger_project')
            ->join('vtiger_crmentity', 'vtiger_project.projectid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->whereMonth('vtiger_crmentity.createdtime', now()->month)
            ->whereYear('vtiger_crmentity.createdtime', now()->year)
            ->whereNotNull('vtiger_project.targetbudget')
            ->sum('vtiger_project.targetbudget');

        $monthlyInvoices = DB::connection('vtiger')
            ->table('vtiger_invoice')
            ->join('vtiger_crmentity', 'vtiger_invoice.invoiceid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->whereMonth('vtiger_crmentity.createdtime', now()->month)
            ->whereYear('vtiger_crmentity.createdtime', now()->year)
            ->whereNotNull('vtiger_invoice.total')
            ->sum('vtiger_invoice.total');

        $totalMonthlySales = ($monthlySales ?? 0) + ($monthlyInvoices ?? 0);

        // Total quotes
        $totalQuotes = DB::connection('vtiger')
            ->table('vtiger_quotes')
            ->join('vtiger_crmentity', 'vtiger_quotes.quoteid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->whereMonth('vtiger_crmentity.createdtime', now()->month)
            ->whereYear('vtiger_crmentity.createdtime', now()->year)
            ->count();

        // Conversion rate (simplified: accepted quotes / total quotes)
        $acceptedQuotes = DB::connection('vtiger')
            ->table('vtiger_quotes')
            ->join('vtiger_crmentity', 'vtiger_quotes.quoteid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_quotes.quote_stage', 'Accepted')
            ->whereMonth('vtiger_crmentity.createdtime', now()->month)
            ->whereYear('vtiger_crmentity.createdtime', now()->year)
            ->count();

        $conversionRate = $totalQuotes > 0 ? ($acceptedQuotes / $totalQuotes) * 100 : 0;

        // Pending and overdue tasks for user
        $pendingTasks = DB::connection('vtiger')
            ->table('vtiger_activity')
            ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_activity.activitytype', 'Task')
            ->where('vtiger_activity.status', '!=', 'Completed')
            ->where(function ($q) use ($userId) {
                $q->where('vtiger_activity.smownerid', $userId)
                  ->orWhere('vtiger_crmentity.smcreatorid', $userId);
            })
            ->count();

        $overdueTasks = DB::connection('vtiger')
            ->table('vtiger_activity')
            ->join('vtiger_crmentity', 'vtiger_activity.activityid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_crmentity.deleted', 0)
            ->where('vtiger_activity.activitytype', 'Task')
            ->where('vtiger_activity.status', '!=', 'Completed')
            ->where('vtiger_activity.due_date', '<', now()->format('Y-m-d'))
            ->where(function ($q) use ($userId) {
                $q->where('vtiger_activity.smownerid', $userId)
                  ->orWhere('vtiger_crmentity.smcreatorid', $userId);
            })
            ->count();

        return [
            'activeClients' => (int) $activeClients,
            'monthlySales' => (float) $totalMonthlySales,
            'totalQuotes' => (int) $totalQuotes,
            'conversionRate' => round($conversionRate, 1),
            'pendingTasks' => (int) $pendingTasks,
            'overdueTasks' => (int) $overdueTasks,
        ];
    }

    /**
     * Get date parameters based on period
     * 
     * @param string $period Period identifier
     * @return array{0: Carbon, 1: Carbon, 2: string} [startDate, endDate, dateFormat]
     */
    private function getDateParams(string $period): array
    {
        $endDate = Carbon::now();
        
        switch ($period) {
            case '7days':
                $startDate = Carbon::now()->subDays(7);
                $dateFormat = '%Y-%m-%d';
                break;
            case '30days':
                $startDate = Carbon::now()->subDays(30);
                $dateFormat = '%Y-%m-%d';
                break;
            case '90days':
                $startDate = Carbon::now()->subDays(90);
                $dateFormat = '%Y-%m';
                break;
            case '12months':
            default:
                $startDate = Carbon::now()->subYear();
                $dateFormat = '%Y-%m';
                break;
        }

        return [$startDate, $endDate, $dateFormat];
    }

    /**
     * Merge sales data from multiple sources
     * 
     * @param array<string, float> $projectsSales
     * @param array<string, float> $invoicesSales
     * @return array<string, float>
     */
    private function mergeSalesData(array $projectsSales, array $invoicesSales): array
    {
        $allPeriods = array_unique(array_merge(
            array_keys($projectsSales),
            array_keys($invoicesSales)
        ));

        $merged = [];
        foreach ($allPeriods as $period) {
            $merged[$period] = ($projectsSales[$period] ?? 0) + ($invoicesSales[$period] ?? 0);
        }

        return $merged;
    }
}