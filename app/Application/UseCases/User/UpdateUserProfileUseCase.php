<?php

namespace App\Application\UseCases\User;

use App\Application\DTOs\User\UpdateUserProfileRequest;
use App\Application\Repositories\UserRepositoryInterface;

class UpdateUserProfileUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}
    
    public function execute(UpdateUserProfileRequest $request, int $modifiedByUserId): bool
    {
        return $this->userRepository->updateProfile($request, $modifiedByUserId);
    }
}