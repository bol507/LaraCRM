<?php

namespace App\Application\DTOs;

class UpdateUserProfileRequest
{
    public function __construct(
        public readonly int $id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $user_name,
        public readonly string $email,
        public readonly ?string $role = null,
        public readonly ?string $department = null,
        public readonly ?string $phone_crm = null,
        public readonly ?string $reports_to_id = null
    ) {}
}
