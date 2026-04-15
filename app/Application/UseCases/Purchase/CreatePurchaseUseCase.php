<?php
// app/Application/UseCases/Purchases/CreatePurchaseUseCase.php

namespace App\Application\UseCases\Purchase;

use App\Application\DTOs\Purchase\CreatePurchaseRequest;
use App\Domain\Entities\Purchase;
use App\Infrastructure\Repositories\PurchaseRepository;
use Illuminate\Support\Facades\DB;

class CreatePurchaseUseCase
{
    public function __construct(
        private PurchaseRepository $repository,
    ) {}

    public function execute(CreatePurchaseRequest $request): Purchase
    {
        $userId = $request->assigned_user_id;
        $now = now()->format('Y-m-d H:i:s');

        return DB::connection('vtiger')->transaction(function () use ($request, $userId, $now) {
            // 1. Generar ID
            $purchaseId = $this->generateNextId();

            // 2. Insertar vtiger_crmentity
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->insert([
                    'crmid' => $purchaseId,
                    'smcreatorid' => $userId,
                    'smownerid' => $userId,
                    'setype' => 'PurchaseOrder',
                    'description' => $request->description,
                    'label' => substr($request->subject, 0, 100),
                    'createdtime' => $now,
                    'modifiedtime' => $now,
                    'deleted' => 0,
                ]);

            // 3. Insertar vtiger_purchaseorder
            DB::connection('vtiger')
                ->table('vtiger_purchaseorder')
                ->insert([
                    'purchaseorderid' => $purchaseId,
                    'subject' => $request->subject,
                    'projectid' => $request->projectid,
                    'vendorid' => $request->vendorid,
                    'postatus' => $request->postatus,
                    'podate' => $request->podate,
                    'validtill' => $request->validtill,
                    'subtotal' => 0, // Se calcula después
                    'taxtotal' => 0,
                    'total' => 0,
                    'createdtime' => $now,
                    'modifiedtime' => $now,
                ]);

            // 4. Insertar ítems
            $this->insertItems($purchaseId, $request->items);

            // 5. Actualizar totales
            $this->updateTotals($purchaseId);

            return $this->repository->findById($purchaseId);
        });
    }

    private function generateNextId(): int
    {
        return DB::connection('vtiger')
            ->table('vtiger_purchaseorder')
            ->max('purchaseorderid') + 1;
    }

    private function insertItems(int $purchaseId, array $items): void
    {
        foreach ($items as $index => $item) {
            DB::connection('vtiger')
                ->table('vtiger_inventoryproductrel')
                ->insert([
                    'id' => $purchaseId,
                    'productid' => $item['productid'] ?? null,
                    'sequence_no' => $index + 1,
                    'productname' => $item['productname'],
                    'quantity' => $item['quantity'],
                    'listprice' => $item['listprice'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'description' => $item['description'] ?? null,
                    'total' => $item['quantity'] * $item['listprice'] * (1 - ($item['discount_percent'] ?? 0) / 100),
                ]);
        }
    }

    private function updateTotals(int $purchaseId): void
    {
        $totals = DB::connection('vtiger')
            ->table('vtiger_inventoryproductrel')
            ->where('id', $purchaseId)
            ->selectRaw('SUM(total) as subtotal, SUM(total) * 0.07 as taxtotal, SUM(total) * 1.07 as total')
            ->first();

        DB::connection('vtiger')
            ->table('vtiger_purchaseorder')
            ->where('purchaseorderid', $purchaseId)
            ->update([
                'subtotal' => $totals->subtotal ?? 0,
                'taxtotal' => $totals->taxtotal ?? 0,
                'total' => $totals->total ?? 0,
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
            ]);
    }
}