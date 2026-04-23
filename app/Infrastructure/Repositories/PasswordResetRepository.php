<?php
// app/Infrastructure/Repositories/PasswordResetRepository.php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\PasswordResetRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PasswordResetRepository implements PasswordResetRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const TABLE = 'vtiger_password_resets';

    public function createOrUpdate(string $email, string $hashedToken, \DateTimeInterface $expiresAt): bool
    {
        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->updateOrInsert(
                ['email' => $email],
                [
                    'token' => $hashedToken,
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                    'used' => false,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                ]
            );
    }

    public function findValid(string $email, string $hashedToken): ?array
    {
        $reset = DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('email', $email)
            ->where('token', $hashedToken)
            ->where('expires_at', '>', now()->format('Y-m-d H:i:s'))
            ->where('used', false)
            ->first();
        
        return $reset ? (array) $reset : null;
    }

    public function markAsUsed(int $resetId): bool
    {
        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('id', $resetId)
            ->update(['used' => true]) > 0;
    }

    public function deleteExpired(): int
    {
        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('expires_at', '<', now()->format('Y-m-d H:i:s'))
            ->delete();
    }
}