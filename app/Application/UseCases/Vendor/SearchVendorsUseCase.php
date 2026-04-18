<?php
// app/Application/UseCases/Vendors/SearchVendorsUseCase.php

namespace App\Application\UseCases\Vendor;

use App\Application\Repositories\VendorRepositoryInterface;

class SearchVendorsUseCase
{
    public function __construct(private VendorRepositoryInterface $repository) {}

    public function execute(string $term, int $limit = 10): array
    {
        if (strlen(trim($term)) < 2) return [];
        return $this->repository->search(trim($term), $limit);
    }
}