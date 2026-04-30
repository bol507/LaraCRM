<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\Role\RoleOptionResponse;
use App\Application\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RoleRepository implements RoleRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const ROLE_TABLE = 'vtiger_role';
    protected const USER_ROLE_TABLE = 'vtiger_user2role';
    protected const CRMENTITY_TABLE = 'vtiger_crmentity';
    protected const ROLE2PROFILE_TABLE = 'vtiger_role2profile';

    /**
     * Fetch all roles ordered by hierarchy depth.
     */
    public function findAll(): array
    {
        return $this->query()
            ->leftJoin(self::ROLE2PROFILE_TABLE, 'vtiger_role.roleid', '=', 'vtiger_role2profile.roleid')
            ->select(
                'vtiger_role.*',
                'vtiger_role2profile.profileid as profile_id'
            )
            ->orderBy('depth', 'asc')
            ->orderBy('rolename', 'asc')
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();
    }

    /**
     * Find a single role by its ID.
     */
    public function findById(string $roleId): ?array
    {
        $row = $this->query()
            ->where('roleid', $roleId)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Fetch direct child roles of a given parent.
     */
    public function findByParentId(string $parentId): array
    {
        $parent = $this->findById($parentId);
        if (!$parent) {
            return [];
        }

        return $this->query()
            ->where('depth', $parent['depth'] + 1)
            ->where('parentrole', 'LIKE', $parent['parentrole'] . '%')
            ->where('roleid', '!=', $parentId)
            ->orderBy('rolename', 'asc')
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();
    }

    /**
     * @inheritDoc
     */
    public function findByUserId(int $userId): ?array
    {
        $roleData = DB::connection(self::CONNECTION)
            ->table(self::USER_ROLE_TABLE) // vtiger_user2role
            ->join('vtiger_role', self::USER_ROLE_TABLE . '.roleid', '=', 'vtiger_role.roleid')
            ->join('vtiger_crmentity', function ($join) {
                $join->on('vtiger_role.roleid', '=', 'vtiger_crmentity.crmid')
                    ->where('vtiger_crmentity.setype', '=', 'Roles');
            })
            ->where(self::USER_ROLE_TABLE . '.userid', $userId)
            ->where(self::CRMENTITY_TABLE . '.deleted', 0)
            ->select(
                'vtiger_role.roleid',
                'vtiger_role.rolename',
                'vtiger_role.depth',
                'vtiger_role.parentrole',
                'vtiger_role.allowassignedrecordsto as sharing_rule'
            )
            ->first();

        if (!$roleData) {
            return null;
        }

        return [
            'role_id' => (string) $roleData->roleid,
            'rolename' => $roleData->rolename,
            'depth' => (int) $roleData->depth,
            'parentrole' => $roleData->parentrole,
            'sharing_rule' => (int) $roleData->sharing_rule,
        ];
    }

    /**
     * Fetch all hierarchical roles formatted for UI selection.
     *
     * @return array<RoleOptionResponse>
     */
    public function findAllAvailable(): array
    {
        $results = $this->query()
        ->leftjoin(self::CRMENTITY_TABLE, function ($join) {
            $join->on('vtiger_role.roleid', '=', 'vtiger_crmentity.crmid')
                ->where('vtiger_crmentity.setype', '=', 'Roles')
                ->where('vtiger_crmentity.deleted', 0);
        })
        ->leftJoin(self::ROLE2PROFILE_TABLE, 'vtiger_role.roleid', '=', 'vtiger_role2profile.roleid')
        ->where(self::CRMENTITY_TABLE . '.deleted', 0)
        ->select(
            'vtiger_role.roleid',
            'vtiger_role.rolename',
            'vtiger_role.depth',
            'vtiger_role.parentrole',
            'vtiger_role2profile.profileid as profile_id'
        )
        ->orderBy('vtiger_role.depth', 'asc')
        ->orderBy('vtiger_role.rolename', 'asc')
        ->get();

        Log::debug('RoleRepository::findAllAvailable', [
        'count' => $results->count(),
        'first' => $results->first(),
        'sql' => $results->toArray(),
    ]);
        return $results
        ->map(fn($row) => RoleOptionResponse::fromDbRow($row))
        ->toArray();
    }

    public function getUserRoleId(int $userId): ?string
    {
        $result = DB::connection(self::CONNECTION)
            ->table('vtiger_user2role')
            ->where('userid', $userId)
            ->value('roleid');

        return $result ? (string) $result : null;
    }

    /**
     * Count how many users are currently assigned to this role.
     */
    public function countUsersAssigned(string $roleId): int
    {
        return DB::connection(self::CONNECTION)
            ->table(self::USER_ROLE_TABLE)
            ->where('roleid', $roleId)
            ->count();
    }

    /**
     * Create a new hierarchical role.
     * Returns the created roleid.
     */
    public function create(string $roleId, string $name, string $parentRole, int $depth, int $sharingRule): string
    {
        $this->query()->insert([
            'roleid' => $roleId,
            'rolename' => $name,
            'parentrole' => $parentRole,
            'depth' => $depth,
            'allowassignedrecordsto' => $sharingRule,
        ]);

        return $roleId;
    }

    /**
     * Update role properties.
     * Expected keys: rolename, parentrole, depth, allowassignedrecordsto
     */
    public function update(string $roleId, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $updated = $this->query()
            ->where('roleid', $roleId)
            ->update($data);

        return $updated > 0;
    }

    public function updateName(string $roleId, string $newName): bool
    {
        return DB::connection(self::CONNECTION)
            ->table(self::ROLE_TABLE)
            ->where('roleid', $roleId)
            ->update(['rolename' => $newName]);
    }

    /**
     * Hard-delete a role.
     * Note: UseCase layer should guarantee no users/children are assigned before calling this.
     */
    public function delete(string $roleId): bool
    {
        return $this->query()
            ->where('roleid', $roleId)
            ->delete() > 0;
    }

    /**
     * Delete a role with cascade cleanup.
     * 
     * @param string $roleId
     * @param int $authenticatedUserId
     * @param bool $force If true, delete even with user assignments (reassign to parent)
     * @return bool
     * 
     * @throws RuntimeException If role has assignments and force=false
     */
    public function deleteWithCascade(string $roleId, int $authenticatedUserId, bool $force = false): bool
    {
        // 1. Validate that the role exists and is not the root
        $role = DB::connection(self::CONNECTION)
            ->table(self::ROLE_TABLE)
            ->where('roleid', $roleId)
            ->first();

        if (!$role) {
            throw new RuntimeException("Role '{$roleId}' not found or already deleted");
        }

        if ($roleId === 'H1') {
            throw new RuntimeException('Cannot delete the root Organization role (H1)');
        }

        // 2. Validate assignments (unless forced)
        if (!$force) {
            if ($this->hasUserAssignments($roleId)) {
                throw new RuntimeException(
                    "Cannot delete role '{$roleId}': {$this->countUserAssignments($roleId)} user(s) assigned. " .
                        "Reassign users first or use force=true to auto-reassign to parent role."
                );
            }
            // Correction: Use ROLE2PROFILE_TABLE
            if ($this->hasProfileAssignments($roleId)) {
                throw new RuntimeException(
                    "Cannot delete role '{$roleId}': profile assignments exist in vtiger_role2profile. Remove them first."
                );
            }
        }

        // 3. Execute in atomic transaction
        return DB::connection(self::CONNECTION)->transaction(function () use ($roleId, $authenticatedUserId, $force, $role): bool {
            $now = now()->format('Y-m-d H:i:s');
            $success = true;

            // Phase A: Reassign users if force=true
            if ($force && $this->hasUserAssignments($roleId)) {
                $parentRoleId = $this->extractParentRoleId($role->parentrole);

                if ($parentRoleId) {
                    // Validate that parent exists and is not deleted
                    $parentValid = DB::connection(self::CONNECTION)
                        ->table(self::ROLE_TABLE)
                        ->join(self::CRMENTITY_TABLE, function ($join) {
                            $join->on('vtiger_role.roleid', '=', 'vtiger_crmentity.crmid')
                                ->where('vtiger_crmentity.setype', '=', 'Roles');
                        })
                        ->where('vtiger_role.roleid', $parentRoleId)
                        ->where('vtiger_crmentity.deleted', 0)
                        ->exists();

                    if ($parentValid) {
                        DB::connection(self::CONNECTION)
                            ->table(self::USER_ROLE_TABLE)
                            ->where('roleid', $roleId)
                            ->update(['roleid' => $parentRoleId]);
                    } else {
                        // Invalid parent: delete assignments
                        DB::connection(self::CONNECTION)
                            ->table(self::USER_ROLE_TABLE)
                            ->where('roleid', $roleId)
                            ->delete();
                    }
                } else {
                    // No parent: delete assignments
                    DB::connection(self::CONNECTION)
                        ->table(self::USER_ROLE_TABLE)
                        ->where('roleid', $roleId)
                        ->delete();
                }
            }

            // Phase B: Delete profile assignments
            // Correction: Use ROLE2PROFILE_TABLE
            DB::connection(self::CONNECTION)
                ->table(self::ROLE2PROFILE_TABLE)  // vtiger_role2profile
                ->where('roleid', $roleId)
                ->delete();

            // Phase C: Hard-delete the role from vtiger_role
            DB::connection(self::CONNECTION)
                ->table(self::ROLE_TABLE)
                ->where('roleid', $roleId)
                ->delete();

            // Phase D: Clear Vtiger permission cache
            $this->clearVtigerPermissionCache();

            // Phase E: Audit logging
            Log::info('Role deleted with cascade', [
                'role_id' => $roleId,
                'role_name' => $role->rolename ?? 'unknown',
                'deleted_by' => $authenticatedUserId,
                'force' => $force,
                'timestamp' => $now,
            ]);

            return $success;
        });
    }

    /**
     * Cascade hierarchy path and depth updates to all descendants.
     */
    public function cascadePathUpdate(string $oldPrefix, string $newPrefix, int $depthOffset): int
    {
        return $this->query()
            ->where('parentrole', 'LIKE', $oldPrefix . '%')
            ->where('roleid', '!=', rtrim($oldPrefix, ':'))
            ->update([
                'parentrole' => DB::raw("REPLACE(parentrole, '{$oldPrefix}', '{$newPrefix}')"),
                'depth' => DB::raw("depth + {$depthOffset}")
            ]);
    }

    public function findSubordinateUserIds(string $roleId): array
    {
        if ($roleId === null) {
            return [];
        }
        
        // 1. Get the full path (parentrole) of the current role
        $currentRolePath = DB::connection(self::CONNECTION)
            ->table('vtiger_role')
            ->where('roleid', $roleId)
            ->value('parentrole');

        if (!$currentRolePath) {
            return [];
        }

        // 2. Find roles whose parentrole starts with the current path + '::'
        //    This ensures we only get direct/indirect descendants
        $subordinateRoleIds = DB::connection(self::CONNECTION)
            ->table('vtiger_role')
            ->where('parentrole', 'LIKE', $currentRolePath . '::%')  // FIX: '::%' not '%'
            ->where('roleid', '!=', $roleId)  // Exclude current role
            ->pluck('roleid');

        if ($subordinateRoleIds->isEmpty()) {
            return [];
        }

        // 3. Get users assigned to those subordinate roles
        return DB::connection(self::CONNECTION)
            ->table('vtiger_user2role')
            ->whereIn('roleid', $subordinateRoleIds)
            ->pluck('userid')
            ->map(fn($id) => (int) $id)  // Type safety: ensure integers
            ->toArray();
    }

    public function hasUserAssignments(string $roleId): bool
    {
        return DB::connection(self::CONNECTION)
            ->table(self::USER_ROLE_TABLE)
            ->where('roleid', $roleId)
            ->exists();
    }

    public function hasProfileAssignments(string $roleId): bool
    {
        return DB::connection(self::CONNECTION)
            ->table(self::ROLE2PROFILE_TABLE)
            ->where('roleid', $roleId)
            ->exists();
    }

    public function existsByName(string $name): bool
    {
        return DB::connection(self::CONNECTION)
            ->table(self::ROLE_TABLE)
            ->join(self::CRMENTITY_TABLE, function ($join) {
                $join->on('vtiger_role.roleid', '=', 'vtiger_crmentity.crmid')
                    ->where('vtiger_crmentity.setype', '=', 'Roles');
            })
            ->where('vtiger_role.rolename', $name)
            ->where('vtiger_crmentity.deleted', 0)
            ->exists();
    }

    /**
     * Base query builder for vtiger_role table.
     */
    protected function query(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(self::CONNECTION)->table(self::ROLE_TABLE);
    }

    /**
     * Extract direct parent role ID from parentrole path.
     * Example: 'H1::H2::H3::' → 'H2'
     */
    private function extractParentRoleId(string $parentRolePath): ?string
    {
        $parts = array_filter(explode('::', trim($parentRolePath, ':')));

        // The direct parent is the second-to-last element
        return count($parts) >= 2 ? $parts[count($parts) - 2] : null;
    }

    /**
     * Count user assignments for a role.
     */
    private function countUserAssignments(string $roleId): int
    {
        return DB::connection(self::CONNECTION)
            ->table(self::USER_ROLE_TABLE)
            ->where('roleid', $roleId)
            ->count();
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
                DB::connection(self::CONNECTION)->statement("DELETE FROM {$table}");
            } catch (\Exception $e) {
                // Ignore if table doesn't exist
            }
        }
    }
}