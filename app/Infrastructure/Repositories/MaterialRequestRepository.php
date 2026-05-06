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
    protected const TABLE_REQUESTS = 'material_requests';
    protected const TABLE_ITEMS = 'material_request_items';
    protected const TABLE_USERS = 'vtiger_users';

    public function findById(int $id): ?object
    {
        Log::debug('Repo findById', [
            'connection' => self::CONNECTION, // Debería ser 'vtiger'
            'table' => self::TABLE_REQUESTS,  // Debería ser 'material_requests' o 'vtiger_material_requests'
            'id' => $id,
        ]);
        if ($id <= 0) throw new InvalidArgumentException('Request ID must be positive');

        $result =  DB::connection(self::CONNECTION)->table(self::TABLE_REQUESTS)
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

        // 4. Extraer IDs de los requests obtenidos
        $requestIds = $requests->pluck('id')->toArray();

        // 5. 🔑 CONSULTA ÚNICA: Traer TODOS los items de estos requests
        // Asegúrate que self::TABLE_ITEMS apunte a 'material_request_items' o 'vtiger_material_request_items'
        $itemsByRequest = DB::connection(self::CONNECTION)
            ->table(self::TABLE_ITEMS)
            ->whereIn('request_id', $requestIds)
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('request_id'); // Agrupa automáticamente por request_id

        // 6. Inyectar items en cada request
        $requests->each(function ($request) use ($itemsByRequest) {
            // Asigna el array de items o [] si no tiene
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
            ->table('material_requests as r')
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

        // 2. Obtener ítems
        $items = DB::connection(self::CONNECTION)
            ->table('material_request_items')
            ->where('request_id', $requestId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();

        // 3. Unir y retornar
        $request->items = $items;
        return (array) $request;
    }

    public function findItemById(int $id): ?object
    {
        return DB::connection(self::CONNECTION)->table('material_request_items')->where('id', $id)->first();
    }

    public function findItemsWithVendor(array $itemIds): array
    {
        return DB::connection(self::CONNECTION)
            ->table('material_request_items as mri')
            ->leftJoin('vtiger_products as p', 'mri.catalog_item_id', '=', 'p.id')
            ->whereIn('mri.id', $itemIds)
            ->select(
                'mri.*',
                'p.vendor_id' // ← Clave: obtener vendor_id del producto
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
        return DB::connection(self::CONNECTION)->table(self::TABLE_REQUESTS)
            ->where('id', $id)
            ->update([
                'status' => $status,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedBy ? now() : null,
                'rejection_notes' => $status === 'rejected' ? $notes : DB::raw('rejection_notes'),
                'updated_at' => now(),
            ]) > 0;
    }

    public function updateItemStatus(int $itemId, string $status, ?float $approvedQty = null, ?int $approvedBy = null): bool
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE_ITEMS)
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
}
