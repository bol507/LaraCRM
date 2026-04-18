<?php
// app/Application/UseCases/Vendors/DeleteVendorUseCase.php

namespace App\Application\UseCases\Vendor;

use App\Application\Repositories\VendorRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DeleteVendorUseCase
{
    public function __construct(private VendorRepositoryInterface $repository) {}

    public function execute(int $vendorId): bool
    {
        if ($vendorId <= 0) throw new InvalidArgumentException('Invalid vendor ID');
        if (!$this->repository->findById($vendorId)) throw new RuntimeException("Vendor {$vendorId} not found");

        return DB::connection('vtiger')->transaction(function () use ($vendorId) {
            DB::connection('vtiger')->table('vtiger_crmentity')
                ->where('crmid', $vendorId)
                ->update(['deleted' => 1, 'modifiedtime' => now()->format('Y-m-d H:i:s')]);
            return true;
        });
    }
}