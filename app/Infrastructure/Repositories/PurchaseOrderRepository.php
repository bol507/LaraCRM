<?php
// app/Infrastructure/Repositories/PurchaseOrderRepository.php

namespace App\Infrastructure\Repositories;


use App\Application\Repositories\PurchaseOrderRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const TABLE_PO = 'nova_purchase_orders';
    protected const TABLE_PO_ITEMS = 'nova_purchase_order_items';

    public function create(array $data): int
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_PO)->insertGetId([
            'project_id' => $data['project_id'],
            'po_number' => $data['po_number'],
            'vendor_quote_id' => $data['vendor_quote_id'] ?? null,
            'material_request_id' => $data['material_request_id'] ?? null,
            'vendor_id' => $data['vendor_id'],
            'status' => $data['status'],
            'subtotal' => $data['subtotal'],
            'tax_amount' => $data['tax_amount'],
            'total_amount' => $data['total_amount'],
            'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
            'terms' => $data['terms'] ?? null,
            'notes' => $data['notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'created_by' => $data['created_by'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function createItem(int $poId, array $itemData): void
    {
        DB::connection(self::CONNECTION)->table(self::TABLE_PO_ITEMS)->insert([
            'purchase_order_id' => $poId,
            'vendor_quote_item_id' => $itemData['vendor_quote_item_id'] ?? null,
            'material_request_item_id' => $itemData['material_request_item_id'] ?? null,
            'item_name' => $itemData['item_name'],
            'catalog_item_type' => $itemData['catalog_item_type'] ?? null,
            'unit' => $itemData['unit'],
            'quantity' => $itemData['quantity'],
            'unit_price' => $itemData['unit_price'],
            'discount_percent' => $itemData['discount_percent'] ?? 0,
            'line_total' => $itemData['line_total'],
            'expected_delivery_date' => $itemData['expected_delivery_date'] ?? null,
            'terms' => $itemData['terms'] ?? null,
            'notes' => $itemData['notes'] ?? null,
            'received_quantity' => $itemData['received_quantity'] ?? 0,
            'receipt_status' => $itemData['receipt_status'] ?? 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findById(int $id): ?array
    {
        $po = DB::connection(self::CONNECTION)
            ->table(self::TABLE_PO . ' as po')
            ->leftJoin('vtiger_vendor as v', 'po.vendor_id', '=', 'v.vendorid')
            ->where('po.id', $id)
            ->select('po.*', 'v.vendorname as vendor_name')
            ->first();

        if (!$po) return null;

        $po->items = DB::connection(self::CONNECTION)
            ->table(self::TABLE_PO_ITEMS)
            ->where('purchase_order_id', $id)
            ->get()
            ->toArray();

        return (array) $po;
    }

    public function findByProject(int $projectId, array $filters, int $limit, int $page): array
    {
        $offset = ($page - 1) * $limit;
        $query = DB::connection(self::CONNECTION)
            ->table(self::TABLE_PO . ' as po')
            ->leftJoin('vtiger_vendor as v', 'po.vendor_id', '=', 'v.vendorid')
            ->where('po.project_id', $projectId)
            ->select('po.*', 'v.vendorname as vendor_name');

        if (!empty($filters['status'])) $query->where('po.status', $filters['status']);
        if (!empty($filters['vendor_id'])) $query->where('po.vendor_id', $filters['vendor_id']);

        $total = (clone $query)->count();
        $items = $query->orderByDesc('po.created_at')->limit($limit)->offset($offset)->get()->toArray();

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => ceil($total / max(1, $limit))
            ]
        ];
    }

    public function updateStatus(int $id, string $status): bool
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_PO)
            ->where('id', $id)
            ->update(['status' => $status, 'updated_at' => now()]) > 0;
    }

    public function updateReceiptStatus(int $itemId, string $status, float $receivedQty): bool
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_PO_ITEMS)
            ->where('id', $itemId)
            ->update([
                'received_quantity' => $receivedQty,
                'receipt_status' => $status,
                'updated_at' => now()
            ]) > 0;
    }
    public function recordItemReceipt(int $poId, int $itemId, array $data): array
    {
        return DB::connection(self::CONNECTION)->transaction(function () use ($poId, $itemId, $data) {

            // 1. Obtener ítem actual
            $item = DB::connection(self::CONNECTION)
                ->table(self::TABLE_PO_ITEMS)
                ->where('id', $itemId)
                ->where('purchase_order_id', $poId)
                ->lockForUpdate() // 🔒 Previne race conditions
                ->first();

            if (!$item) throw new \DomainException('Item not found');

            // 2. Validar que no se exceda la cantidad ordenada
            $currentReceived = (float) ($item->received_quantity ?? 0);
            $orderedQty = (float) $item->quantity;
            $newReceived = $currentReceived + $data['quantity_received'];

            if ($newReceived > $orderedQty) {
                throw new \DomainException(
                    sprintf(
                        'Cannot receive %.2f more. Ordered: %.2f, Already received: %.2f, Remaining: %.2f',
                        $data['quantity_received'],
                        $orderedQty,
                        $currentReceived,
                        $orderedQty - $currentReceived
                    )
                );
            }

            // 3. Determinar estado de recepción del ítem
            $receiptStatus = $newReceived >= $orderedQty ? 'complete' : 'partial';

            // 4. Actualizar ítem
            DB::connection(self::CONNECTION)
                ->table(self::TABLE_PO_ITEMS)
                ->where('id', $itemId)
                ->update([
                    'received_quantity' => $newReceived,
                    'receipt_status' => $receiptStatus,
                    'updated_at' => now(),
                ]);

            // 5. Registrar en tabla de historial de recepciones (auditoría)
            DB::connection(self::CONNECTION)->table('nova_po_receipts')->insert([
                'purchase_order_id' => $poId,
                'po_item_id' => $itemId,
                'quantity_received' => $data['quantity_received'],
                'received_date' => $data['received_date'],
                'received_by' => $data['received_by'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 6. Verificar y actualizar estado global de la PO
            $this->checkAndUpdatePOReceiptStatus($poId);

            return [
                'item_id' => $itemId,
                'previous_received' => $currentReceived,
                'new_received' => $newReceived,
                'ordered_quantity' => $orderedQty,
                'receipt_status' => $receiptStatus,
                'remaining' => $orderedQty - $newReceived,
            ];
        });
    }

    private function checkAndUpdatePOReceiptStatus(int $poId): void
    {
        $items = DB::connection(self::CONNECTION)
            ->table(self::TABLE_PO_ITEMS)
            ->where('purchase_order_id', $poId)
            ->get(['id', 'quantity', 'received_quantity', 'receipt_status'])
            ->toArray(); // ← array<stdClass>

        if (empty($items)) return;

        $totalItems = count($items);

        
        $completeItems = count(array_filter($items, fn($i) => $i->receipt_status === 'complete'));
        $anyReceived = $completeItems > 0 || count(array_filter($items, fn($i) => $i->receipt_status === 'partial')) > 0;

        $newStatus = null;
        if ($completeItems === $totalItems) {
            $newStatus = 'fully_received';
        } elseif ($anyReceived) {
            $newStatus = 'partially_received';
        }

        if ($newStatus) {
            DB::connection(self::CONNECTION)
                ->table(self::TABLE_PO)
                ->where('id', $poId)
                ->update(['status' => $newStatus, 'updated_at' => now()]);
        }
    }
}
