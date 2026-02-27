<?php

namespace App\Application\UseCases\User;

use App\Application\Repositories\UserRepositoryInterface;

class FindUserByFullNameUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(string $fullName): ?array
    {
        return $this->userRepository->findByFullName($fullName);
    }
}