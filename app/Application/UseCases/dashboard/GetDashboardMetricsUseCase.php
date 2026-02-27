<?php

namespace App\Application\UseCases\Dashboard;

use App\Application\Repositories\DashboardRepositoryInterface;

class GetDashboardMetricsUseCase
{
    public function __construct(
        private readonly DashboardRepositoryInterface $dashboardRepository
    ) {}

    /**
     * Get dashboard summary metrics for a user
     * 
     * @param int $userId Authenticated user ID
     * @return array{
     *   activeClients: int,
     *   monthlySales: float,
     *   totalQuotes: int,
     *   conversionRate: float,
     *   pendingTasks: int,
     *   overdueTasks: int
     * }
     */
    public function execute(int $userId): array
    {
        return $this->dashboardRepository->getSummaryMetrics($userId);
    }
}