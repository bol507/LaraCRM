<?php

namespace App\Application\UseCases\Purchase;

use App\Application\DTOs\Purchase\CreatePurchaseRequest;
use App\Domain\Entities\Purchase;
use App\Infrastructure\Repositories\PurchaseRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class UpdatePurchaseUseCase
{
    public function __construct(
        private PurchaseRepository $repository,
    ) {}

    /**
     * Execute the update purchase use case.
     *
     * @param int $purchaseId Purchase order ID to update
     * @param CreatePurchaseRequest $request Updated purchase data
     * @return Purchase Updated purchase entity
     *
     * @throws InvalidArgumentException If parameters are invalid
     * @throws RuntimeException If purchase not found
     */
    public function execute(int $purchaseId, CreatePurchaseRequest $request): Purchase
    {
        // 1. Validate parameters
        $this->validateParameters($purchaseId, $request);

        // 2. Check if purchase exists
        $existingPurchase = $this->repository->findById($purchaseId);
        if (!$existingPurchase) {
            throw new RuntimeException("Purchase order {$purchaseId} not found or has been deleted");
        }

        // 3. Update in transaction
        return DB::connection('vtiger')->transaction(function () use ($purchaseId, $request, $existingPurchase) {
            $now = now()->format('Y-m-d H:i:s');

            // 4. Update vtiger_crmentity
            DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $purchaseId)
                ->update([
                    'label' => substr(trim($request->subject), 0, 100),
                    'description' => $request->description,
                    'smownerid' => $request->assigned_user_id,
                    'modifiedtime' => $now,
                ]);

            // 5. Update vtiger_purchaseorder
            DB::connection('vtiger')
                ->table('vtiger_purchaseorder')
                ->where('purchaseorderid', $purchaseId)
                ->update([
                    'subject' => $request->subject,
                    'projectid' => $request->projectid,
                    'vendorid' => $request->vendorid,
                    'postatus' => $request->postatus,
                    'podate' => $request->podate,
                    'validtill' => $request->validtill,
                    'modifiedtime' => $now,
                    // Totals will be recalculated after items update
                ]);

            // 6. Delete existing items
            DB::connection('vtiger')
                ->table('vtiger_inventoryproductrel')
                ->where('id', $purchaseId)
                ->delete();

            // 7. Insert new items
            $this->insertItems($purchaseId, $request->items);

            // 8. Recalculate totals
            $this->updateTotals($purchaseId);

            // 9. Return updated purchase
            return $this->repository->findById($purchaseId);
        });
    }

    /**
     * Validate input parameters.
     *
     * @param int $purchaseId Purchase order ID
     * @param CreatePurchaseRequest $request Purchase data
     * @throws InvalidArgumentException If parameters are invalid
     */
    private function validateParameters(int $purchaseId, CreatePurchaseRequest $request): void
    {
        if ($purchaseId <= 0) {
            throw new InvalidArgumentException('Purchase order ID must be a positive integer');
        }

        if (trim($request->subject) === '') {
            throw new InvalidArgumentException('Subject is required');
        }

        if ($request->projectid <= 0) {
            throw new InvalidArgumentException('Project is required');
        }

        if ($request->vendorid <= 0) {
            throw new InvalidArgumentException('Vendor is required');
        }

        if (!in_array($request->postatus, ['Draft', 'Pending Approval', 'Approved', 'Received', 'Cancelled'], true)) {
            throw new InvalidArgumentException('Invalid status');
        }

        if (empty($request->items) || !is_array($request->items)) {
            throw new InvalidArgumentException('At least one item is required');
        }

        foreach ($request->items as $index => $item) {
            if (empty($item['productname'])) {
                throw new InvalidArgumentException("Item #" . ($index + 1) . " product name is required");
            }
            if (!isset($item['quantity']) || $item['quantity'] <= 0) {
                throw new InvalidArgumentException("Item #" . ($index + 1) . " quantity must be positive");
            }
            if (!isset($item['listprice']) || $item['listprice'] < 0) {
                throw new InvalidArgumentException("Item #" . ($index + 1) . " price must be non-negative");
            }
        }
    }

    /**
     * Insert purchase items.
     *
     * @param int $purchaseId Purchase order ID
     * @param array $items Items to insert
     */
    private function insertItems(int $purchaseId, array $items): void
    {
        foreach ($items as $index => $item) {
            $total = $item['quantity'] * $item['listprice'] * (1 - ($item['discount_percent'] ?? 0) / 100);

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
                    'total' => $total,
                ]);
        }
    }

    /**
     * Update purchase totals.
     *
     * @param int $purchaseId Purchase order ID
     */
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