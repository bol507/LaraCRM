<?php

namespace App\Infrastructure\Repositories\Core;

use Illuminate\Support\Facades\DB;

/**
 * Repository for vtiger_seactivityrel - many-to-many activity relationships
 */
class SeActivityRelRepository
{
    private const TABLE = 'vtiger_seactivityrel';
    
    public function __construct(
        private readonly string $connection = 'vtiger'
    ) {}
    
    /**
     * Link an activity to a CRM entity
     */
    public function link(int $activityId, int $crmid, string $setype): bool
    {
        // Check if relationship already exists
        if ($this->exists($activityId, $crmid)) {
            return true;
        }
        
        return DB::connection($this->connection)
            ->table(self::TABLE)
            ->insert([
                'activityid' => $activityId,
                'crmid' => $crmid,
                'setype' => $setype,
            ]) > 0;
    }
    
    /**
     * Unlink an activity from a CRM entity
     */
    public function unlink(int $activityId, int $crmid): bool
    {
        return DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('activityid', $activityId)
            ->where('crmid', $crmid)
            ->delete() > 0;
    }
    
    /**
     * Check if relationship exists
     */
    public function exists(int $activityId, int $crmid): bool
    {
        return DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('activityid', $activityId)
            ->where('crmid', $crmid)
            ->exists();
    }
    
    /**
     * Get all entities linked to an activity
     * 
     * @return array<array{crmid: int, setype: string}>
     */
    public function getLinkedEntities(int $activityId): array
    {
        return DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('activityid', $activityId)
            ->get(['crmid', 'setype'])
            ->map(fn($row) => (array) $row)
            ->all();
    }
    
    /**
     * Get all activities linked to an entity
     * 
     * @return array<int> List of activity IDs
     */
    public function getLinkedActivities(int $crmid, string $setype): array
    {
        return DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('crmid', $crmid)
            ->where('setype', $setype)
            ->pluck('activityid')
            ->map(fn($id) => (int) $id)
            ->all();
    }
}