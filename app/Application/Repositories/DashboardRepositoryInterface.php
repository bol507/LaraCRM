<?php

namespace App\Application\Repositories;

/**
 * Interface for dashboard data operations
 * 
 * Provides aggregated data for dashboard widgets and charts.
 */
interface DashboardRepositoryInterface
{
    /**
     * Get sales data grouped by time period
     * 
     * Aggregates revenue from projects (targetbudget) and invoices (total)
     * for chart visualization.
     * 
     * @param string $period Period identifier: '7days', '30days', '90days', '12months'
     * @return array<string, float> Sales amounts keyed by period label (e.g., '2024-03')
     */
    public function getSalesData(string $period): array;

    /**
     * Get leads data grouped by time period
     * 
     * Counts leads created within the specified period for chart visualization.
     * 
     * @param string $period Period identifier: '7days', '30days', '90days', '12months'
     * @return array<string, int> Lead counts keyed by period label
     */
    public function getLeadsData(string $period): array;

    /**
     * Get opportunities data grouped by time period
     * 
     * Counts or sums potential amounts by period for additional chart metrics.
     * 
     * @param string $period Period identifier
     * @return array<string, float|int> Opportunity data keyed by period
     */
    public function getOpportunitiesData(string $period): array;

    /**
     * Get conversion rate data by period
     * 
     * Calculates the ratio of converted leads to total leads by period.
     * 
     * @param string $period Period identifier
     * @return array<string, float> Conversion rates (0-100) keyed by period
     */
    public function getConversionRateData(string $period): array;

    /**
     * Get dashboard summary metrics
     * 
     * Returns high-level KPIs for dashboard cards.
     * 
     * @param int $userId User ID for personalized metrics
     * @return array{
     *   activeClients: int,
     *   monthlySales: float,
     *   totalQuotes: int,
     *   conversionRate: float,
     *   pendingTasks: int,
     *   overdueTasks: int
     * }
     */
    public function getSummaryMetrics(int $userId): array;
    
}