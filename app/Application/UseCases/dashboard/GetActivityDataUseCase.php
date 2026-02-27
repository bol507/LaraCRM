<?php

namespace App\Application\UseCases\Dashboard;

use App\Application\Repositories\DashboardRepositoryInterface;

class GetActivityDataUseCase
{
    public function __construct(
        private readonly DashboardRepositoryInterface $dashboardRepository
    ) {}

    /**
     * Get activity data for charts
     * 
     * @param string $period Time period: '7days', '30days', '90days', '12months'
     * @return array{chartData: array, summary: array}
     */
    public function execute(string $period = '12months'): array
    {
        // Get sales data
        $salesData = $this->dashboardRepository->getSalesData($period);

        // Get leads data
        $leadsData = $this->dashboardRepository->getLeadsData($period);

        // Merge data
        $chartData = $this->mergeData($salesData, $leadsData, $period);

        return [
            'chartData' => $chartData,
            'summary' => [
                'totalSales' => array_sum(array_column($chartData, 'ventas')),
                'totalLeads' => array_sum(array_column($chartData, 'leads')),
            ],
        ];
    }

    /**
     * Merge sales and leads data
     */
    private function mergeData(array $salesData, array $leadsData, string $period): array
    {
        $allPeriods = array_unique(array_merge(
            array_keys($salesData),
            array_keys($leadsData)
        ));

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
     * Format period label for display
     */
    private function formatPeriodLabel(string $period, string $periodType): string
    {
        $months = [
            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dec',
        ];

        if (preg_match('/(\d{4})-(\d{2})/', $period, $matches)) {
            return $months[$matches[2]] ?? $period;
        }

        return $period;
    }
}