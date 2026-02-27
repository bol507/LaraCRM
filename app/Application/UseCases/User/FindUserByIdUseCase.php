<?php

namespace App\Application\UseCases\User;

use App\Domain\Entities\User;
use App\Application\Repositories\UserRepositoryInterface;

class FindUserByIdUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }
}