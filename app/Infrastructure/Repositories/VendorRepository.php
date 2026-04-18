<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\VendorRepositoryInterface;
use App\Domain\Entities\Vendor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VendorRepository implements   VendorRepositoryInterface
{
    public function getAll(
        int $page = 1,
        int $perPage = 10,
        ?string $search = null,
        ?string $category = null,
        ?string $sortBy = 'createdtime',
        ?string $sortOrder = 'DESC'
    ): LengthAwarePaginator {
        $query = DB::connection('vtiger')
            ->table('vtiger_vendor')
            ->join('vtiger_crmentity', 'vtiger_vendor.vendorid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->select(
                'vtiger_vendor.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name")
            )
            ->where('vtiger_crmentity.deleted', 0);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_vendor.vendorname', 'LIKE', "%{$search}%")
                  ->orWhere('vtiger_vendor.email', 'LIKE', "%{$search}%")
                  ->orWhere('vtiger_vendor.phone', 'LIKE', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('vtiger_vendor.category', $category);
        }

        $allowedSorts = ['createdtime', 'modifiedtime', 'vendorname', 'category'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) ? "vtiger_crmentity.{$sortBy}" : 'vtiger_crmentity.createdtime';
        $query->orderBy($sortColumn, $sortOrder);

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $vendors = $items->map(fn($row) => $this->mapToEntity($row));

        return new LengthAwarePaginator($vendors, $total, $perPage, $page);
    }

    public function findById(int $vendorId): ?Vendor
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_vendor')
            ->join('vtiger_crmentity', 'vtiger_vendor.vendorid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->select(
                'vtiger_vendor.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name")
            )
            ->where('vtiger_vendor.vendorid', $vendorId)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        return $row ? $this->mapToEntity($row) : null;
    }

    public function search(string $term, int $limit = 10): array
    {
        return DB::connection('vtiger')
            ->table('vtiger_vendor')
            ->join('vtiger_crmentity', 'vtiger_vendor.vendorid', '=', 'vtiger_crmentity.crmid')
            ->select('vtiger_vendor.vendorid as id', 'vtiger_vendor.vendorname', 'vtiger_vendor.email', 'vtiger_vendor.phone', 'vtiger_vendor.category')
            ->where('vtiger_crmentity.deleted', 0)
            ->where(function ($q) use ($term) {
                $q->where('vtiger_vendor.vendorname', 'LIKE', "%{$term}%")
                  ->orWhere('vtiger_vendor.email', 'LIKE', "%{$term}%");
            })
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function mapToEntity($row): Vendor
    {
        return new Vendor(
            vendorid: (int) $row->vendorid,
            vendorname: $row->vendorname ?? '',
            email: $row->email ?? null,
            phone: $row->phone ?? null,
            category: $row->category ?? null,
            website: $row->website ?? null,
            street: $row->street ?? null,
            city: $row->city ?? null,
            state: $row->state ?? null,
            code: $row->code ?? null,
            country: $row->country ?? null,
            description: $row->description ?? null,
            assigned_user_id: (int) ($row->smownerid ?? 1),
            assigned_user_name: $row->assigned_user_name ?? null,
            createdtime: $row->createdtime ?? now()->format('Y-m-d H:i:s'),
            modifiedtime: $row->modifiedtime ?? null,
        );
    }
}