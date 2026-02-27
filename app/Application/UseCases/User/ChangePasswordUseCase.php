<?php

namespace App\Application\UseCases\User;

use App\Application\DTOs\User\ChangePasswordRequest;
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