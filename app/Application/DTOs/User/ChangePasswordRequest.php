<?php

namespace App\Application\DTOs\User;

use InvalidArgumentException;

class ChangePasswordRequest
{
    public function __construct(
        public readonly int $userId,
        public readonly string $newPassword,
        public readonly ?string $currentPassword = null,
    ) {
        $this->validate();
    }
    
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) $data['user_id'],
            newPassword: $data['new_password'],
            currentPassword: $data['current_password'] ?? null,
        );
    }

    private function validate(): void
    {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }
        if (strlen($this->newPassword) < 6) {
            throw new InvalidArgumentException('Password must be at least 6 characters');
        }
        
        if (!preg_match('/[A-Z]/', $this->newPassword)) {
            throw new InvalidArgumentException('Password must contain at least one uppercase letter');
        }
        if (!preg_match('/[0-9]/', $this->newPassword)) {
            throw new InvalidArgumentException('Password must contain at least one number');
        }
    }
}