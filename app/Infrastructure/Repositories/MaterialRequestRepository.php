<?php
// app/Infrastructure/Repositories/Procurement/MaterialRequestRepository.php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\MaterialRequestRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MaterialRequestRepository implements MaterialRequestRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const TABLE_REQUESTS = 'nova_material_requests';
    protected const TABLE_ITEMS = 'nova_material_request_items';
    protected const TABLE_USERS = 'vtiger_users';
    protected const TABLE_PO_ITEMS = 'nova_purchase_order_items';

    public function findById(int $id): ?object
    {
        Log::debug('Repo findById', [
            'connection' => self::CONNECTION, // Should be 'vtiger'
            'table' => self::TABLE_REQUESTS,  // Should be 'material_requests' or 'vtiger_material_requests'
            'id' => $id,
        ]);
        if ($id <= 0) throw new InvalidArgumentException('Request ID must be positive');

        $result = DB::connection(self::CONNECTION)->table(self::TABLE_REQUESTS)
            ->where('id', $id)
            ->first();
        Log::debug('Repo findById result', ['found' => $result !== null]);
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function findByProjectId(int $projectId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::connection(self::CONNECTION)
            ->table(self::TABLE_REQUESTS . ' as r')
            ->leftJoin(self::TABLE_USERS . ' as u', 'r.requested_by', '=', 'u.id')
            ->where('r.project_id', $projectId)
            ->select('r.*', 'u.user_name as requested_by_name');

        if (!empty($filters['status'])) {
            $query->whereIn('r.status', (array) $filters['status']);
        }
        if (!empty($filters['requested_by'])) {
            $query->where('r.requested_by', $filters['requested_by']);
        }

        $total = $query->count();

        $requests = $query->orderByDesc('r.created_at')
            ->limit($limit)
            ->offset($offset)
            ->get();

        if ($requests->isEmpty()) {
            return [
                'data' => [],
                'total' => $total,
                'per_page' => $limit,
                'current_page' => ($offset / $limit) + 1,
                'last_page' => ceil($total / max(1, $limit)),
            ];
        }

        // Extract request IDs from the obtained requests
        $requestIds = $requests->pluck('id')->toArray();

        // Single query: Fetch ALL items for these requests
        $itemsByRequest = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->whereIn('request_id', $requestIds)
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('request_id'); // Automatically groups by request_id

        // Inject items into each request
        $requests->each(function ($request) use ($itemsByRequest) {
            // Assign the array of items or [] if none
            $request->items = $itemsByRequest->get($request->id, collect())->values()->toArray();
        });

        return [
            'data' => $requests->toArray(),
            'total' => $total,
            'per_page' => $limit,
            'current_page' => ($offset / $limit) + 1,
            'last_page' => ceil($total / max(1, $limit)),
        ];
    }

    public function findPendingBySupervisor(int $supervisorId): Collection
    {
        // Simple implementation: fetch all 'submitted' requests in projects the supervisor oversees
        // In a real scenario, you'd join project assignments or use a role hierarchy table
        return DB::connection(self::CONNECTION)->table(self::TABLE_REQUESTS)
            ->where('status', 'submitted')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function findByIdWithItems(int $requestId): ?array
    {
        $request = DB::connection(self::CONNECTION)
            ->table(self::TABLE_REQUESTS . ' as r')
            ->leftJoin('vtiger_users as u', 'r.requested_by', '=', 'u.id')
            ->leftJoin('vtiger_users as au', 'r.approved_by', '=', 'au.id')
            ->where('r.id', $requestId)
            ->select(
                'r.*',
                'u.user_name as requested_by_name',
                'u.email1 as requested_by_email',
                'au.user_name as approved_by_name'
            )
            ->first();

        if (!$request) return null;

        // Get items
        $items = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->where('request_id', $requestId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();

        // Merge and return
        $request->items = $items;
        return (array) $request;
    }

    public function findItemById(int $id): ?object
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)->where('id', $id)->first();
    }

    public function findItemsWithVendor(array $itemIds): array
    {
        return DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS . ' as mri')
            ->leftJoin('vtiger_products as p', 'mri.catalog_item_id', '=', 'p.id')
            ->whereIn('mri.id', $itemIds)
            ->select(
                'mri.*',
                'p.vendor_id' // Key: get vendor_id from product
            )
            ->get()
            ->toArray();
    }

    public function create(array $data): int
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_REQUESTS)->insertGetId([
            'project_id' => $data['project_id'],
            'requested_by' => $data['requested_by'],
            'status' => $data['status'] ?? 'draft',
            'submitted_at' => $data['submitted_at'] ?? null,
            'rejection_notes' => $data['rejection_notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function createItems(int $requestId, array $items): int
    {
        $insertData = array_map(fn($item) => [
            'request_id' => $requestId,
            'catalog_item_type' => $item['catalog_item_type'],
            'catalog_reason_type' => $item['catalog_reason_type'],
            'item_name' => $item['item_name'],
            'quantity' => $item['quantity'],
            'unit' => $item['unit'],
            'priority' => $item['priority'] ?? 'medium',
            'estimated_cost' => $item['estimated_cost'] ?? null,
            'notes' => $item['notes'] ?? null,
            'item_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
            'reason_other' => $item['reason_other'] ?? null,
        ], $items);

        return DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)->insert($insertData) ? count($insertData) : 0;
    }

    public function updateStatus(int $id, string $status, ?int $approvedBy = null, ?string $notes = null): bool
    {
        $updateData = [
            'status' => $status,
            'updated_at' => now(),
        ];

        switch ($status) {
            case 'approved':
            case 'partially_approved':
                $updateData['approved_by'] = $approvedBy;
                $updateData['approved_at'] = now();
                break;

            case 'rejected':
                $updateData['rejection_notes'] = $notes;
                // Optional: clear approval if rejected
                // $updateData['approved_by'] = null;
                // $updateData['approved_at'] = null;
                break;

            case 'procurement_in_progress':
            case 'fully_procured':
            case 'closed':
                // For workflow states, only update status and updated_at
                // Do not touch approved_by, approved_at, or rejection_notes
                break;
        }

        return DB::connection(self::CONNECTION)
            ->table(self::TABLE_REQUESTS)
            ->where('id', $id)
            ->update($updateData) > 0;
    }

    public function updateItemStatus(int $itemId, string $status, ?float $approvedQty = null, ?int $approvedBy = null): bool
    {
        return DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->where('id', $itemId)
            ->update([
                'item_status' => $status,
                'approved_quantity' => $approvedQty,
                'approved_by' => $approvedBy,
                'updated_at' => now(),
            ]) > 0;
    }

    public function getItemsByRequestId(int $requestId): Collection
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)
            ->where('request_id', $requestId)
            ->orderBy('id', 'asc')
            ->get();
    }

    public function checkAndUpdateToFullyProcured(int $requestId): bool
    {
        try {
            Log::debug('[MaterialRequestRepository] Checking if request is fully procured', ['request_id' => $requestId]);

            // 1. Get the request to validate current status
            $request = DB::connection(self::CONNECTION)
                ->table(self::TABLE_REQUESTS)
                ->where('id', $requestId)
                ->first();

            if (!$request) {
                Log::warning('[MaterialRequestRepository] Material request not found', ['request_id' => $requestId]);
                return false;
            }

            // If already fully_procured or closed, do nothing
            if (in_array($request->status, ['fully_procured', 'closed'])) {
                Log::debug('[MaterialRequestRepository] Request already fully procured or closed', ['request_id' => $requestId]);
                return false;
            }

            // 2. Count approved items (approved or partially_approved)
            $approvedItemsCount = DB::connection(self::CONNECTION)
                ->table(self::TABLE_ITEMS)
                ->where('material_request_id', $requestId)
                ->whereIn('item_status', ['approved', 'partially_approved'])
                ->count();

            // If no approved items, nothing to procure
            if ($approvedItemsCount === 0) {
                Log::debug('[MaterialRequestRepository] No approved items to procure', ['request_id' => $requestId]);
                return false;
            }

            // 3. Count how many of those approved items already have a PO linked
            // Link: material_request_item_id in nova_purchase_order_items
            $procuredItemsCount = DB::connection(self::CONNECTION)
                ->table(self::TABLE_ITEMS . ' as mri')
                ->join(self::TABLE_PO_ITEMS . ' as poi', 'mri.id', '=', 'poi.material_request_item_id')
                ->where('mri.material_request_id', $requestId)
                ->whereIn('mri.item_status', ['approved', 'partially_approved'])
                ->distinct('mri.id')
                ->count('mri.id');

            Log::debug('[MaterialRequestRepository] Procurement progress', [
                'request_id' => $requestId,
                'approved_items' => $approvedItemsCount,
                'procured_items' => $procuredItemsCount,
                'percentage' => round(($procuredItemsCount / $approvedItemsCount) * 100, 1),
            ]);

            // 4. If ALL approved items are procured -> update status
            if ($procuredItemsCount >= $approvedItemsCount) {
                $updated = DB::connection(self::CONNECTION)
                    ->table(self::TABLE_REQUESTS)
                    ->where('id', $requestId)
                    ->update([
                        'status' => 'fully_procured',
                        'updated_at' => now(),
                    ]) > 0;

                if ($updated) {
                    Log::info('[MaterialRequestRepository] Request marked as fully_procured', [
                        'request_id' => $requestId,
                        'approved_items' => $approvedItemsCount,
                        'procured_items' => $procuredItemsCount,
                    ]);
                }

                return $updated;
            }

            // 5. If not complete, optionally update to 'partially_procured'
            if ($procuredItemsCount > 0 && $request->status !== 'partially_procured') {
                DB::connection(self::CONNECTION)
                    ->table(self::TABLE_REQUESTS)
                    ->where('id', $requestId)
                    ->update([
                        'status' => 'partially_procured',
                        'updated_at' => now(),
                    ]);
                
                Log::info('[MaterialRequestRepository] Request marked as partially_procured', [
                    'request_id' => $requestId,
                    'approved' => $approvedItemsCount,
                    'procured' => $procuredItemsCount,
                ]);
            }

            return false;

        } catch (\Throwable $e) {
            Log::error('[MaterialRequestRepository] Error in checkAndUpdateToFullyProcured', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }
}