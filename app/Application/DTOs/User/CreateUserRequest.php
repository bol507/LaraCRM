<?php

namespace App\Application\DTOs\User;

class CreateUserRequest
{
    public function __construct(
        public readonly string $user_name,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $email,
        public readonly bool $is_admin,
        public readonly string $role_id,
        public readonly string $status,
        public readonly string $password,
        public readonly ?string $phone_crm = null,
        public readonly ?string $department = null,
        public readonly ?int $reports_to_id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            user_name: trim($data['user_name']),
            first_name: trim($data['first_name']),
            last_name: trim($data['last_name']),
            email: trim($data['email']),
            is_admin: filter_var($data['is_admin'] ?? false, FILTER_VALIDATE_BOOLEAN),
            role_id: trim($data['role_id']),
            status: $data['status'] ?? 'Active',
            password: $data['password'],
            phone_crm: $data['phone_crm'] ?? null,
            department: $data['department'] ?? null,
            reports_to_id: isset($data['reports_to_id']) ? (int) $data['reports_to_id'] : null,
        );
    }
}