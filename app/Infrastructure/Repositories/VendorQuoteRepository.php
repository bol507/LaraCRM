<?php
// app/Infrastructure/Repositories/Procurement/VendorQuoteRepository.php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\VendorQuoteRepositoryInterface;
use Illuminate\Support\Facades\DB;

class VendorQuoteRepository implements VendorQuoteRepositoryInterface
{
    protected const TABLE_QUOTES = 'nova_vendor_quotes';
    protected const TABLE_ITEMS = 'nova_vendor_quote_items';
    protected const CONNECTION = 'vtiger';

    public function create(array $data): int
    {
        return (int) DB::connection(self::CONNECTION)->table(self::TABLE_QUOTES)->insertGetId($data);
    }

    public function createItem(int $quoteId, array $data): void
    {
        DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)->insert([
            'vendor_quote_id' => $quoteId,
            'material_request_item_id' => $data['material_request_item_id'],
            'item_name' => $data['item_name'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $data['unit_price'],
            'delivery_date' => $data['delivery_date'] ?? null,
            'discount_percent' => $data['discount'] ?? 0,
            'terms' => $data['terms'] ?? null,
            'notes' => $data['notes'] ?? null,
            'line_total' => ($data['quantity'] ?? 1) * $data['unit_price'] * (1 - (($data['discount_percent'] ?? 0) / 100)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recalculateTotalAndUpdateQuote($quoteId);
    }

    public function findById(int $id): ?array
    {

        $quote = DB::connection(self::CONNECTION)
            ->table(self::TABLE_QUOTES . ' as q')
            ->leftJoin('vtiger_vendor as v', 'q.vendor_id', '=', 'v.vendorid')
            ->where('q.id', $id)
            ->select(
                'q.*',
                'v.vendorname as vendor_name'
            )
            ->first();

        if (!$quote) return null;


        $quote->items = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->where('vendor_quote_id', $id)
            ->get()
            ->toArray();

        return (array) $quote;
    }

    public function findByProject(int $projectId, array $filters, int $limit, int $page): array
    {
        $offset = ($page - 1) * $limit;

        $query = DB::connection(self::CONNECTION)
            ->table(self::TABLE_QUOTES . ' as q')
            ->leftJoin('vtiger_vendor as v', 'q.vendor_id', '=', 'v.vendorid')
            ->where('q.project_id', $projectId)
            ->select(
                'q.*',
                'v.vendorname as vendor_name'
            );


        if (!empty($filters['status'])) {
            $query->where('q.status', $filters['status']);
        }
        if (!empty($filters['vendor_id'])) {
            $query->where('q.vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['material_request_id'])) {
            $query->where('q.material_request_id', $filters['material_request_id']);
        }

        $total = (clone $query)->count();

        $items = $query
            ->orderByDesc('q.created_at')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();

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
        return DB::connection(self::CONNECTION)->table(self::TABLE_QUOTES)
            ->where('id', $id)->update(['status' => $status, 'updated_at' => now()]) > 0;
    }

    public function recalculateTotal(int $quoteId): float
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)
            ->where('vendor_quote_id', $quoteId)->sum('line_total');
    }

    public function recalculateTotalAndUpdateQuote(int $quoteId): float
    {
        $total = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->where('vendor_quote_id', $quoteId)
            ->sum('line_total') ?? 0;

        // Actualizar la cotización con el nuevo total
        DB::connection(self::CONNECTION)
            ->table(self::TABLE_QUOTES)
            ->where('id', $quoteId)
            ->update([
                'total_amount' => $total,
                'updated_at' => now()
            ]);

        return $total;
    }

    public function updateTermsAndNotes(int $id, ?string $notes, ?string $terms): bool
    {
        $update = [];
        if ($notes !== null) $update['notes'] = $notes;
        if ($terms !== null) $update['terms'] = $terms;
        if (empty($update)) return true;

        $update['updated_at'] = now();
        return DB::connection(self::CONNECTION)->table(self::TABLE_QUOTES)->where('id', $id)->update($update) > 0;
    }

    public function updateItem(int $itemId, array $data): bool
    {
        $quoteId = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->where('id', $itemId)
            ->value('vendor_quote_id');

        $result = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->where('id', $itemId)
            ->update([
                'unit_price' => $data['unit_price'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'discount_percent' => $data['discount'] ?? 0,
                'delivery_date' => $data['delivery_date'] ?? null,
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'line_total' => ($data['unit_price'] ?? 0) * (1 - ($data['discount'] ?? 0) / 100) * ($data['quantity'] ?? 1),
                'updated_at' => now(),
            ]) > 0;

        // ✅ Recalcular total si el item se actualizó
        if ($result && $quoteId) {
            $this->recalculateTotalAndUpdateQuote((int) $quoteId);
        }

        return $result;
    }

    public function deleteItem(int $itemId): bool
    {
        $quoteId = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->where('id', $itemId)
            ->value('vendor_quote_id');

        $result = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->where('id', $itemId)
            ->delete() > 0;

        // ✅ Recalcular total si el item se eliminó
        if ($result && $quoteId) {
            $this->recalculateTotalAndUpdateQuote((int) $quoteId);
        }

        return $result;
    }

    public function acceptQuote(int $id, int $acceptedBy, ?string $notes): bool
    {
        $update = [
            'status' => 'accepted',
            'accepted_by' => $acceptedBy,
            'accepted_at' => now(),
            'updated_at' => now(),
        ];

        // Append approval note without deleting existing notes
        if (!empty($notes)) {
            $update['notes'] = DB::raw("CONCAT(IFNULL(notes, ''), '\n\n[Approved by user #{$acceptedBy}] ' . '{$notes}')");
        }

        return DB::connection(self::CONNECTION)
            ->table(self::TABLE_QUOTES)
            ->where('id', $id)
            ->update($update) > 0;
    }
}
