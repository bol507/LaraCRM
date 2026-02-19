<?php

namespace App\Application\Repositories;

use App\Application\DTOs\CreateOpportunityRequest;
use App\Domain\Entities\Opportunity;
use Illuminate\Pagination\LengthAwarePaginator;

interface OpportunityRepositoryInterface
{
    public function getAll(int $page = 1, int $perPage = 20, ?string $search = null): LengthAwarePaginator;
    public function findById(int $id): ?Opportunity;
    public function create(CreateOpportunityRequest $request, int $createdByUserId): int;
    public function update(int $id, array $data, int $modifiedByUserId): bool;
    public function delete(int $id, int $deletedByUserId): bool;
    
    public function getAvailableStages(): array;
}