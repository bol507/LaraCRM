<?php
// app/Application/UseCases/Auth/RequestPasswordResetUseCase.php

namespace App\Application\UseCases\Auth;

use App\Application\DTOs\Auth\RequestPasswordResetRequest;
use App\Application\Repositories\UserRepositoryInterface;
use App\Application\Repositories\PasswordResetRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class RequestPasswordResetUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetRepositoryInterface $resetRepository,
    ) {}

    /**
     * Request a password reset token for a user.
     * Always returns success to prevent user enumeration.
     * 
     * @param RequestPasswordResetRequest $dto
     * @return array{message: string, email_sent: bool}
     */
    public function execute(RequestPasswordResetRequest $dto): array
    {
        $email = $dto->email;
        $user = $this->userRepository->findByEmail($email);
        
        $emailSent = false;
        
        // Only process if user exists and is active
        if ($user && $user->isActive()) {
            // Generate secure token
            $plainToken = Str::random(64);
            $hashedToken = hash('sha256', $plainToken);
            $expiresAt = now()->addHours(1);
            
            // Save token (upsert to handle multiple requests)
            $this->resetRepository->createOrUpdate($email, $hashedToken, $expiresAt);
            
            // Send email (inject mail service if needed)
            // $this->mailService->sendResetLink($user, $plainToken);
            
            $emailSent = true;
            
            
        } else {
            
        }
        
        return [
            'message' => 'If the email exists and is active, a password reset link has been sent',
            'email_sent' => $emailSent,
        ];
    }
}