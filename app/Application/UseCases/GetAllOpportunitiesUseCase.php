<?php

namespace App\Application\UseCases;

use App\Application\Repositories\OpportunityRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAllOpportunitiesUseCase
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository
    ) {}

    public function execute(int $page = 1, int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        return $this->opportunityRepository->getAll($page, $perPage, $search);
    }
}