<?php

namespace App\Application\Repositories;

use App\Application\DTOs\CreateQuoteRequest;
use App\Application\DTOs\UpdateQuoteRequest;
use App\Application\DTOs\QuoteResponse;
use Illuminate\Pagination\LengthAwarePaginator;

interface QuoteRepositoryInterface
{
    public function create(CreateQuoteRequest $request, int $createdByUserId): int;
    public function update(UpdateQuoteRequest $request, int $modifiedByUserId): bool;
    public function findById(int $quoteid): ?QuoteResponse;
    public function delete(int $quoteid): bool;
    public function paginate(int $page, int $perPage, ?string $search = null): LengthAwarePaginator;
}