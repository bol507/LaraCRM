<?php

namespace App\Domain\Entities;

use InvalidArgumentException;

/**
 * Represents the assignment of a hierarchical role to a user.
 * Maps to `vtiger_user2role` table.
 *
 * @immutable One user → One role assignment in Vtiger.
 */
class UserRoleAssignment
{
    public function __construct(
        public readonly int $userId,
        public readonly string $roleId
    ) {
        $this->validate();
    }

    /**
     * Validate domain invariants.
     *
     * @throws InvalidArgumentException If data violates business rules
     */
    private function validate(): void
    {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }
        if (trim($this->roleId) === '') {
            throw new InvalidArgumentException('Role ID cannot be empty');
        }
    }

    /**
     * Convert entity to persistence/array format
     *
     * @internal Used by Infrastructure/Mappers
     */
    public function toArray(): array
    {
        return [
            'userid' => $this->userId,
            'roleid' => $this->roleId,
        ];
    }

    /**
     * Factory method to create from database row/array
     *
     * @internal Used by Infrastructure/Mappers
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) ($data['userid'] ?? 0),
            roleId: trim($data['roleid'] ?? '')
        );
    }
}