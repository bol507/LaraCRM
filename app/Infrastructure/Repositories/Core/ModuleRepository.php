<?php
// app/Infrastructure/Repositories/VtigerModuleRepository.php

namespace App\Infrastructure\Repositories\Core;

use App\Application\Repositories\ModuleRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ModuleRepository implements ModuleRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const TABLE = 'vtiger_tab';
    protected const CACHE_KEY = 'vtiger:modules:active';
    protected const CACHE_TTL = 3600; // 1 hour

    public function getAllActive(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return DB::connection(self::CONNECTION)
                ->table(self::TABLE)
                ->where('presence', 0) // Only active/installed modules
                ->select('tabid', 'name', 'tablabel')
                ->orderBy('tabid')
                ->get()
                ->mapWithKeys(fn($row) => [
                    $row->tabid => [
                        'tabid' => (int) $row->tabid,
                        'name' => $row->name,
                        'tablabel' => $row->tablabel,
                    ]
                ])
                ->toArray();
        });
    }

    public function getById(int $tabid): ?array
    {
        $modules = $this->getAllActive();
        return $modules[$tabid] ?? null;
    }

    public function getTabIdByName(string $moduleName): ?int
    {
        $modules = $this->getAllActive();
        foreach ($modules as $mod) {
            if ($mod['name'] === $moduleName) {
                return $mod['tabid'];
            }
        }
        return null;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}