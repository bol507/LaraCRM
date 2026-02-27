<?php

namespace App\Application\UseCases\User;

use App\Application\DTOs\User\CreateUserRequest;
use App\Application\Repositories\UserRepositoryInterface;

class CreateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(CreateUserRequest $request, int $createByUserId): int
    {
        return $this->userRepository->create($request, $createByUserId);
    }
}