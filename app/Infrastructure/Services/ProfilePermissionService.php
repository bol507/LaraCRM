<?php

namespace App\Infrastructure\Services;

use App\Application\Repositories\ModuleRepositoryInterface;
use App\Application\Repositories\RoleProfileAssignmentRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service to resolve and check module permissions for user profiles.
 * 
 * Works with Vtiger's bitwise permission system stored in vtiger_profile2tab:
 * - 1 = Read, 2 = Write, 4 = Create, 8 = Delete
 * 
 * Example: permissions = 7 → read(1) + write(2) + create(4) = 7
 */
class ProfilePermissionService
{
    protected const CONNECTION = 'vtiger';
    protected const PROFILE2TAB_TABLE = 'vtiger_profile2tab';
    protected const CACHE_PREFIX = 'profile_perm';
    protected const CACHE_TTL = 300; // 5 minutes

    // Bitwise permission flags (Vtiger standard)
    private const READ = 1;
    private const WRITE = 2;
    private const CREATE = 4;
    private const DELETE = 8;

    // Human-readable permission names
    private const PERMISSION_NAMES = [
        self::READ => 'read',
        self::WRITE => 'write',
        self::CREATE => 'create',
        self::DELETE => 'delete',
    ];

    public function __construct(
        private readonly ModuleRepositoryInterface $moduleRepo,
        private readonly RoleProfileAssignmentRepositoryInterface $roleProfileRepo
    ) {}

