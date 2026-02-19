<?php

namespace App\Application\Repositories;

use App\Application\DTOs\CreateClientRequest;
use App\Application\DTOs\UpdateClientRequest;
use App\Domain\Entities\Client;
use Illuminate\Pagination\LengthAwarePaginator;

interface ClientRepositoryInterface
{
    public function getAll(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        ?array $filters = null
    ): LengthAwarePaginator;
    public function findById(int $id): ?Client;
    public function create(CreateClientRequest $request, int $userId): int;
    public function update(UpdateClientRequest $request, int $userId): bool;
    public function delete(int $id): bool;

    public function findByAccountName(string $accountName): ?array;
    public function findByNameOrEmail(string $searchTerm): array;
}