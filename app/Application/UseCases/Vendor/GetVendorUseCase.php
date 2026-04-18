<?php
// app/Application/UseCases/Vendors/GetVendorUseCase.php

namespace App\Application\UseCases\Vendor;

use App\Application\Repositories\VendorRepositoryInterface;
use App\Domain\Entities\Vendor;

use InvalidArgumentException;
use RuntimeException;

class GetVendorUseCase
{
    public function __construct(private VendorRepositoryInterface $repository) {}

    public function execute(int $vendorId): Vendor
    {
        if ($vendorId <= 0) throw new InvalidArgumentException('Invalid vendor ID');

        $vendor = $this->repository->findById($vendorId);
        if (!$vendor) throw new RuntimeException("Vendor {$vendorId} not found");

        return $vendor;
    }
}