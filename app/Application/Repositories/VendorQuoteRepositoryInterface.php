<?php

namespace App\Application\Repositories;

interface VendorQuoteRepositoryInterface {
    public function create(array $data): int;
    public function createItem(int $quoteId, array $data): void;
    public function findById(int $id): ?array;
    public function findByProject(int $projectId, array $filters = []): array;
    public function updateStatus(int $id, string $status): bool;
    public function recalculateTotal(int $quoteId): float;
}