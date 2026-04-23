<?php

namespace App\Application\DTOs\Role;

use InvalidArgumentException;

/**
 * DTO for assigning a profile to a hierarchical role.
 */
class AssignProfileRequest
{
    public function __construct(
        public readonly string $roleId,
        public readonly int $profileId,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (!preg_match('/^H\d+$/', $this->roleId)) {
            throw new InvalidArgumentException('Invalid role ID format. Expected H + number (e.g., H2)');
        }
        if ($this->profileId <= 0) {
            throw new InvalidArgumentException('Profile ID must be positive');
        }
    }

    public static function fromArray(array $data, string $roleId): self
    {
        return new self(
            roleId: $roleId,
            profileId: (int) ($data['profile_id'] ?? 0),
        );
    }
}