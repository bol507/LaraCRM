<?php

namespace App\Application\UseCases;

use App\Application\Repositories\UserRepositoryInterface;

class FindUsersByNameOrUsernameUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(string $searchTerm): array
    {
        return $this->userRepository->findByNameOrUsername($searchTerm);
    }
}