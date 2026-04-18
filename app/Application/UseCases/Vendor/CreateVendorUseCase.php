<?php
// app/Application/UseCases/Vendors/CreateVendorUseCase.php

namespace App\Application\UseCases\Vendor;

use App\Application\DTOs\Vendor\CreateVendorRequest;
use App\Application\Repositories\VendorRepositoryInterface;
use App\Domain\Entities\Vendor;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateVendorUseCase
{
    public function __construct(private VendorRepositoryInterface $repository) {}

    public function execute(CreateVendorRequest $request): Vendor
    {
        if (trim($request->vendorname) === '') {
            throw new InvalidArgumentException('Vendor name is required');
        }

        return DB::connection('vtiger')->transaction(function () use ($request) {
            $vendorId = $this->generateNextId();
            $now = now()->format('Y-m-d H:i:s');

            DB::connection('vtiger')->table('vtiger_crmentity')->insert([
                'crmid' => $vendorId,
                'smcreatorid' => $request->assigned_user_id,
                'smownerid' => $request->assigned_user_id,
                'setype' => 'Vendors',
                'description' => $request->description,
                'label' => substr($request->vendorname, 0, 100),
                'createdtime' => $now,
                'modifiedtime' => $now,
                'deleted' => 0,
            ]);

            DB::connection('vtiger')->table('vtiger_vendor')->insert([
                'vendorid' => $vendorId,
                'vendor_no' => 'VEN-' . date('Y') . str_pad($vendorId, 4, '0', STR_PAD_LEFT), 
                'vendorname' => $request->vendorname,
                'email' => $request->email,
                'phone' => $request->phone,
                'category' => $request->category,
                'website' => $request->website,
                'street' => $request->street,
                'city' => $request->city,
                'state' => $request->state,
                'pobox' => null,
                'postalcode' => $request->postalcode, 
                'country' => $request->country,
                'description' => $request->description,
                'tags' => null,
            ]);

            return $this->repository->findById($vendorId);
        });
    }

    private function generateNextId(): int
    {
        $maxId = DB::connection('vtiger')->table('vtiger_crmentity')->max('crmid');
        return ($maxId ? (int) $maxId : 0) + 1;
    }
}