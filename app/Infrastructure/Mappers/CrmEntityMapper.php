<?php
namespace App\Infrastructure\Mappers;

use App\Domain\Entities\CrmEntity;
use DateTime;
use stdClass;

/**
 * CrmEntityMapper - Infrastructure layer for CrmEntity mapping
 * 
 * Responsible for transforming between:
 * - Database rows (stdClass) ↔ Domain Entity (CrmEntity)
 * 
 * This mapper knows Vtiger table structure and handles field aliases.
 * 
 * @package App\Infrastructure\Mappers
 */
class CrmEntityMapper
{
    /**
     * Map a database row to a CrmEntity
     * 
     * @param stdClass|array $row Raw database result
     * @return CrmEntity
     */
    public static function fromDatabaseRow(stdClass|array $row): CrmEntity
    {
        $arr = is_object($row) ? (array) $row : $row;

        // Helper para fechas de Vtiger
        $parseDateTime = fn(?string $val): ?DateTime =>
            $val && $val !== '0000-00-00 00:00:00' ? new DateTime($val) : null;

        return new CrmEntity(
            crmid: (int) ($arr['crmid'] ?? 0),
            creatorId: (int) ($arr['smcreatorid'] ?? 0),
            ownerId: (int) ($arr['smownerid'] ?? 0),
            entityType: (string) ($arr['setype'] ?? ''),
            createdAt: $parseDateTime($arr['createdtime']) ?? new DateTime(),
            updatedAt: $parseDateTime($arr['modifiedtime']) ?? new DateTime(),
            modifiedBy: (int) ($arr['modifiedby'] ?? 0),
            description: $arr['description'] ?? null,
            viewedTime: $parseDateTime($arr['viewedtime']),
            status: $arr['status'] ?? null,
            version: (int) ($arr['version'] ?? 0),
            presence: (int) ($arr['presence'] ?? CrmEntity::PRESENCE_ENABLED),
            isDeleted: (int) ($arr['deleted'] ?? 0) === CrmEntity::DELETED_YES,
            groupId: isset($arr['smgroupid']) ? (int) $arr['smgroupid'] : null,
            source: $arr['source'] ?? null,
            label: $arr['label'] ?? null,
        );
    }

    /**
     * Map a CrmEntity to a database-ready array
     * 
     * @param CrmEntity $entity Domain entity
     * @return array<string, mixed> Array with snake_case keys for vtiger_crmentity
     */
    public static function toDatabaseRow(CrmEntity $entity): array
    {
        return [
            'crmid' => $entity->getId(),
            'smcreatorid' => $entity->getCreatorId(),
            'smownerid' => $entity->getOwnerId(),
            'setype' => $entity->getEntityType(),
            'createdtime' => $entity->getCreatedAt()->format('Y-m-d H:i:s'),
            'modifiedtime' => $entity->getUpdatedAt()->format('Y-m-d H:i:s'),
            'modifiedby' => $entity->getModifiedBy(),
            'description' => $entity->getDescription(),
            'viewedtime' => $entity->getViewedTime()?->format('Y-m-d H:i:s'),
            'status' => $entity->getStatus(),
            'version' => $entity->getVersion(),
            'presence' => $entity->getPresence(),
            'deleted' => $entity->isDeleted() ? 1 : 0,
            'smgroupid' => $entity->getGroupId(),
            'source' => $entity->getSource(),
            'label' => $entity->getLabel(),
        ];
    }
}