<?php
// app/Application/DTOs/Auth/ResetPasswordRequest.php

namespace App\Application\DTOs\Auth;

use InvalidArgumentException;

class ResetPasswordRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly string $password,
        public readonly string $passwordConfirmation,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        if (strlen($this->token) < 64) {
            throw new InvalidArgumentException('Invalid token format');
        }
        
        if (strlen($this->password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }
        
        if ($this->password !== $this->passwordConfirmation) {
            throw new InvalidArgumentException('Password confirmation does not match');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: strtolower(trim((string) ($data['email'] ?? ''))),
            token: trim((string) ($data['token'] ?? '')),
            password: (string) ($data['password'] ?? ''),
            passwordConfirmation: (string) ($data['password_confirmation'] ?? ''),
        );
    }
}