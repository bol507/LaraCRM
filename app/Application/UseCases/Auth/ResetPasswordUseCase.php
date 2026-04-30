<?php
// app/Application/UseCases/Auth/ResetPasswordUseCase.php

namespace App\Application\UseCases\Auth;

use App\Application\DTOs\Auth\ResetPasswordRequest;
use App\Application\Repositories\UserRepositoryInterface;
use App\Application\Repositories\PasswordResetRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ResetPasswordUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetRepositoryInterface $resetRepository,
    ) {}

    /**
     * Reset user password with valid token.
     * 
     * @param ResetPasswordRequest $dto
     * @return array{message: string, user_id: int}
     * 
     * @throws InvalidArgumentException If token is invalid/expired
     * @throws RuntimeException If persistence fails
     */
    public function execute(ResetPasswordRequest $dto): array
    {
        $email = $dto->email;
        $hashedToken = hash('sha256', $dto->token);
        
        // 1. Validate token (delegated to repository)
        $reset = $this->resetRepository->findValid($email, $hashedToken);
        
        if (!$reset) {
            
            throw new InvalidArgumentException('Invalid or expired reset token');
        }
        
        // 2. Find user and validate
        $user = $this->userRepository->findByEmail($email);
        
        if (!$user) {
            throw new RuntimeException('User not found');
        }
        
        if (!$user->isActive()) {
            throw new RuntimeException('User account is inactive');
        }
        
        // 3. Execute in atomic transaction
        return DB::connection('vtiger')->transaction(function () use ($user, $dto, $reset): array {
            // a) Update password with secure hashing
            $this->userRepository->changePassword(
                $user->getId(),
                $dto->password,
                $user->getId() // modified_by = self for reset
            );
            
            // b) Mark token as used
            $this->resetRepository->markAsUsed($reset['id']);
            
            
            
            return [
                'message' => 'Password reset successfully',
                'user_id' => $user->getId(),
            ];
        });
    }
}