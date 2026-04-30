<?php
// app/Application/Repositories/PasswordResetRepositoryInterface.php

namespace App\Application\Repositories;

interface PasswordResetRepositoryInterface
{
    /**
     * Create or update a password reset token.
     */
    public function createOrUpdate(string $email, string $hashedToken, \DateTimeInterface $expiresAt): bool;
    
    /**
     * Find a valid (unused, not expired) reset token.
     * 
     * @return array{id: int, email: string, token: string, expires_at: string}|null
     */
    public function findValid(string $email, string $hashedToken): ?array;
    
    /**
     * Mark a token as used.
     */
    public function markAsUsed(int $resetId): bool;
    
    /**
     * Delete expired tokens (for cleanup jobs).
     */
    public function deleteExpired(): int;
}