<?php

namespace App\Application\UseCases;

use App\Application\DTOs\UpdateUserRequest;
use App\Application\Repositories\UserRepositoryInterface;

class UpdateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(UpdateUserRequest $request, int $modifiedByUserId): bool
    {
        return $this->userRepository->update($request, $modifiedByUserId);
    }
}