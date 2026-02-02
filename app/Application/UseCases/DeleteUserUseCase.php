<?php

namespace App\Application\UseCases;

use App\Application\Repositories\UserRepositoryInterface;

class DeleteUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $id, int $deletedByUserId): bool
    {
        return $this->userRepository->delete($id, $deletedByUserId);
    }
}