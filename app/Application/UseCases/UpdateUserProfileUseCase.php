<?php

namespace App\Application\UseCases;

use App\Application\DTOs\UpdateUserProfileRequest;
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