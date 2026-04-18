<?php

namespace App\Application\DTOs\User;

class ChangePasswordRequest
{
    public function __construct(
        public readonly int $userId,
        public readonly string $newPassword
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            newPassword: $data['new_password'],
        );
    }
}