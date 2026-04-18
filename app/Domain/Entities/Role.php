<?php

namespace App\Domain\Entities;

use InvalidArgumentException;

/**
 * Represents a hierarchical role in the CRM system.
 * Maps to `vtiger_role` table.
 *
 * @immutable Domain entities should be immutable after creation.
 */
class Role
{
    public function __construct(
        public readonly string $roleId,
        public readonly string $roleName,
        public readonly string $parentRole,
        public readonly int $depth,
        public readonly int $allowAssignedRecordsTo
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
        if (trim($this->roleId) === '') {
            throw new InvalidArgumentException('Role ID cannot be empty');
        }
        if (trim($this->roleName) === '') {
            throw new InvalidArgumentException('Role name cannot be empty');
        }
        if ($this->depth < 0) {
            throw new InvalidArgumentException('Role depth cannot be negative');
        }
        if ($this->allowAssignedRecordsTo < 0) {
            throw new InvalidArgumentException('Invalid sharing rule value');
        }
    }

    /**
     * Check if this is a root role (e.g., "Organization")
     */
    public function isRoot(): bool
    {
        return $this->depth === 0;
    }

    /**
     * Extract the immediate parent role ID from the hierarchy path.
     * Example: "H1::H2::H3" → returns "H2"
     */
    public function getParentRoleId(): ?string
    {
        $path = rtrim($this->parentRole, ':');
        $parts = explode('::', $path);
        return count($parts) > 1 ? $parts[count($parts) - 2] : null;
    }

    /**
     * Get full hierarchy as an array of role IDs
     * Example: "H1::H2::H3:" → ['H1', 'H2', 'H3']
     */
    public function getHierarchy(): array
    {
        return array_filter(explode('::', rtrim($this->parentRole, ':')));
    }

    /**
     * Convert entity to persistence/array format
     *
     * @internal Used by Infrastructure/Mappers
     */
    public function toArray(): array
    {
        return [
            'roleid' => $this->roleId,
            'rolename' => $this->roleName,
            'parentrole' => $this->parentRole,
            'depth' => $this->depth,
            'allowassignedrecordsto' => $this->allowAssignedRecordsTo,
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
            roleId: trim($data['roleid'] ?? ''),
            roleName: trim($data['rolename'] ?? ''),
            parentRole: $data['parentrole'] ?? '',
            depth: (int) ($data['depth'] ?? 0),
            allowAssignedRecordsTo: (int) ($data['allowassignedrecordsto'] ?? 1)
        );
    }
}