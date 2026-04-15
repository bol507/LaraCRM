<?php
// app/Application/UseCases/Purchases/GetProjectPurchasesUseCase.php

namespace App\Application\UseCases\Purchase;

use App\Infrastructure\Repositories\PurchaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class GetProjectPurchasesUseCase
{
    public function __construct(
        private PurchaseRepository $repository,
    ) {}

    /**
     * Execute the get project purchases use case.
     *
     * @param int $projectId Project ID
     * @param int $page Page number
     * @param int $limit Items per page
     * @param string|null $status Filter by status
     * @return LengthAwarePaginator Paginated purchase list for project
     *
     * @throws InvalidArgumentException If projectId is invalid
     */
    public function execute(
        int $projectId,
        int $page = 1,
        int $limit = 50,
        ?string $status = null
    ): LengthAwarePaginator {
        // 1. Validate parameters
        if ($projectId <= 0) {
            throw new InvalidArgumentException('Project ID must be a positive integer');
        }

        // 2. Normalize parameters
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));

        // 3. Retrieve purchases from repository
        return $this->repository->getAll(
            page: $page,
            perPage: $limit,
            search: null,
            status: $status,
            projectId: $projectId,
            sortBy: 'createdtime',
            sortOrder: 'DESC'
        );
    }
}