<?php
// app/Application/DTOs/Auth/LoginRequest.php

namespace App\Application\DTOs\Auth;

use InvalidArgumentException;

/**
 * DTO for user login authentication.
 */
class LoginRequest
{
    public function __construct(
        public readonly string $user_name,
        public readonly string $password,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (trim($this->user_name) === '') {
            throw new InvalidArgumentException('Username is required');
        }
        if (trim($this->password) === '') {
            throw new InvalidArgumentException('Password is required');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            user_name: trim((string) ($data['user_name'] ?? '')),
            password: (string) ($data['password'] ?? ''),
        );
    }
}