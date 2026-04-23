<?php
// app/Application/UseCases/Role/AssignProfileToRoleUseCase.php

namespace App\Application\UseCases\Role;

use App\Application\DTOs\Role\AssignProfileRequest;
use App\Application\Repositories\ProfileRepositoryInterface;
use App\Application\Repositories\RoleRepositoryInterface;
use App\Application\Repositories\RoleProfileAssignmentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class AssignProfileToRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly RoleProfileAssignmentRepositoryInterface $assignmentRepository,
    ) {}

    /**
     * Assign a permission profile to a hierarchical role.
     * Updates vtiger_profile2role and clears Vtiger permission cache.
     *
     * @throws InvalidArgumentException If role or profile doesn't exist
     * @throws RuntimeException If assignment fails
     */
    public function execute(AssignProfileRequest $request): bool
    {
        // 1. validate that role exists
        $role = $this->roleRepository->findById($request->roleId);
        if (!$role) {
            throw new InvalidArgumentException("Role '{$request->roleId}' does not exist");
        }

        // 2. validate that profile exists
        $profile = $this->profileRepository->findById($request->profileId);
        if (!$profile) {
            throw new InvalidArgumentException("Profile with ID {$request->profileId} does not exist");
        }

        // 3. assign in transaction
        return DB::connection('vtiger')->transaction(function () use ($request): bool {
            // a) Eliminar asignación anterior (si existe)
            $this->assignmentRepository->deleteByRoleId($request->roleId);
            
            // b) create new assignment
            $assigned = $this->assignmentRepository->assign($request->roleId, $request->profileId);
            if (!$assigned) {
                throw new RuntimeException("Failed to assign profile {$request->profileId} to role {$request->roleId}");
            }
            
            // c) clear cache of permissions in Vtiger
            $this->clearVtigerPermissionCache();
            
            return true;
        });
    }

    /**
     * Clear Vtiger's cached permission tables to force recalculation.
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
                Log::warning("Could not clear cache table {$table}: " . $e->getMessage());
            }
        }
    }
}