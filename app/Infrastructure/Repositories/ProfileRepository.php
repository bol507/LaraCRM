<?php
// app/Infrastructure/Repositories/ProfileRepository.php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\ModuleRepositoryInterface;
use App\Application\Repositories\ProfileRepositoryInterface;
use App\Infrastructure\Services\ProfilePermissionService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $connection = DB::connection(self::CONNECTION);

        try {
            return $connection->transaction(function () use ($connection, $profileId, $modules): bool {

                // 1. Get active modules (direct query, without tableExists)
                $allTabs = $connection
                    ->table(self::TAB_TABLE)
                    ->where('presence', 0)
                    ->pluck('tabid')
                    ->toArray();

                if (empty($allTabs)) {
                    Log::warning('No active modules found in vtiger_tab', ['profile_id' => $profileId]);
                    return true;
                }

                // 2. Map received modules for O(1) lookup
                $modulesByTabid = collect($modules)->keyBy('tabid');

                $upserts = [];
                $processedCount = 0;

                foreach ($allTabs as $tabid) {
                    $moduleConfig = $modulesByTabid->get($tabid);

                    // Calculate bitwise value
                    $permissionValue = $moduleConfig
                        ? $this->calculatePermissionValue($moduleConfig['permissions'] ?? [])
                        : 0;

                    $upserts[] = [
                        'profileid'   => $profileId,
                        'tabid'       => $tabid,
                        'permissions' => $permissionValue,
                    ];

                    if ($moduleConfig) $processedCount++;
                }

                // 3. Execute batch UPSERT
                if (!empty($upserts)) {
                    $this->executeBatchUpsert($connection, $upserts);
                }

                
                
                $permService = app(ProfilePermissionService::class);
                if ($permService) {
                    $permService->clearCache($profileId);
                }

                // Also clear module cache if needed
                $moduleRepo = app(ModuleRepositoryInterface::class);
                if ($moduleRepo) {
                    $moduleRepo->clearCache();
                }
                
                return true;
            });
        } catch (\Exception $e) {
            Log::error('Failed to update profile permissions', [
                'profile_id' => $profileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'modules_count' => count($modules),
            ]);
            return false; // Return false so frontend knows it failed
        }
    }

    private function executeBatchUpsert($connection, array $records): void
    {
        if (empty($records)) return;

        $values = [];
        $bindings = [];

        foreach ($records as $record) {
            $values[] = '(?, ?, ?)';
            $bindings[] = $record['profileid'];
            $bindings[] = $record['tabid'];
            $bindings[] = $record['permissions'];
        }

        $sql = "INSERT INTO " . self::PROFILE2TAB_TABLE . " (profileid, tabid, permissions) 
            VALUES " . implode(', ', $values) . "
            ON DUPLICATE KEY UPDATE permissions = VALUES(permissions)";

        $connection->statement($sql, $bindings);
    }

    /**
     * Executes batch UPSERT in vtiger_profile2tab.
     * Uses ON DUPLICATE KEY UPDATE for efficiency.
     */
    private function batchUpsertPermissions(int $profileId, array $records): void
    {
        if (empty($records)) return;

        $connection = DB::connection(self::CONNECTION);

        // Build SQL for INSERT ... ON DUPLICATE KEY UPDATE
        $values = [];
        $bindings = [];

        foreach ($records as $record) {
            $values[] = '(?, ?, ?)';
            $bindings[] = $record['profileid'];
            $bindings[] = $record['tabid'];
            $bindings[] = $record['permissions'];
        }

        $sql = "INSERT INTO " . self::PROFILE2TAB_TABLE . " (profileid, tabid, permissions) 
            VALUES " . implode(', ', $values) . "
            ON DUPLICATE KEY UPDATE permissions = VALUES(permissions)";

        $connection->statement($sql, $bindings);
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
    public function create(string $name, ?string $roleId = null): array
    {
        $connection = DB::connection(self::CONNECTION);

        return $connection->transaction(function () use ($connection, $name, $roleId) {
            // 1. Create profile in vtiger_profile
            $profileId = $connection->table(self::PROFILE_TABLE)->insertGetId([
                'profilename'   => $name,
            ]);

            // 2. Populate default permissions (0 = hidden/no access)
            $activeTabs = $connection
                ->table(self::TAB_TABLE)
                ->where('presence', 0)
                ->pluck('tabid')
                ->toArray();

            $defaultPerms = array_map(fn($tabid) => [
                'profileid'   => $profileId,
                'tabid'       => $tabid,
                'permissions' => 0, // No initial access
            ], $activeTabs);

            if (!empty($defaultPerms)) {
                $connection->table(self::PROFILE2TAB_TABLE)->insert($defaultPerms);
            }

            // 3. Optional: Immediately assign to a role (hierarchy)
            if ($roleId) {
                $connection->table('vtiger_role2profile')->insert([
                    'roleid'    => $roleId,
                    'profileid' => $profileId,
                ]);
            }

            return [
                'profileid' => $profileId,
                'name'      => $name,
                'role_id'   => $roleId,
            ];
        });
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
        if (in_array('read', $permissions, true))   $value |= 1;
        if (in_array('write', $permissions, true))  $value |= 2;
        if (in_array('create', $permissions, true)) $value |= 4;
        if (in_array('delete', $permissions, true)) $value |= 8;

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