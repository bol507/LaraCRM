<?php

namespace App\Infrastructure\Repositories\Core;

use App\Application\Repositories\CrmentityRepositoryInterface;
use App\Domain\Entities\CrmEntity;
use App\Infrastructure\Mappers\CrmEntityMapper;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Repository for vtiger_crmentity - the core entity table in Vtiger
 *
 * Responsible for:
 * - Inserting/updating core entity metadata (crmid, module, owner, timestamps)
 * - Soft delete management (deleted flag)
 * - Entity type resolution
 * 
 * Uses CrmEntityMapper for DB ↔ Domain transformation
 */
class CrmentityRepository implements CrmentityRepositoryInterface
{
    private const TABLE = 'vtiger_crmentity';

    public function __construct(
        private readonly string $connection = 'vtiger'
    ) {}

    // ==================== READ OPERATIONS (return Domain Entities) ====================

    /**
     * Get entity metadata by crmid as a Domain Entity
     * 
     * @param int $crmid The entity ID to retrieve
     * @return CrmEntity|null Domain entity if found and not deleted, null otherwise
     */
    public function findById(int $crmid): ?CrmEntity
    {
        try {
            $row = DB::connection($this->connection)
                ->table(self::TABLE)
                ->where('crmid', $crmid)
                ->where('deleted', 0)
                ->first();

            return $row ? CrmEntityMapper::fromDatabaseRow($row) : null;
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve crmentity {$crmid}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Get raw array data by crmid (for legacy compatibility or specific use cases)
     * 
     * @deprecated Use findById() which returns CrmEntity instead
     */
    public function findByIdAsArray(int $crmid): ?array
    {
        $result = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('crmid', $crmid)
            ->where('deleted', 0)
            ->first();

        return $result ? (array) $result : null;
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

    // ==================== WRITE OPERATIONS (accept arrays/DTOs, use mapper internally) ====================

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

        // Use mapper to normalize data before insertion
        $prepared = CrmEntityMapper::toDatabaseRow(
            new CrmEntity(
                crmid: $data['crmid'],
                creatorId: $data['smcreatorid'],
                ownerId: $data['smownerid'],
                entityType: $data['setype'],
                createdAt: new \DateTime($data['createdtime']),
                updatedAt: new \DateTime($data['modifiedtime']),
                modifiedBy: $data['modifiedby'] ?? $data['smcreatorid'],
                description: $data['description'] ?? null,
                viewedTime: isset($data['viewedtime']) ? new \DateTime($data['viewedtime']) : null,
                status: $data['status'] ?? null,
                version: $data['version'] ?? 0,
                presence: $data['presence'] ?? CrmEntity::PRESENCE_ENABLED,
                isDeleted: ($data['deleted'] ?? 0) === 1,
                groupId: $data['smgroupid'] ?? null,
                source: $data['source'] ?? null,
                label: $data['label'] ?? null,
            )
        );

        DB::connection($this->connection)
            ->table(self::TABLE)
            ->insert($prepared);

        return $data['crmid'];
    }

    /**
     * @inheritDoc
     * 
     * @param CrmEntity $entity The entity with updated values
     * @return bool True if rows were affected
     */
    public function updateFromEntity(CrmEntity $entity): bool
    {
        // Mapper: Entity → DB array
        $data = CrmEntityMapper::toDatabaseRow($entity);

        // Remove immutable/managed fields
        $sanitized = array_diff_key($data, [
            'crmid' => true,
            'createdtime' => true,
            'deleted' => true,  // Use delete() for soft deletes
        ]);

        if (empty($sanitized)) {
            return true;
        }

        $affected = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('crmid', $entity->getId())
            ->where('deleted', 0)
            ->update([
                ...$sanitized,
                'modifiedtime' => $entity->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);

        return $affected > 0;
    }

    /**
     * @inheritDoc
     *
     * @param integer $crmid
     * @param array $data
     * @return boolean
     */
    public function update(int $crmid, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $sanitized = array_diff_key($data, [
            'createdtime' => true,
            'crmid' => true,
            'deleted' => true,
        ]);

        if (empty($sanitized)) {
            return true;
        }
        

        $affected = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('crmid', $crmid)
            ->where('deleted', 0)
            ->update([
                ...$sanitized,
                'modifiedtime' => now()->format('Y-m-d H:i:s'),
            ]);

        return $affected > 0;
    }

    /**
     * Soft delete an entity
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

    // ==================== UTILITY METHODS ====================

    /**
     * Update only the label field for an entity
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

    /**
     * Validate required fields for crmentity insertion
     *
     * @throws InvalidArgumentException
     */
    private function validateRequiredFields(array $data): void
    {
        $required = ['crmid', 'smcreatorid', 'smownerid', 'setype', 'createdtime', 'modifiedtime'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }
}
