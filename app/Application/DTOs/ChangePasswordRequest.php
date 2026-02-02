<?php

namespace App\Application\DTOs;

class ChangePasswordRequest
{
    public function __construct(
        public readonly int $userId,
        public readonly string $newPassword
    ) {}
}