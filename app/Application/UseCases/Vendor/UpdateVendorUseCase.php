<?php
// app/Application/UseCases/Vendors/UpdateVendorUseCase.php

namespace App\Application\UseCases\Vendor;

use App\Application\DTOs\Vendor\UpdateVendorRequest;
use App\Application\Repositories\VendorRepositoryInterface;
use App\Domain\Entities\Vendor;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class UpdateVendorUseCase
{
    public function __construct(private VendorRepositoryInterface $repository) {}

    public function execute(int $vendorId, UpdateVendorRequest $request): Vendor
    {
        if ($vendorId <= 0) throw new InvalidArgumentException('Invalid vendor ID');
        if (!$this->repository->findById($vendorId)) throw new RuntimeException("Vendor {$vendorId} not found");

         return DB::connection('vtiger')->transaction(function () use ($vendorId, $request) {
            $now = now()->format('Y-m-d H:i:s');
            $entityUpdates = [];
            $vendorUpdates = [];

            if ($request->vendorname !== null) {
                $entityUpdates['label'] = substr($request->vendorname, 0, 100);
                $vendorUpdates['vendorname'] = $request->vendorname;
            }
            if ($request->email !== null) $vendorUpdates['email'] = $request->email;
            if ($request->phone !== null) $vendorUpdates['phone'] = $request->phone;
            if ($request->category !== null) $vendorUpdates['category'] = $request->category;
            if ($request->website !== null) $vendorUpdates['website'] = $request->website;
            if ($request->street !== null) $vendorUpdates['street'] = $request->street;
            if ($request->city !== null) $vendorUpdates['city'] = $request->city;
            if ($request->state !== null) $vendorUpdates['state'] = $request->state;
            if ($request->postalcode !== null) $vendorUpdates['postalcode'] = $request->postalcode; 
            if ($request->country !== null) $vendorUpdates['country'] = $request->country;
            if ($request->description !== null) {
                $entityUpdates['description'] = $request->description;
                $vendorUpdates['description'] = $request->description;
            }
            if ($request->assigned_user_id !== null) $entityUpdates['smownerid'] = $request->assigned_user_id;

            $entityUpdates['modifiedtime'] = $now;
            if (!empty($entityUpdates)) DB::connection('vtiger')->table('vtiger_crmentity')->where('crmid', $vendorId)->update($entityUpdates);
            if (!empty($vendorUpdates)) DB::connection('vtiger')->table('vtiger_vendor')->where('vendorid', $vendorId)->update($vendorUpdates);

            return $this->repository->findById($vendorId);
        });
    }
}