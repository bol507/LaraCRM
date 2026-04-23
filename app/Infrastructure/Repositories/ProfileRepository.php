<?php
// app/Infrastructure/Repositories/ProfileRepository.php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\ProfileRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProfileRepository implements ProfileRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const PROFILE_TABLE = 'vtiger_profile';
    protected const PROFILE2TAB_TABLE = 'vtiger_profile2tab';
    protected const PROFILE2PERM_TABLE = 'vtiger_profile2standardpermissions';
    protected const TAB_TABLE = 'vtiger_tab';

    public function findAll(): array
    {
        $profiles = $this->query()
            ->where('profileid', '>', 0) // Exclude system profile
            ->orderBy('profilename', 'asc')
            ->get();

        return $profiles->map(function ($profile) {
            return [
                'profileid' => (string) $profile->profileid,
                'profilename' => $profile->profilename,
                'modules' => $this->getProfileModules((int) $profile->profileid),
            ];
        })->toArray();
    }

    public function findById(int $profileId): ?array
    {
        $profile = $this->query()
            ->where('profileid', $profileId)
            ->first();

        if (!$profile) {
            return null;
        }

        return [
            'profileid' => (string) $profile->profileid,
            'profilename' => $profile->profilename,
            'modules' => $this->getProfileModules($profileId),
        ];
    }

    public function updateName(int $profileId, string $name): bool
    {
        return $this->query()
            ->where('profileid', $profileId)
            ->update(['profilename' => $name]) > 0;
    }

    public function updateModulePermissions(int $profileId, array $modules): bool
    {
        return DB::connection(self::CONNECTION)->transaction(function () use ($profileId, $modules): bool {
            // 1. Get all active modules in the system (presence = 0)
            $allTabs = $this->query()
                ->where('presence', 0) // Only installed and active modules
                ->pluck('tabid')
                ->toArray();

            if (empty($allTabs)) {
                // No modules in the system → nothing to update
                return true;
            }

            $allowedTabIds = array_column($modules, 'tabid');
            $updates = [];
            $inserts = [];

            // 2. Prepare batch updates/inserts
            foreach ($allTabs as $tabid) {
                $moduleConfig = collect($modules)->firstWhere('tabid', $tabid);
                
                if ($moduleConfig) {
                    // Module allowed: calculate bitwise permission value
                    $permissionValue = $this->calculatePermissionValue($moduleConfig['permissions'] ?? []);
                    
                    $updates[] = [
                        'profileid' => $profileId,
                        'tabid' => $tabid,
                        'permissions' => $permissionValue,
                    ];
                } else {
                    // Module NOT allowed: hide with permissions = 0
                    $inserts[] = [
                        'profileid' => $profileId,
                        'tabid' => $tabid,
                        'permissions' => 0,
                    ];
                }
            }

            // 3. Execute batch updates (more efficient than individual loops)
            if (!empty($updates)) {
                $this->batchUpsert($profileId, $updates);
            }

            // 4. Insert hidden modules (only if they don't already exist)
            if (!empty($inserts)) {
                $this->batchInsertHidden($profileId, $inserts);
            }

            return true;
        });
    }

    /**
     * @inheritDoc
     */
    public function existsByName(string $name): bool
    {
        return $this->query()
            ->where('profilename', $name)
            ->exists();
    }

    /**
     * @inheritDoc
     */
    public function create(string $name, string $description = ''): int
    {
        $now = now()->format('Y-m-d H:i:s');

        return $this->query()
            ->insertGetId([
                'profilename' => $name,
                'description' => $description,
                'createdtime' => $now,
                'modifiedtime' => $now,
            ]);
    }

    /**
     * Parse Vtiger's bitwise permission value back to string array.
     * Used for reading permissions in findAll()/findById().
     * 
     * @param int $value Bitwise permission value (0-15)
     * @return string[] Array of permission strings
     */
    public function parsePermissionValue(int $value): array
    {
        $permissions = [];
        $map = [
            1 => 'read',
            2 => 'write',
            4 => 'create',
            8 => 'delete',
        ];
        
        foreach ($map as $bit => $name) {
            if ($value & $bit) {
                $permissions[] = $name;
            }
        }
        
        return $permissions;
    }

    /**
     * Batch upsert for enabled modules (update or insert).
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for efficiency.
     */
    private function batchUpsert(int $profileId, array $records): void
    {
        if (empty($records)) {
            return;
        }

        $connection = DB::connection(self::CONNECTION);
        $table = self::PROFILE2TAB_TABLE;

        // Build manual query for ON DUPLICATE KEY UPDATE
        // (Laravel's upsert() is not available in all versions with multiple connections)
        $values = [];
        $bindings = [];
        
        foreach ($records as $record) {
            $values[] = '(?, ?, ?)';
            $bindings[] = $record['profileid'];
            $bindings[] = $record['tabid'];
            $bindings[] = $record['permissions'];
        }

        $sql = "INSERT INTO {$table} (profileid, tabid, permissions) 
                VALUES " . implode(', ', $values) . "
                ON DUPLICATE KEY UPDATE permissions = VALUES(permissions)";

        $connection->statement($sql, $bindings);
    }

    /**
     * Insert hidden modules only if they don't already exist.
     */
    private function batchInsertHidden(int $profileId, array $records): void
    {
        if (empty($records)) {
            return;
        }

        $connection = DB::connection(self::CONNECTION);
        $table = self::PROFILE2TAB_TABLE;

        // Filter only those that don't already exist
        $existing = $connection->table($table)
            ->where('profileid', $profileId)
            ->pluck('tabid')
            ->toArray();

        $toInsert = array_filter($records, fn($r) => !in_array($r['tabid'], $existing, true));

        if (!empty($toInsert)) {
            $connection->table($table)->insert($toInsert);
        }
    }

    /**
     * Convert permission strings to Vtiger's bitwise integer value.
     * 
     * Vtiger permission bits:
     * 1 = Read, 2 = Write, 4 = Create, 8 = Delete
     * 
     * Example: ['read', 'write', 'create'] → 1+2+4 = 7
     * 
     * @param string[] $permissions Array of permission strings
     * @return int Bitwise permission value (0-15)
     */
    private function calculatePermissionValue(array $permissions): int
    {
        $value = 0;
        $map = [
            'read' => 1,
            'write' => 2,
            'create' => 4,
            'delete' => 8,
        ];
        
        foreach ($permissions as $perm) {
            $perm = strtolower(trim($perm));
            if (isset($map[$perm])) {
                $value += $map[$perm];
            }
        }
        
        return $value;
    }

    /**
     * Fetch module permissions for a specific profile.
     * Adapted: No longer checks 'visible', assumes if permissions > 0 the module is active.
     */
    private function getProfileModules(int $profileId): array
    {
        // Get all records from the table for this profile
        $rows = DB::connection(self::CONNECTION)
            ->table(self::PROFILE2TAB_TABLE)
            ->join(self::TAB_TABLE, self::PROFILE2TAB_TABLE . '.tabid', '=', self::TAB_TABLE . '.tabid')
            ->where(self::PROFILE2TAB_TABLE . '.profileid', $profileId)
            ->where(self::PROFILE2TAB_TABLE . '.permissions', '>', 0)
            ->select(
                self::TAB_TABLE . '.tabid',
                self::TAB_TABLE . '.name as module_name',
                self::PROFILE2TAB_TABLE . '.permissions'
            )
            ->get();

        return $rows->map(function ($mod) {
            return [
                'tabid' => (int) $mod->tabid,
                'name' => $mod->module_name,
                'permissions' => $this->parsePermissionValue((int) $mod->permissions),
            ];
        })->toArray();
    }

    /**
     * Base query builder for vtiger_profile table.
     */
    protected function query(): Builder
    {
        return DB::connection(self::CONNECTION)->table(self::PROFILE_TABLE);
    }
}