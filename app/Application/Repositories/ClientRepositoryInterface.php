<?php

namespace App\Application\Repositories;

use App\Application\DTOs\CreateClientRequest;
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
}