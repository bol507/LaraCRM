<?php
// app/Infrastructure/Repositories/PurchaseOrderRepository.php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\Procurement\ListPurchaseOrdersRequestDto;
use App\Application\Repositories\PurchaseOrderRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const TABLE_PO = 'purchase_orders';
    protected const TABLE_ITEMS = 'purchase_order_items';

    public function findById(int $poId, int $projectId): ?array
    {
        $po = DB::connection(self::CONNECTION)
            ->table(self::TABLE_PO . ' as po')
            ->leftJoin('vtiger_vendor as v', 'po.vendor_id', '=', 'v.vendorid')
            ->where('po.id', $poId)
            ->where('po.project_id', $projectId) 
            ->select(
                'po.*',
                'v.vendorname as vendor_name'
            )
            ->first();

        if (!$po) {
            return null;
        }

        // 2. Cargar ítems de la orden
        $items = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->where('po_id', $poId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();

        // 3. Estructurar respuesta
        $po->items = $items;
        return (array) $po;
    }

    public function findByPoNumber(string $poNumber): ?object
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_PO)->where('po_number', $poNumber)->first();
    }

    public function findByProjectId(ListPurchaseOrdersRequestDto $dto): array
    {
        $offset = ($dto->page - 1) * $dto->limit;


        $query = DB::connection(self::CONNECTION)
            ->table(self::TABLE_PO . ' as po')
            ->leftJoin('vtiger_vendor as v', 'po.vendor_id', '=', 'v.vendorid')
            ->where('po.project_id', $dto->projectId)
            ->select(
                'po.*',
                'v.vendorname as vendor_name'
            );


        if ($dto->status !== null) {
            $query->where('po.status', $dto->status);
        }
        if ($dto->vendorId !== null) {
            $query->where('po.vendor_id', $dto->vendorId);
        }


        $total = $query->count();

        $items = $query->orderByDesc('po.created_at')
            ->limit($dto->limit)
            ->offset($offset)
            ->get()
            ->toArray(); // Convertir a array nativo

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $dto->limit,
                'current_page' => $dto->page,
                'last_page' => ceil($total / max(1, $dto->limit)),
            ]
        ];
    }

    public function findByStatus(string $status): Collection
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_PO)->where('status', $status)->orderByDesc('created_at')->get();
    }

    public function addLine(int $poId, array $lineData): int
    {
        return (int) DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->insertGetId([

                'source_request_item_id' => $lineData['source_request_item_id'] ?? null,

                // Datos de la línea de compra
                'po_id' => $poId,
                'item_name' => $lineData['item_name'],
                'description' => $lineData['description'] ?? null,
                'quantity' => (float) $lineData['quantity'],
                'unit' => $lineData['unit'],
                'unit_price' => isset($lineData['unit_price']) ? (float) $lineData['unit_price'] : null,
                'total_price' => isset($lineData['quantity'], $lineData['unit_price'])
                    ? (float) $lineData['quantity'] * (float) $lineData['unit_price']
                    : null,


                'status' => $lineData['status'] ?? 'pending',

                // Timestamps
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function findByIdWithLines(int $poId): ?array
    {
        $po = DB::connection(self::CONNECTION)
            ->table(self::TABLE_PO)
            ->where('id', $poId)
            ->first();

        if (!$po) return null;

        $lines = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->where('po_id', $poId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();

        $po->lines = $lines;
        return (array) $po;
    }

    public function create(array $data): int
    {
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                if (empty($data['po_number'])) {
                    $year = now()->year;
                    $prefix = "OC-{$year}-";


                    $lastPo = DB::connection(self::CONNECTION)
                        ->table('vtiger_purchase_orders') // ← Ajustar a tu nombre real de tabla
                        ->where('po_number', 'like', "{$prefix}%")
                        ->orderBy('po_number', 'desc')
                        ->value('po_number');

                    // Extraer secuencia: "OC-2026-0042" → 42 → siguiente: 43
                    $lastSequence = $lastPo ? (int) substr($lastPo, strlen($prefix)) : 0;
                    $nextSequence = str_pad($lastSequence + 1, 4, '0', STR_PAD_LEFT);

                    $data['po_number'] = "{$prefix}{$nextSequence}";
                }

                $id = DB::connection(self::CONNECTION)
                    ->table(self::TABLE_PO)
                    ->insertGetId([
                        'po_number' => $data['po_number'],
                        'project_id' => $data['project_id'],
                        'vendor_id' => $data['vendor_id'],
                        'status' => $data['status'] ?? 'draft',
                        'created_by' => $data['created_by'],
                        'order_date' => $data['order_date'] ?? now(),
                        'expected_delivery' => $data['expected_delivery'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                return (int) $id;
            } catch (\Illuminate\Database\QueryException $e) {
                // Si es error de duplicate entry en po_number, reintentar
                if ($e->errorInfo[1] === 1062 && $attempt < $maxRetries - 1) { // 1062 = Duplicate entry en MySQL
                    $attempt++;
                    unset($data['po_number']); // Forzar regeneración
                    usleep(100000); // Esperar 100ms antes de reintentar
                    continue;
                }
                throw $e; // Otros errores, propagar
            }
        }
        throw new \RuntimeException('Failed to generate unique po_number after multiple attempts');
    }

    public function addItem(int $poId, array $itemData): int
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)->insertGetId([
            'po_id' => $poId,
            'source_request_item_id' => $itemData['source_request_item_id'] ?? null,
            'item_name' => $itemData['item_name'],
            'quantity_ordered' => $itemData['quantity_ordered'],
            'quantity_received' => 0,
            'unit_cost' => $itemData['unit_cost'],
            'total_cost' => $itemData['quantity_ordered'] * $itemData['unit_cost'],
            'vendor_notes' => $itemData['vendor_notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateStatus(int $id, string $status, ?int $approvedBy = null): bool
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_PO)
            ->where('id', $id)
            ->update([
                'status' => $status,
                'approved_by' => $approvedBy,
                'updated_at' => now(),
            ]) > 0;
    }

    public function recordReception(int $itemId, float $receivedQty): bool
    {
        if ($receivedQty <= 0) throw new InvalidArgumentException('Reception quantity must be positive');

        return DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)
            ->where('id', $itemId)
            ->update([
                'quantity_received' => DB::raw("quantity_received + {$receivedQty}"),
                'updated_at' => now(),
            ]) > 0;
    }

    public function getItemsByPoId(int $poId): Collection
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)
            ->where('po_id', $poId)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function calculateTotal(int $poId): float
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)
            ->where('po_id', $poId)
            ->sum('total_cost');
    }

    public function updateTotal(int $poId, float $totalAmount): bool
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_PO)
            ->where('id', $poId)
            ->update([
                'total_amount' => $totalAmount,
                'updated_at' => now(),
            ]) > 0;
    }
}
