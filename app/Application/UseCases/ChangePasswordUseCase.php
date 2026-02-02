<?php

namespace App\Application\UseCases;

use App\Application\DTOs\ChangePasswordRequest;
use App\Application\Repositories\UserRepositoryInterface;

class ChangePasswordUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    )
    {}
    
    public function execute(ChangePasswordRequest $request, int $modifiedByUserId): bool
    {
        return $this->userRepository->changePassword($request->userId, $request->newPassword, $modifiedByUserId);
    }
}