    /**
     * Get raw bitwise permission value for profile+tabid.
     */
    public function getRawValue(int $profileId, int $tabid): ?int
    {
        $cacheKey = $this->cacheKey($profileId, $tabid);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($profileId, $tabid): ?int {
            return DB::connection(self::CONNECTION)
                ->table(self::PROFILE2TAB_TABLE)
                ->where('profileid', $profileId)
                ->where('tabid', $tabid)
                ->value('permissions');
        });
    }

    /**
     * Get raw bitwise permission value for a profile+module.
     * 
     * @param int $profileId Profile ID from vtiger_profile
     * @param int $tabid Module tabid from vtiger_tab
     * @return int|null Bitwise value (0-15) or null if module not configured
     */
    public function getRawPermissionValue(int $profileId, int $tabid): ?int
    {
        $cacheKey = $this->buildCacheKey($profileId, $tabid);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($profileId, $tabid): ?int {
            $value = DB::connection(self::CONNECTION)
                ->table(self::PROFILE2TAB_TABLE)
                ->where('profileid', $profileId)
                ->where('tabid', $tabid)
                ->value('permissions');

            return $value !== null ? (int) $value : null;
        });
    }

    /**
     * Obtiene los módulos activos y sus permisos para un perfil.
     * Este método reemplaza la necesidad de un UseCase.
     */
    public function getModulesWithPermissions(?int $profileId): array
    {
        $modules = $this->moduleRepo->getAllActive();

        // 🔒 Fallback seguro: si no hay perfil, denegar todos los permisos por defecto
        if ($profileId === null) {
            
            return array_map(fn($m) => [
                ...$m,
                'permissions' => ['read' => false, 'write' => false, 'create' => false, 'delete' => false]
            ], $modules);
        }

        $perms = $this->getRawPermissionsForProfile($profileId);

        $result = [];
        foreach ($modules as $tabid => $module) {
            $result[$tabid] = [
                'tabid'      => $tabid,
                'name'       => $module['name'],
                'tablabel'   => $module['tablabel'],
                'permissions' => $perms[$tabid] ?? ['read' => false, 'write' => false, 'create' => false, 'delete' => false]
            ];
        }

        return $result;
    }

    /**
     * Obtiene los permisos crudos de todos los módulos asignados a un perfil.
     * 
     * @param int $profileId
     * @return array<int, array{read: bool, write: bool, create: bool, delete: bool}>
     *         Key: tabid | Value: permisos parseados
     */
    public function getRawPermissionsForProfile(int $profileId): array
    {
        $cacheKey = "profile:permissions:{$profileId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($profileId) {
            try {
                // 🔒 Verificar que la tabla existe (Vtiger 8.x a veces la omite en migraciones)
                if (!$this->tableExists(self::PROFILE2TAB_TABLE)) {
                    
                    return [];
                }

                // 1. Traer permisos bitwise por módulo
                $rawPermissions = DB::connection(self::CONNECTION)
                    ->table(self::PROFILE2TAB_TABLE)
                    ->where('profileid', $profileId)
                    ->pluck('permissions', 'tabid')
                    ->toArray();

                // 2. Convertir integers bitwise → estructura booleana legible
                return array_map(fn($bitmask) => $this->parsePermissions((int) $bitmask), $rawPermissions);
            } catch (\Exception $e) {
                Log::error('Failed to fetch profile permissions', [
                    'profile_id' => $profileId,
                    'error' => $e->getMessage()
                ]);
                return []; // ✅ Fail-safe: sin permisos = denegar por defecto
            }
        });
    }

    /**
     * Check if profile has a specific permission flag for a module.
     * 
     * @param int $profileId
     * @param int $tabid
     * @param int $permissionFlag One of PERM_* constants
     * @return bool
     */
    public function hasPermission(int $profileId, int $tabid, int $permissionFlag): bool
    {
        $value = $this->getRawPermissionValue($profileId, $tabid);
        if ($value === null) {
            return false;
        }

        // Bitwise AND: checks if the specific bit is set
        return (bool) ($value & $permissionFlag);
    }

    /**
     * Check specific permission flag.
     */
    public function has(int $profileId, int $tabid, int $flag): bool
    {
        $value = $this->getRawValue($profileId, $tabid);
        return $value !== null && (bool) ($value & $flag);
    }

    // ==================== Convenience Methods ====================

    /**
     * Check if profile can READ records in a module.
     */
    public function canRead(int $profileId, int $tabid): bool
    {
        return $this->hasPermission($profileId, $tabid, self::READ);
    }

    /**
     * Check if profile can WRITE (edit) records in a module.
     */
    public function canWrite(int $profileId, int $tabid): bool
    {
        return $this->hasPermission($profileId, $tabid, self::WRITE);
    }

    /**
     * Check if profile can CREATE new records in a module.
     */
    public function canCreate(int $profileId, int $tabid): bool
    {
        return $this->hasPermission($profileId, $tabid, self::CREATE);
    }

    /**
     * Check if profile can DELETE records in a module.
     */
    public function canDelete(int $profileId, int $tabid): bool
    {
        return $this->hasPermission($profileId, $tabid, self::DELETE);
    }

    /**
     * Check if module is accessible (visible) for profile.
     * A module is accessible if it has at least READ permission.
     */
    public function canAccessModule(int $profileId, int $tabid): bool
    {
        return $this->canRead($profileId, $tabid);
    }

    // ==================== Batch Operations ====================

    /**
     * Get all permissions for a profile as structured array.
     * 
     * @return array<int, array{read: bool, write: bool, create: bool, delete: bool}>
     * Key: tabid, Value: permission flags
     */
    public function getAllPermissions(int $profileId): array
    {
        $results = DB::connection(self::CONNECTION)
            ->table(self::PROFILE2TAB_TABLE)
            ->where('profileid', $profileId)
            ->pluck('permissions', 'tabid')
            ->toArray();

        return array_map(fn($value) => $this->parsePermissions((int) $value), $results);
    }

    /**
     * Get ALL permissions for a profile, keyed by tabid.
     * Backend-agnostic: returns raw tabid keys.
     * 
     * @return array<int, array{read: bool, write: bool, create: bool, delete: bool}>
     */
    public function getAllForProfile(int $profileId): array
    {
        $results = DB::connection(self::CONNECTION)
            ->table(self::PROFILE2TAB_TABLE)
            ->where('profileid', $profileId)
            ->pluck('permissions', 'tabid')
            ->toArray();

        return array_map(fn($v) => $this->parse((int) $v), $results);
    }

    /**
     * Get permissions for specific tabids (batch query).
     */
    public function getForTabIds(int $profileId, array $tabids): array
    {
        if (empty($tabids)) return [];

        $results = DB::connection(self::CONNECTION)
            ->table(self::PROFILE2TAB_TABLE)
            ->where('profileid', $profileId)
            ->whereIn('tabid', $tabids)
            ->pluck('permissions', 'tabid')
            ->toArray();

        return array_map(fn($v) => $this->parse((int) $v), $results);
    }

    /**
     * Parse bitwise to structured array.
     */
    public function parse(int $value): array
    {
        return [
            'read' => (bool) ($value & self::READ),
            'write' => (bool) ($value & self::WRITE),
            'create' => (bool) ($value & self::CREATE),
            'delete' => (bool) ($value & self::DELETE),
        ];
    }

    /**
     * Get permissions for multiple modules in a single query.
     * 
     * @param int $profileId
     * @param array<int> $tabids
     * @return array<int, array{read: bool, write: bool, create: bool, delete: bool}>
     */
    public function getPermissionsForModules(int $profileId, array $tabids): array
    {
        if (empty($tabids)) {
            return [];
        }

        $results = DB::connection(self::CONNECTION)
            ->table(self::PROFILE2TAB_TABLE)
            ->where('profileid', $profileId)
            ->whereIn('tabid', $tabids)
            ->pluck('permissions', 'tabid')
            ->toArray();

        return array_map(fn($value) => $this->parsePermissions((int) $value), $results);
    }

    // ==================== Cache Management ====================

    /**
     * Clear cache for a specific profile+module combination.
     */
    public function clearCache(int $profileId, ?int $tabid = null): void
    {
        if ($tabid !== null) {
            // Clear single module cache
            Cache::forget($this->buildCacheKey($profileId, $tabid));
        } else {
            // Clear all modules for this profile (more expensive)
            // Note: This requires tracking keys or using cache tags
            // For now, we clear by pattern if driver supports it
            $this->clearProfileCacheAll($profileId);
        }
    }

    /**
     * Clear all cached permissions for a profile.
     * Fallback for drivers that don't support tags.
     */
    private function clearProfileCacheAll(int $profileId): void
    {
        // Option A: If using Redis with tags (Laravel 8+)
        // Cache::tags(["profile:{$profileId}"])->flush();

        // Option B: Manual key tracking (more reliable across drivers)
        $cacheKeys = Cache::get($this->getProfileCacheKeysKey($profileId), []);
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget($this->getProfileCacheKeysKey($profileId));
    }

    /**
     * Register a cache key for later cleanup.
     */
    private function registerCacheKey(int $profileId, string $cacheKey): void
    {
        $keysKey = $this->getProfileCacheKeysKey($profileId);
        $keys = Cache::get($keysKey, []);
        $keys[] = $cacheKey;
        Cache::put($keysKey, array_unique($keys), self::CACHE_TTL * 2);
    }

    private function getProfileCacheKeysKey(int $profileId): string
    {
        return self::CACHE_PREFIX . ":keys:{$profileId}";
    }

    // ==================== Helpers ====================

    /**
     * Build consistent cache key for profile+module.
     */
    private function buildCacheKey(int $profileId, int $tabid): string
    {
        $key = self::CACHE_PREFIX . ":{$profileId}:tab{$tabid}";
        $this->registerCacheKey($profileId, $key);
        return $key;
    }

    private function cacheKey(int $profileId, int $tabid): string
    {
        return self::CACHE_PREFIX . ":{$profileId}:{$tabid}";
    }



    /**
     * Parse bitwise integer to structured permission array.
     * 
     * @param int $value Bitwise value (0-15)
     * @return array{read: bool, write: bool, create: bool, delete: bool}
     */
    public function parsePermissions(int $value): array
    {
        return [
            'read' => (bool) ($value & self::READ),
            'write' => (bool) ($value & self::WRITE),
            'create' => (bool) ($value & self::CREATE),
            'delete' => (bool) ($value & self::DELETE),
        ];
    }

    /**
     * Verifica si una tabla existe en la base de datos conectada.
     */
    private function tableExists(string $table): bool
    {
        try {
            return DB::connection(self::CONNECTION)
                ->selectOne("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table])
                ->c > 0;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Convert permission array back to bitwise integer.
     * Useful for saving permissions.
     * 
     * @param array{read?: bool, write?: bool, create?: bool, delete?: bool} $permissions
     * @return int Bitwise value (0-15)
     */
    public function toBitwiseValue(array $permissions): int
    {
        $value = 0;

        if (!empty($permissions['read'])) $value |= self::READ;
        if (!empty($permissions['write'])) $value |= self::WRITE;
        if (!empty($permissions['create'])) $value |= self::CREATE;
        if (!empty($permissions['delete'])) $value |= self::DELETE;

        return $value;
    }

    /**
     * Get human-readable permission names for a bitwise value.
     * 
     * @param int $value
     * @return string[] Array of permission names: ['read', 'create']
     */
    public function getPermissionNames(int $value): array
    {
        $names = [];
        foreach (self::PERMISSION_NAMES as $bit => $name) {
            if ($value & $bit) {
                $names[] = $name;
            }
        }
        return $names;
    }
}
