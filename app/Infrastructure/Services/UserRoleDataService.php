<?php
// app/Infrastructure/Services/UserRoleDataService.php

namespace App\Infrastructure\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Service to fetch raw hierarchical role data for users.
 * NO business logic, NO mapping. Just data retrieval.
 */
class UserRoleDataService
{
    protected const CONNECTION = 'vtiger';
    protected const CACHE_TTL = 300; // 5 minutes
    protected const CACHE_PREFIX = 'user_role_raw';

    /**
     * Fetch raw role data for a user.
     * Returns exactly what's in vtiger_role, no transformations.
     */
    public static function fetchForUser(int $userId, bool $useCache = true): array
    {
        if (!$useCache) {
            return self::queryRoleData($userId);
        }    
    
        return Cache::remember(
            self::CACHE_PREFIX . ".{$userId}",
            self::CACHE_TTL,
            fn() => self::queryRoleData($userId)
        );
    }

    public static function clearCache(int $userId): void
    {
        Cache::forget("user_role_raw.{$userId}");
    }

    public static function clearAllCache(): void
    {
        // if (Cache::getStore() instanceof TaggableStore) {
        //     Cache::tags(['user_roles'])->flush();
        // }
        // Cache::tags(['user_roles'])->flush();
        
        // Fallback universal:
        Cache::getStore()->flush(); // ⚠️ only for testing
    }

    private static function queryRoleData(int $userId): array
    {
        $roleData = DB::connection(self::CONNECTION)
            ->table('vtiger_user2role')
            ->join('vtiger_role', 'vtiger_user2role.roleid', '=', 'vtiger_role.roleid')
            ->where('vtiger_user2role.userid', $userId)
            ->select(
                'vtiger_role.roleid',
                'vtiger_role.rolename',
                'vtiger_role.depth',
                'vtiger_role.parentrole',
                'vtiger_role.allowassignedrecordsto as sharing_rule'
            )
            ->first();

        return [
            'role_id' => $roleData?->roleid ?? null,
            'rolename' => $roleData?->rolename ?? null,
            'depth' => (int) ($roleData?->depth ?? 0),
            'parentrole' => $roleData?->parentrole ?? null,
            'sharing_rule' => (int) ($roleData?->sharing_rule ?? 1),
        ];
    }
}