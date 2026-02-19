<?php

namespace App\Application\UseCases;

use App\Application\Repositories\ClientRepositoryInterface;

class FindClientsByNameOrEmailUseCase
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {}

    public function execute(string $searchTerm): array
    {
        return $this->clientRepository->findByNameOrEmail($searchTerm);
    }
}