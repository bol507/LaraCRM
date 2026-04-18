<?php

namespace App\Application\DTOs\Role;

class AssignRoleRequest
{
    public function __construct(
        public readonly int $userId,
        public readonly string $roleId, 
    ) {
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException('User ID must be positive');
        }
        if (trim($this->roleId) === '') {
            throw new \InvalidArgumentException('Role ID cannot be empty');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) ($data['user_id'] ?? 0),
            roleId: trim($data['role_id'] ?? ''),
        );
    }
}