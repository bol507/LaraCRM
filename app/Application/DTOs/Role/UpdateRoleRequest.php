<?php

namespace App\Application\DTOs\Role;

use InvalidArgumentException;

class UpdateRoleRequest
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $parentId = null,
        public readonly ?int $sharingRule = null,
    ) {
        $this->validate();
    }

    /**
     * Validate DTO properties before instantiation.
     * 
     * @throws InvalidArgumentException If validation fails
     */
    private function validate(): void
    {
        // validate name (if provided)
        if ($this->name !== null) {
            $trimmed = trim($this->name);
            if ($trimmed === '') {
                throw new InvalidArgumentException('Role name cannot be empty');
            }
            if (strlen($trimmed) > 100) {
                throw new InvalidArgumentException('Role name cannot exceed 100 characters');
            }
        }

        // validate sharing rule (Vtiger allowassignedrecordsto)
        // Valid values in Vtiger:
        // 0 = Private
        // 1 = Role (Parent only)
        // 2 = Role & Subordinates
        // 3 = All users
        if ($this->sharingRule !== null && !in_array($this->sharingRule, [0, 1, 2, 3], true)) {
            throw new InvalidArgumentException(
                'Invalid sharing rule. Allowed values: 0 (Private), 1 (Parent), 2 (Role & Subordinates), 3 (All)'
            );
        }
    }

    /**
     * Factory method to create DTO from controller request data.
     * Maps snake_case JSON keys to camelCase properties.
     *
     * @param array<string, mixed> $data Raw request data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? trim((string) $data['name']) : null,
            parentId: isset($data['parent_id']) ? (string) $data['parent_id'] : null,
            sharingRule: isset($data['sharing_rule']) ? (int) $data['sharing_rule'] : null,
        );
    }
}