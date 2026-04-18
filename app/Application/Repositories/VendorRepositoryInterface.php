<?php

namespace App\Application\Repositories;

use App\Domain\Entities\Vendor;
use Illuminate\Pagination\LengthAwarePaginator;

interface VendorRepositoryInterface
{
    public function getAll(
        int $page = 1,
        int $perPage = 10,
        ?string $search = null,
        ?string $category = null,
        ?string $sortBy = 'createdtime',
        ?string $sortOrder = 'DESC'
    ): LengthAwarePaginator;

    public function findById(int $vendorId): ?Vendor;

    public function search(string $term, int $limit = 10): array;

    
}