<?php

namespace App\Application\UseCases\Opportunity;

use App\Application\Repositories\OpportunityRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAllOpportunitiesUseCase
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository
    ) {}
    /**
     * Execute the use case to retrieve paginated opportunities.
     * 
     * @param int $page Page number (default: 1)
     * @param int $perPage Items per page (default: 20)
     * @param string|null $search Search term for filtering by potentialname
     * @param int|null $accountId Filter by client/account ID (optional)
     * @param string|null $sortBy Sort by column (default: createdtime)
     * @param string|null $sortOrder Sort order (default: ASC)
     * @return LengthAwarePaginator Paginated collection of opportunities
     */
    public function execute(int $page = 1, int $perPage = 20, ?string $search = null, ?int $accountId = null, ?string $sortBy = null, ?string $sortOrder = null): LengthAwarePaginator
    {
        return $this->opportunityRepository->getAll($page, $perPage, $search, $accountId, $sortBy , $sortOrder );
    }
}
