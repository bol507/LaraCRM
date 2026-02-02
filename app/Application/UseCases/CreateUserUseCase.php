<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateUserRequest;
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