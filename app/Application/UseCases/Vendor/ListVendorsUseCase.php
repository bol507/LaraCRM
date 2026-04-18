<?php
// app/Application/UseCases/Vendors/ListVendorsUseCase.php

namespace App\Application\UseCases\Vendor;

use App\Application\Repositories\VendorRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ListVendorsUseCase
{
    public function __construct(private VendorRepositoryInterface $repository) {}

    public function execute(
        int $page = 1,
        int $limit = 10,
        ?string $search = null,
        ?string $category = null,
        string $sortBy = 'createdtime',
        string $sortOrder = 'DESC'
    ): LengthAwarePaginator {
        return $this->repository->getAll($page, $limit, $search, $category, $sortBy, $sortOrder);
    }
}