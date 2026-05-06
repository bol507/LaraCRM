<?php
// app/Application/Repositories/PurchaseOrderRepositoryInterface.php

namespace App\Application\Repositories;

use App\Application\DTOs\Procurement\ListPurchaseOrdersRequestDto;
use Illuminate\Support\Collection;

interface PurchaseOrderRepositoryInterface
{
    public function findById(int $poId, int $projectId): ?array;
    public function findByPoNumber(string $poNumber): ?object;
    public function findByProjectId(ListPurchaseOrdersRequestDto $dto): array;
    public function findByStatus(string $status): Collection;
    /**
     * Find a purchase order by ID with its lines
     */
    public function findByIdWithLines(int $poId): ?array;
    /**
     * Create a new purchase order header
     * @return int The created PO ID
     */
    public function create(array $data): int;
    /**
     * Add a line item to an existing purchase order
     * 
     * @param int $poId Purchase order ID
     * @param array $lineData Line item data including source_request_item_id for traceability
     * @return int The created line item ID
     */
    public function addLine(int $poId, array $lineData): int;
    public function addItem(int $poId, array $itemData): int;
    /**
     * Update PO status
     */
    public function updateStatus(int $id, string $status, ?int $approvedBy = null): bool;
    public function recordReception(int $itemId, float $receivedQty): bool;
    public function getItemsByPoId(int $poId): Collection;
    public function calculateTotal(int $poId): float;
    public function updateTotal(int $poId, float $totalAmount): bool;
}