<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\PurchaseRepositoryInterface;
use App\Domain\Entities\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PurchaseRepository implements PurchaseRepositoryInterface
{
    public function getAll(
        int $page = 1,
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        ?int $projectId = null,
        ?string $sortBy = 'createdtime',
        ?string $sortOrder = 'DESC'
    ): LengthAwarePaginator {
        $query = DB::connection('vtiger')
            ->table('vtiger_purchaseorder')
            ->join('vtiger_crmentity', 'vtiger_purchaseorder.purchaseorderid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_project', 'vtiger_purchaseorder.projectid', '=', 'vtiger_project.projectid')
            ->leftJoin('vtiger_vendor', 'vtiger_purchaseorder.vendorid', '=', 'vtiger_vendor.vendorid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->select(
                'vtiger_purchaseorder.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_project.projectname',
                'vtiger_project.targetbudget as project_budget',
                'vtiger_vendor.vendorname',
                DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name")
            )
            ->where('vtiger_crmentity.deleted', 0);

        if ($projectId) {
            $query->where('vtiger_purchaseorder.projectid', $projectId);
        }

        if ($status) {
            $query->where('vtiger_purchaseorder.postatus', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_purchaseorder.subject', 'LIKE', "%{$search}%")
                  ->orWhere('vtiger_purchaseorder.ponumber', 'LIKE', "%{$search}%")
                  ->orWhere('vtiger_project.projectname', 'LIKE', "%{$search}%");
            });
        }

        $allowedSorts = ['createdtime', 'modifiedtime', 'podate', 'total', 'subject'];
        $sortColumn = in_array($sortBy, $allowedSorts, true) 
            ? "vtiger_crmentity.{$sortBy}" 
            : 'vtiger_crmentity.createdtime';

        $query->orderBy($sortColumn, $sortOrder);

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $purchases = $items->map(fn($row) => $this->mapToEntity($row));

        return new LengthAwarePaginator($purchases, $total, $perPage, $page);
    }

    public function findById(int $purchaseId): ?Purchase
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_purchaseorder')
            ->join('vtiger_crmentity', 'vtiger_purchaseorder.purchaseorderid', '=', 'vtiger_crmentity.crmid')
            ->leftJoin('vtiger_project', 'vtiger_purchaseorder.projectid', '=', 'vtiger_project.projectid')
            ->leftJoin('vtiger_vendor', 'vtiger_purchaseorder.vendorid', '=', 'vtiger_vendor.vendorid')
            ->leftJoin('vtiger_users', 'vtiger_crmentity.smownerid', '=', 'vtiger_users.id')
            ->select(
                'vtiger_purchaseorder.*',
                'vtiger_crmentity.createdtime',
                'vtiger_crmentity.modifiedtime',
                'vtiger_project.projectname',
                'vtiger_project.targetbudget as project_budget',
                'vtiger_vendor.vendorname',
                DB::raw("CONCAT(vtiger_users.first_name, ' ', vtiger_users.last_name) as assigned_user_name")
            )
            ->where('vtiger_purchaseorder.purchaseorderid', $purchaseId)
            ->where('vtiger_crmentity.deleted', 0)
            ->first();

        if (!$row) return null;

        return $this->mapToEntity($row);
    }

    private function mapToEntity($row): Purchase
    {
        $items = DB::connection('vtiger')
            ->table('vtiger_inventoryproductrel')
            ->where('id', $row->purchaseorderid)
            ->orderBy('sequence_no')
            ->get()
            ->map(fn($item) => [
                'productid' => $item->productid ? (int) $item->productid : null,
                'sequence_no' => (int) $item->sequence_no,
                'productname' => $item->productname ?? '',
                'quantity' => (float) $item->quantity,
                'listprice' => (float) $item->listprice,
                'discount_percent' => (float) ($item->discount_percent ?? 0),
                'description' => $item->description ?? null,
                'total' => (float) ($item->total ?? 0),
            ])
            ->toArray();

        // Calcular gasto total del proyecto
        $projectSpent = DB::connection('vtiger')
            ->table('vtiger_purchaseorder')
            ->join('vtiger_crmentity', 'vtiger_purchaseorder.purchaseorderid', '=', 'vtiger_crmentity.crmid')
            ->where('vtiger_purchaseorder.projectid', $row->projectid)
            ->where('vtiger_purchaseorder.postatus', '!=', 'Cancelled')
            ->where('vtiger_crmentity.deleted', 0)
            ->sum('vtiger_purchaseorder.total');

        return new Purchase(
            purchaseorderid: (int) $row->purchaseorderid,
            subject: $row->subject ?? '',
            ponumber: $row->ponumber ?? '',
            projectid: (int) ($row->projectid ?? 0),
            projectname: $row->projectname ?? null,
            vendorid: (int) ($row->vendorid ?? 0),
            vendorname: $row->vendorname ?? null,
            postatus: $row->postatus ?? 'Draft',
            podate: $row->podate ?? null,
            validtill: $row->validtill ?? null,
            subtotal: (float) ($row->subtotal ?? 0),
            taxtotal: (float) ($row->taxtotal ?? 0),
            total: (float) ($row->total ?? 0),
            items: $items,
            description: $row->description ?? null,
            assigned_user_id: (int) ($row->smownerid ?? 1),
            assigned_user_name: $row->assigned_user_name ?? null,
            createdtime: $row->createdtime ?? null,
            modifiedtime: $row->modifiedtime ?? null,
            projectBudget: $row->project_budget ? (float) $row->project_budget : null,
            projectSpent: $projectSpent,
        );
    }
}