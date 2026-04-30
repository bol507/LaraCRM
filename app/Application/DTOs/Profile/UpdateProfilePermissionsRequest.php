<?php
// app/Application/DTOs/Profile/UpdateProfilePermissionsRequest.php

namespace App\Application\DTOs\Profile;

use InvalidArgumentException;

/**
 * DTO for updating profile name and module permissions.
 */
class UpdateProfilePermissionsRequest
{
    /**
     * @param array<int, array{tabid: int, permissions: string[]}> $modules
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly array $modules = [],
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->name !== null && trim($this->name) === '') {
            throw new InvalidArgumentException('Profile name cannot be empty');
        }
        
        foreach ($this->modules as $mod) {
            if (!isset($mod['tabid']) || !is_int($mod['tabid']) || $mod['tabid'] <= 0) {
                throw new InvalidArgumentException('Each module must have a valid tabid (positive integer)');
            }
            if (!isset($mod['permissions']) || !is_array($mod['permissions'])) {
                throw new InvalidArgumentException('Each module must have a permissions array');
            }
            // Validar permisos permitidos
            $valid = ['read', 'write', 'create', 'delete'];
            foreach ($mod['permissions'] as $perm) {
                if (!in_array($perm, $valid, true)) {
                    throw new InvalidArgumentException("Invalid permission '{$perm}'. Allowed: " . implode(', ', $valid));
                }
            }
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? trim((string) $data['name']) : null,
            modules: array_map(function ($mod) {
                return [
                    'tabid' => (int) $mod['tabid'],
                    'permissions' => array_map('strval', $mod['permissions']),
                ];
            }, $data['modules'] ?? []),
        );
    }
}