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

    public function create(array $data): int {
        return (int) DB::connection(self::CONNECTION)->table(self::TABLE_QUOTES)->insertGetId($data);
    }

    public function createItem(int $quoteId, array $data): void {
        DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)->insert([
            'vendor_quote_id' => $quoteId,
            'material_request_item_id' => $data['material_request_item_id'],
            'unit_price' => $data['unit_price'],
            'delivery_date' => $data['delivery_date'] ?? null,
            'discount_percent' => $data['discount'] ?? 0,
            'terms' => $data['terms'] ?? null,
            'notes' => $data['notes'] ?? null,
            'line_total' => $data['unit_price'] * (1 - ($data['discount'] ?? 0) / 100) * ($data['quantity'] ?? 1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findById(int $id): ?array {
        $quote = DB::connection(self::CONNECTION)->table(self::TABLE_QUOTES)->find($id);
        if (!$quote) return null;

        $quote->items = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)->where('vendor_quote_id', $id)->get()->toArray();
        
        return (array) $quote;
    }

    public function findByProject(int $projectId, array $filters = []): array {
        $query = DB::connection(self::CONNECTION)->table(self::TABLE_QUOTES)->where('project_id', $projectId);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['vendor_id'])) $query->where('vendor_id', $filters['vendor_id']);

        return $query->orderByDesc('created_at')->get()->toArray();
    }

    public function updateStatus(int $id, string $status): bool {
        return DB::connection(self::CONNECTION)->table(self::TABLE_QUOTES)
            ->where('id', $id)->update(['status' => $status, 'updated_at' => now()]) > 0;
    }

    public function recalculateTotal(int $quoteId): float {
        return DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)
            ->where('vendor_quote_id', $quoteId)->sum('line_total');
    }
}