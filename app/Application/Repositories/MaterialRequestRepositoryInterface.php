<?php
// app/Application/Repositories/MaterialRequestRepositoryInterface.php

namespace App\Application\Repositories;

use Illuminate\Support\Collection;

interface MaterialRequestRepositoryInterface
{
    /**
     * Find a material request by ID
     * @return object|null stdClass row
     */
    public function findById(int $id): ?object;

    /**
     * Get all requests for a project with optional filters
     * @param integer $projectId
     * @param array $filters
     * @param integer $limit
     * @param integer $offset
     * @return array<string, mixed>
     */
    public function findByProjectId(int $projectId, array $filters = [], int $limit = 50, int $offset = 0): array;

    /**
     * Get pending requests for approval
     * @return Collection<object>
     */
    public function findPendingBySupervisor(int $supervisorId): Collection;

    public function findByIdWithItems(int $requestId): ?array;

    public function findItemById(int $id): ?object;

    public function findItemsWithVendor(array $itemIds): array;

    /**
     * Create a new material request header
     * @return int New request ID
     */
    public function create(array $data): int;

    /**
     * Bulk insert items for a request
     * @return int Number of items inserted
     */
    public function createItems(int $requestId, array $items): int;

    /**
     * Update request status and approval metadata
     */
    public function updateStatus(int $id, string $status, ?int $approvedBy = null, ?string $notes = null): bool;

    /**
     * Update individual item status & approved quantity
     */
    public function updateItemStatus(int $itemId, string $status, ?float $approvedQty = null, ?int $approvedBy = null): bool;

    /**
     * Get all items for a request
     * @return Collection<object>
     */
    public function getItemsByRequestId(int $requestId): Collection;

    /**
     * checks if the request is fully procured.
     * 
     * @param int $requestId ID of the request to check
     * @return bool True if request is fully procured, false otherwise
     */
    public function checkAndUpdateToFullyProcured(int $requestId): bool;
}