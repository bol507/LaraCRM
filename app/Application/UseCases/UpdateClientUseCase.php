<?php

namespace App\Application\UseCases;

use App\Application\DTOs\UpdateClientRequest;
use App\Application\Repositories\ClientRepositoryInterface;

class UpdateClientUseCase
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {}

    public function execute(UpdateClientRequest $request, int $userId): bool
    {
        return $this->clientRepository->update($request, $userId);
    }
}