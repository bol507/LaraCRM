<?php

namespace App\Domain\Entities;

class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $user_name,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $email,
        public readonly string $role,       
        public readonly string $status,
        public readonly ?string $phone_crm = null,
        public readonly ?string $department = null,
        public readonly ?string $reports_to_id = null,
        public readonly bool $is_active
    ) {}
}