<?php

namespace App\Infrastructure\Repositories;

use App\Application\DTOs\Role\RoleOptionResponse;
use App\Application\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RoleRepository implements RoleRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const ROLE_TABLE = 'vtiger_role';
    protected const USER_ROLE_TABLE = 'vtiger_user2role';
    protected const PROFILE_ROLE_TABLE = 'vtiger_profile2role';
    protected const CRMENTITY_TABLE = 'vtiger_crmentity';

    /**
     * Fetch all roles ordered by hierarchy depth.
     */
    public function findAll(): array
    {
        return $this->query()
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
            ->where(self::USER_ROLE_TABLE . '.userid', $userId)
            //->where('vtiger_role.deleted', 0)
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
        $cacheKey = 'roles_available_list';
        $ttl = 300; // 5 minutes

        return Cache::remember($cacheKey, $ttl, function () {
            return $this->query()
                ->select('roleid', 'rolename', 'depth', 'parentrole')
                ->orderBy('depth', 'asc')
                ->orderBy('rolename', 'asc')
                ->get()
                ->map(fn($row) => RoleOptionResponse::fromDbRow($row))
                ->toArray();
        });
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
        // 1. Validar que el rol existe y no es el raíz
        $role = DB::connection(self::CONNECTION)
            ->table(self::ROLE_TABLE)
            ->where('roleid', $roleId)
            //->where('deleted', 0)
            ->first();

        if (!$role) {
            throw new RuntimeException("Role '{$roleId}' not found or already deleted");
        }

        if ($roleId === 'H1') {
            throw new RuntimeException('Cannot delete the root Organization role (H1)');
        }

        // 2. Validar asignaciones (a menos que sea forzado)
        if (!$force) {
            if ($this->hasUserAssignments($roleId)) {
                throw new RuntimeException(
                    "Cannot delete role '{$roleId}': {$this->countUserAssignments($roleId)} user(s) assigned. " .
                    "Reassign users first or use force=true to auto-reassign to parent role."
                );
            }
            if ($this->hasProfileAssignments($roleId)) {
                throw new RuntimeException(
                    "Cannot delete role '{$roleId}': profile assignments exist. Remove them first."
                );
            }
        }

        // 3. Ejecutar en transacción atómica
        return DB::connection(self::CONNECTION)->transaction(function () use ($roleId, $authenticatedUserId, $force, $role): bool {
            $now = now()->format('Y-m-d H:i:s');

            // === FASE A: Reasignar usuarios si force=true ===
            if ($force && $this->hasUserAssignments($roleId)) {
                $parentRoleId = $this->extractParentRoleId($role->parentrole);
                
                if ($parentRoleId) {
                    // Reasignar usuarios al rol padre
                    DB::connection(self::CONNECTION)
                        ->table(self::USER_ROLE_TABLE)
                        ->where('roleid', $roleId)
                        ->update(['roleid' => $parentRoleId]);
                } else {
                    // Sin padre: eliminar asignaciones (usuarios quedarán sin rol jerárquico)
                    DB::connection(self::CONNECTION)
                        ->table(self::USER_ROLE_TABLE)
                        ->where('roleid', $roleId)
                        ->delete();
                }
            }

            // === FASE B: Eliminar asignaciones de perfiles ===
            DB::connection(self::CONNECTION)
                ->table(self::PROFILE_ROLE_TABLE)
                ->where('roleid', $roleId)
                ->delete();

            // === FASE C: Soft-delete del rol en vtiger_role ===
            $updated = DB::connection(self::CONNECTION)
                ->table(self::ROLE_TABLE)
                ->where('roleid', $roleId)
                ->update([
                    //'deleted' => 1,
                    'date_modified' => $now,
                    'modified_user_id' => (string) $authenticatedUserId,
                ]);

            // === FASE D: Actualizar crmentity si existe entrada para el rol ===
            DB::connection(self::CONNECTION)
                ->table(self::CRMENTITY_TABLE)
                ->where('crmid', $roleId)
                ->where('setype', 'Roles')
                ->update([
                    'deleted' => 1,
                    'modifiedtime' => $now,
                ]);

            // === FASE E: Limpiar caché de permisos de Vtiger ===
            $this->clearVtigerPermissionCache();

            return $updated > 0;
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
        $role = $this->findById($roleId);
        if (!$role) {
            return [];
        }
        return DB::connection('vtiger')
            ->table('vtiger_user2role AS u2r')
            ->join('vtiger_users AS u', 'u2r.userid', '=', 'u.id')
            ->join('vtiger_role AS r', 'u2r.roleid', '=', 'r.roleid')
            ->where('r.parentrole', 'LIKE', $role['parentrole'] . '%')
            ->where('r.roleid', '!=', $roleId)  // exclude current role
            ->pluck('u.id')
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
            ->table(self::PROFILE_ROLE_TABLE)
            ->where('roleid', $roleId)
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
        
        // El padre directo es el penúltimo elemento
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
                // Ignorar si la tabla no existe
            }
        }
    }

}
