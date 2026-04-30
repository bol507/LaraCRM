<?php
// app/Application/UseCases/Profile/CreateProfileUseCase.php

namespace App\Application\UseCases\Profile;

use App\Application\DTOs\Profile\CreateProfileRequest;
use App\Application\Repositories\ProfileRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CreateProfileUseCase
{
    public function __construct(
        private readonly ProfileRepositoryInterface $repository
    ) {}

    /**
     * Create a new permission profile with optional module permissions.
     * 
     * @param CreateProfileRequest $dto
     * @return array Created profile data with profileid
     * 
     * @throws InvalidArgumentException If validation fails or name already exists
     * @throws RuntimeException If persistence fails
     */
    public function execute(CreateProfileRequest $dto): array
    {
        return DB::connection('vtiger')->transaction(function () use ($dto): array {
            // 1. Check if the name already exists
            if ($this->repository->existsByName($dto->name)) {
                throw new InvalidArgumentException("Profile '{$dto->name}' already exists");
            }

            // 2. Create profile in vtiger_profile
            $profile = $this->repository->create($dto->name, $dto->description ?? '');
            $profileId = $profile['profileid'];

            // 3. Initialize module permissions (all hidden by default)
            if (!empty($dto->modules)) {
                $this->initializeModulePermissions($profileId, $dto->modules);
            } else {
                // If no modules provided, initialize all as hidden
                $this->initializeAllModulesHidden($profileId);
            }

            // 4. Clear Vtiger permission cache
            $this->clearVtigerPermissionCache();

            // 5. Return created profile data
            return [
                'profileid' => (string) $profileId,
                'name' => $dto->name,
                'description' => $dto->description,
            ];
        });
    }

    /**
     * Initialize permissions for specific modules.
     * 
     * @param int $profileId
     * @param array<int, array{tabid: int, permissions: string[]}> $modules
     */
    private function initializeModulePermissions(int $profileId, array $modules): void
    {
        $allowedTabIds = array_column($modules, 'tabid');

        // Get all active modules in the system
        $allTabs = DB::connection('vtiger')
            ->table('vtiger_tab')
            ->where('presence', 0) // Only active modules
            ->pluck('tabid', 'name')
            ->toArray();

        foreach ($allTabs as $tabName => $tabid) {
            $moduleConfig = collect($modules)->firstWhere('tabid', $tabid);
            
            if ($moduleConfig) {
                // Module allowed: calculate permissions
                $permissionValue = $this->calculateVtigerPermissionValue($moduleConfig['permissions']);
                
                // Insert into vtiger_profile2tab
                DB::connection('vtiger')->table('vtiger_profile2tab')->insert([
                    'profileid' => $profileId,
                    'tabid' => $tabid,
                    'permissions' => $permissionValue,
                ]);
            } else {
                // Module NOT allowed: hide with permissions = 0
                DB::connection('vtiger')->table('vtiger_profile2tab')->insert([
                    'profileid' => $profileId,
                    'tabid' => $tabid,
                    'permissions' => 0,
                ]);
            }
        }
    }

    /**
     * Initialize all modules as hidden for a new profile.
     */
    private function initializeAllModulesHidden(int $profileId): void
    {
        $allTabs = DB::connection('vtiger')
            ->table('vtiger_tab')
            ->where('presence', 0)
            ->pluck('tabid')
            ->toArray();

        $inserts = array_map(fn($tabid) => [
            'profileid' => $profileId,
            'tabid' => $tabid,
            'permissions' => 0, // Hidden by default
        ], $allTabs);

        if (!empty($inserts)) {
            DB::connection('vtiger')
                ->table('vtiger_profile2tab')
                ->insert($inserts);
        }
    }

    /**
     * Convert permission strings to Vtiger's bitwise integer value.
     * 
     * Vtiger permission bits: 1=Read, 2=Write, 4=Create, 8=Delete
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
                // Ignore if table doesn't exist (Vtiger < 7.2)
            }
        }
    }
}