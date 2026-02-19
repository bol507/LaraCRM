<?php

namespace App\Application\UseCases;

use App\Application\Repositories\ClientRepositoryInterface;
use App\Domain\Entities\Client;

class FindClientByAccountNameUseCase
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository
    ) {}

    public function execute(string $accountName): ?Client
    {
        return $this->clientRepository->findByAccountName($accountName);
    }
}