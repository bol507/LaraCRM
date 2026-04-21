<?php

namespace App\Application\Repositories;

use App\Domain\Entities\Purchase;
use Illuminate\Pagination\LengthAwarePaginator;

interface PurchaseRepositoryInterface
{
    public function getAll(
        int $page = 1,
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        ?int $projectId = null,
        ?string $sortBy = 'createdtime',
        ?string $sortOrder = 'DESC'
    ): LengthAwarePaginator ;

    public function findById(int $purchaseId): ?Purchase;

    
}