<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateClientRequest;
use App\Application\Repositories\ClientRepositoryInterface;

class CreateClientUseCase
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {}

   public function execute(CreateClientRequest $request, int $userId): int
    {
        return $this->clientRepository->create($request, $userId);
    }
}