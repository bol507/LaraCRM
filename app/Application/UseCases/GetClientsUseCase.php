<?php

namespace App\Application\UseCases;

use App\Application\Repositories\ClientRepositoryInterface;
use App\Domain\Entities\Client;
use Illuminate\Pagination\LengthAwarePaginator;

class GetClientsUseCase
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {}

    public function execute(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null, 
        ?array $filters = null
    ): LengthAwarePaginator {
        return $this->clientRepository->getAll($page, $perPage, $search, $filters);
    }
}
