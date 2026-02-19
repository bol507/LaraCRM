<?php

namespace App\Application\UseCases;

use App\Application\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;

class GetMyProfileUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $userId): ?User
    {
        return $this->userRepository->findById($userId);
    }
}