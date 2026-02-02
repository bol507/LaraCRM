<?php

namespace App\Application\DTOs;

class CreateUserRequest
{
    public function __construct(
        public readonly string $user_name,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $email,
        public readonly string $role,
        public readonly string $password,
        public readonly ?string $phone_crm = null,
        public readonly ?string $department = null,
        public readonly ?string $reports_to_id = null
    ) {}
}