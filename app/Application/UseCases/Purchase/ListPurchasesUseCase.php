<?php
// app/Application/UseCases/Purchases/ListPurchasesUseCase.php

namespace App\Application\UseCases\Purchase;

use App\Infrastructure\Repositories\PurchaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Domain\Entities\Purchase;

class ListPurchasesUseCase
{
    public function __construct(
        private PurchaseRepository $repository,
    ) {}

    /**
     * Execute the list purchases use case.
     *
     * @param int $page Page number (1-based)
     * @param int $limit Items per page
     * @param string|null $search Search term
     * @param string|null $status Filter by status
     * @param int|null $projectId Filter by project ID
     * @param string|null $sortBy Sort column
     * @param string|null $sortOrder Sort order (ASC/DESC)
     * @return LengthAwarePaginator Paginated purchase list
     */
    public function execute(
        int $page = 1,
        int $limit = 10,
        ?string $search = null,
        ?string $status = null,
        ?int $projectId = null,
        ?string $sortBy = 'createdtime',
        ?string $sortOrder = 'DESC'
    ): LengthAwarePaginator {
        // 1. Validate and normalize parameters
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        // 2. Retrieve purchases from repository
        return $this->repository->getAll(
            page: $page,
            perPage: $limit,
            search: $search,
            status: $status,
            projectId: $projectId,
            sortBy: $sortBy,
            sortOrder: $sortOrder
        );
    }
}