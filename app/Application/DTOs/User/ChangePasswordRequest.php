<?php

namespace App\Application\DTOs\User;

class ChangePasswordRequest
{
    public function __construct(
        public readonly int $userId,
        public readonly string $newPassword
    ) {}
}