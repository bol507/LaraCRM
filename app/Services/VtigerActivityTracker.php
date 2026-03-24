<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class VtigerActivityTracker
{
    /**
     * Log an activity in vtiger_modtracker_basic.
     *
     * @param string $module Vtiger module name (Project, Accounts, Quotes, etc.)
     * @param int $crmid Vtiger record ID
     * @param int $status Activity status: 0=created, 1=updated, 2=deleted, 3=restored, 4=transferred
     * @param int|null $userId User ID (defaults to current authenticated user)
     * @return int The ID of the inserted activity record
     */
    public static function log(
        string $module,
        int $crmid,
        int $status,
        ?int $userId = null
    ): int {
        
        $userId = $userId ?? \App\Services\CurrentUserService::idOr(1);
        
        return DB::connection('vtiger')
            ->table('vtiger_modtracker_basic')
            ->insertGetId([
                'crmid' => $crmid,
                'module' => $module,
                'whodid' => $userId,
                'changedon' => now(),
                'status' => $status,
            ]);
    }

    /**
     * Log a creation activity.
     *
     * @param string $module Vtiger module name
     * @param int $crmid Vtiger record ID
     * @param int|null $userId User ID (optional)
     * @return int The ID of the inserted activity record
     */
    public static function created(string $module, int $crmid, ?int $userId = null): int
    {
        return self::log($module, $crmid, 0, $userId);
    }

    /**
     * Log an update activity.
     *
     * @param string $module Vtiger module name
     * @param int $crmid Vtiger record ID
     * @param int|null $userId User ID (optional)
     * @return int The ID of the inserted activity record
     */
    public static function updated(string $module, int $crmid, ?int $userId = null): int
    {
        return self::log($module, $crmid, 1, $userId);
    }

    /**
     * Log a deletion activity.
     *
     * @param string $module Vtiger module name
     * @param int $crmid Vtiger record ID
     * @param int|null $userId User ID (optional)
     * @return int The ID of the inserted activity record
     */
    public static function deleted(string $module, int $crmid, ?int $userId = null): int
    {
        return self::log($module, $crmid, 2, $userId);
    }

    /**
     * Log a restoration activity.
     *
     * @param string $module Vtiger module name
     * @param int $crmid Vtiger record ID
     * @param int|null $userId User ID (optional)
     * @return int The ID of the inserted activity record
     */
    public static function restored(string $module, int $crmid, ?int $userId = null): int
    {
        return self::log($module, $crmid, 3, $userId);
    }

    /**
     * Log a transfer activity.
     *
     * @param string $module Vtiger module name
     * @param int $crmid Vtiger record ID
     * @param int|null $userId User ID (optional)
     * @return int The ID of the inserted activity record
     */
    public static function transferred(string $module, int $crmid, ?int $userId = null): int
    {
        return self::log($module, $crmid, 4, $userId);
    }
}
