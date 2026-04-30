<?php
// app/Application/DTOs/Auth/RequestPasswordResetRequest.php

namespace App\Application\DTOs\Auth;

use InvalidArgumentException;

class RequestPasswordResetRequest
{
    public function __construct(
        public readonly string $email,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            email: strtolower(trim((string) ($data['email'] ?? ''))),
        );
    }
}