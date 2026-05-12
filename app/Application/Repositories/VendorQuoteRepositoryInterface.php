<?php

namespace App\Application\Repositories;

interface VendorQuoteRepositoryInterface {
    public function create(array $data): int;
    public function createItem(int $quoteId, array $data): void;
    public function findById(int $id): ?array;
    public function findByProject(int $projectId, array $filters, int $limit, int $page): array;
    public function updateStatus(int $id, string $status): bool;
    public function recalculateTotal(int $quoteId): float;
    public function updateTermsAndNotes(int $id, ?string $notes, ?string $terms): bool;
    public function acceptQuote(int $id, int $acceptedBy, ?string $notes): bool;
}