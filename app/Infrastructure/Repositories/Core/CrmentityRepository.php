<?php

namespace App\Infrastructure\Repositories\Core;

use App\Application\Repositories\CrmentityRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Repository for vtiger_crmentity - the core entity table in Vtiger
 *
 * Responsible for:
 * - Inserting/updating core entity metadata (crmid, module, owner, timestamps)
 * - Soft delete management (deleted flag)
 * - Entity type resolution
 */
class CrmentityRepository implements CrmentityRepositoryInterface
{
    private const TABLE = 'vtiger_crmentity';

    public function __construct(
        private readonly string $connection = 'vtiger'
    ) {}

    /**
     * Insert a new crmentity record
     *
     * @param array{
     *   crmid: int,
     *   smcreatorid: int,
     *   smownerid: int,
     *   setype: string,
     *   description?: string,
     *   createdtime: string,
     *   modifiedtime: string,
     *   deleted: int,
     *   label?: string
     * } $data
     * @return int The inserted crmid
     */
    public function insert(array $data): int
    {
        $this->validateRequiredFields($data);

        DB::connection($this->connection)
            ->table(self::TABLE)
            ->insert($this->prepareData($data));

        return $data['crmid'];
    }

    /**
     * Update an existing crmentity record
     *
     * @param  int  $crmid  The entity ID to update
     * @param  array  $data  Fields to update (excluding managed fields)
     * @return bool True if rows were affected
     */
    public function update(int $crmid, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        // Remove managed fields
        $sanitized = array_diff_key($data, [
            'modifiedtime' => true,
            'crmid' => true,
            'deleted' => true,  // Use delete() method for soft deletes
        ]);

        if (empty($sanitized)) {
            return true;
        }

        $affected = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('crmid', $crmid)
            ->where('deleted', 0)  // Don't update soft-deleted records
            ->update([
                ...$sanitized,
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
            ]);

        return $affected > 0;
    }

    /**
     * Soft delete an entity
     * @param  int  $crmid  The entity ID to update
     * @return bool True if rows were affected
     */
    public function delete(int $crmid): bool
    {
        $now = now()->format('Y-m-d H:i:s');
        $affected = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('crmid', $crmid)
            ->update([
                'deleted' => 1,
                'modifiedtime' => $now,
            ]);

        return $affected > 0;
    }

    /**
     * Check if an entity exists and is not deleted
     */
    public function exists(int $crmid, string $module): bool
    {
        return DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('crmid', $crmid)
            ->where('setype', $module)
            ->where('deleted', 0)
            ->exists();
    }

    /**
     * Get entity metadata by crmid
     */
    public function findById(int $crmid): ?array
    {
        $result = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('crmid', $crmid)
            ->where('deleted', 0)
            ->first();

        return $result ? (array) $result : null;
    }

    /**
     * Prepare data for insertion/update with defaults
     */
    private function prepareData(array $data): array
    {
        return [
            'crmid' => $data['crmid'],
            'smcreatorid' => $data['smcreatorid'],
            'smownerid' => $data['smownerid'],
            'setype' => $data['setype'],
            'description' => $data['description'] ?? '',
            'createdtime' => $data['createdtime'] ?? now()->format('Y-m-d H:i:s'),
            'modifiedtime' => $data['modifiedtime'] ?? now()->format('Y-m-d H:i:s'),
            'deleted' => $data['deleted'] ?? 0,
            'label' => $data['label'] ?? null,
        ];
    }

    /**
     * Validate required fields for crmentity
     *
     * @throws \InvalidArgumentException
     */
    private function validateRequiredFields(array $data): void
    {
        $required = ['crmid', 'smcreatorid', 'smownerid', 'setype', 'createdtime', 'modifiedtime'];

        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }

    /**
     * Update only the label field for an entity
     *
     * Used for syncing display labels when related fields change
     *
     * @param  int  $crmid  Entity ID
     * @param  string  $label  New label value
     * @param  string|null  $setype  Optional module filter for safety
     * @return bool True if update was successful
     */
    public function updateLabel(int $crmid, string $label, ?string $setype = null): bool
    {
        $query = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('crmid', $crmid)
            ->where('deleted', 0);

        if ($setype !== null) {
            $query->where('setype', $setype);
        }

        $affected = $query->update([
            'label' => $label,
            'modifiedtime' => now()->format('Y-m-d H:i:s'),
        ]);

        return $affected > 0;
    }
}
