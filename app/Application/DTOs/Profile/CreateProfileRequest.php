<?php
// app/Application/DTOs/Profile/CreateProfileRequest.php

namespace App\Application\DTOs\Profile;

use InvalidArgumentException;

/**
 * DTO for creating a new permission profile.
 */
class CreateProfileRequest
{
    /**
     * @param array<int, array{tabid: int, permissions: string[]}> $modules
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly array $modules = [],
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        // Name is required and valid
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Profile name is required');
        }
        if (strlen($this->name) > 100) {
            throw new InvalidArgumentException('Profile name cannot exceed 100 characters');
        }

        // Description is optional
        if ($this->description !== null && strlen($this->description) > 255) {
            throw new InvalidArgumentException('Description cannot exceed 255 characters');
        }

        // Validate module structure
        foreach ($this->modules as $index => $mod) {
            if (!isset($mod['tabid']) || !is_int($mod['tabid']) || $mod['tabid'] <= 0) {
                throw new InvalidArgumentException("Module #{$index} must have a valid tabid (positive integer)");
            }
            if (!isset($mod['permissions']) || !is_array($mod['permissions'])) {
                throw new InvalidArgumentException("Module #{$index} must have a permissions array");
            }
            // Validate allowed permissions
            $valid = ['read', 'write', 'create', 'delete'];
            foreach ($mod['permissions'] as $perm) {
                if (!in_array($perm, $valid, true)) {
                    throw new InvalidArgumentException(
                        "Invalid permission '{$perm}' in module #{$index}. Allowed: " . implode(', ', $valid)
                    );
                }
            }
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) ($data['name'] ?? '')),
            description: isset($data['description']) ? trim((string) $data['description']) : null,
            modules: array_map(function ($mod) {
                return [
                    'tabid' => (int) $mod['tabid'],
                    'permissions' => array_map('strval', $mod['permissions'] ?? []),
                ];
            }, $data['modules'] ?? []),
        );
    }
}