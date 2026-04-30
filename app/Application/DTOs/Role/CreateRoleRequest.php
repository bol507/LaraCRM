<?php

namespace App\Application\DTOs\Role;

use InvalidArgumentException;

/**
 * Immutable DTO for creating a new hierarchical role.
 * Validates required fields at instantiation.
 */
class CreateRoleRequest
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $parentId = null,
    ) {
        
        $this->validate();
    }

    /**
     * Validate DTO properties.
     * 
     * @throws InvalidArgumentException If validation fails
     */
    private function validate(): void
    {
        if ($this->name === '') {
            throw new InvalidArgumentException('Role name cannot be empty');
        }
        if (strlen($this->name) > 100) {
            throw new InvalidArgumentException('Role name cannot exceed 100 characters');
        }
        // parentId se valida en el UseCase (debe existir en BD)
    }

    /**
     * Factory method to create DTO from controller request data.
     * 
     * @param array<string, mixed> $data Raw request data (snake_case)
     * @return self
     * @throws InvalidArgumentException If required fields are missing or invalid
     */
    public static function fromArray(array $data): self
    {
        
        if (!isset($data['name']) || trim((string) $data['name']) === '') {
            throw new InvalidArgumentException('Role name is required');
        }

        return new self(
            name: trim((string) $data['name']),
            parentId: isset($data['parent_id']) ? (string) $data['parent_id'] : null,
        );
    }
}
