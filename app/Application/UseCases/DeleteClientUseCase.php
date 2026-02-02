<?php

namespace App\Application\UseCases;

use App\Application\Repositories\ClientRepositoryInterface;

class DeleteClientUseCase
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {}

    public function execute(int $id): bool
    {
        return $this->clientRepository->delete($id);
    }
}