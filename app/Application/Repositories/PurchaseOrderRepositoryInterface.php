<?php
// app/Application/Repositories/PurchaseOrderRepositoryInterface.php

namespace App\Application\Repositories;

interface PurchaseOrderRepositoryInterface
{
    public function create(array $data): int;
    public function createItem(int $poId, array $itemData): void;
    public function findById(int $id): ?array;
    public function findByProject(int $projectId, array $filters, int $limit, int $page): array;
    public function updateStatus(int $id, string $status): bool;
    public function updateReceiptStatus(int $itemId, string $status, float $receivedQty): bool;
    public function recordItemReceipt(int $poId, int $itemId, array $data): array;
}