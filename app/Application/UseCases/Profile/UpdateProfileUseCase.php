<?php
// app/Application/UseCases/Profile/UpdateProfileUseCase.php

namespace App\Application\UseCases\Profile;

use App\Application\DTOs\Profile\UpdateProfilePermissionsRequest;
use App\Application\Repositories\ProfileRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class UpdateProfileUseCase
{
    // Constants for bitwise permissions (maintainability)
    private const PERMISSION_READ = 1;
    private const PERMISSION_WRITE = 2;
    private const PERMISSION_CREATE = 4;
    private const PERMISSION_DELETE = 8;
    
    // Permission map (DRY)
    private const PERMISSION_MAP = [
        'read' => self::PERMISSION_READ,
        'write' => self::PERMISSION_WRITE,
        'create' => self::PERMISSION_CREATE,
        'delete' => self::PERMISSION_DELETE,
    ];

    public function __construct(
        private readonly ProfileRepositoryInterface $repository
    ) {}

    /**
     * Update profile name and/or module permissions.
     * 
     * @param int $profileId
     * @param UpdateProfilePermissionsRequest $dto
     * @return array Updated profile data
     * 
     * @throws InvalidArgumentException If validation fails
     * @throws RuntimeException If profile not found or persistence fails
     */
    public function execute(int $profileId, UpdateProfilePermissionsRequest $dto): array
    {
        $existing = $this->repository->findById($profileId);
        if (!$existing) {
            throw new RuntimeException("Profile with ID {$profileId} not found");
        }

        return DB::connection('vtiger')->transaction(function () use ($profileId, $dto, $existing): array {
            $updated = [];

            // 1. Update name if changed
            if ($dto->name !== null && $dto->name !== $existing['profilename']) {
                $this->repository->updateName($profileId, $dto->name);
                $updated['name'] = $dto->name;
            }

            // 2. Delegate permission update to repository
            if (!empty($dto->modules)) {
                $this->repository->updateModulePermissions($profileId, $dto->modules);
                $updated['modules'] = $dto->modules;
            }

            // 3. Clear cache
            $this->clearVtigerPermissionCache();

            return $this->repository->findById($profileId) ?? $existing;
        });
    }

    /**
     * Convert permission strings to Vtiger's bitwise integer value.
     */
    private function calculateVtigerPermissionValue(array $permissions): int
    {
        $value = 0;
        $map = ['read' => 1, 'write' => 2, 'create' => 4, 'delete' => 8];
        
        foreach ($permissions as $perm) {
            if (isset($map[$perm])) {
                $value += $map[$perm];
            }
        }
        
        return $value;
    }

    /**
     * Clear Vtiger's cached permission tables.
     */
    private function clearVtigerPermissionCache(): void
    {
        $tables = [
            'vtiger_tmp_read_permission',
            'vtiger_tmp_write_permission',
            'vtiger_tmp_assign_permission',
            'vtiger_tmp_read_group_permissions',
            'vtiger_tmp_write_group_permissions',
        ];
        
        foreach ($tables as $table) {
            try {
                DB::connection('vtiger')->statement("DELETE FROM {$table}");
            } catch (\Exception $e) {
                // Ignore if table doesn't exist
            }
        }
    }
}