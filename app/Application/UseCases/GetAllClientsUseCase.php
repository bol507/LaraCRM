<?php

namespace App\Application\UseCases;

use App\Application\Repositories\ClientRepositoryInterface;
use App\Domain\Entities\Client;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAllClientsUseCase
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {}

    public function execute(int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return $this->clientRepository->getAll($page, $perPage);
    }
}