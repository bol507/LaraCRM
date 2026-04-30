<?php

namespace App\Application\Repositories;

use App\Domain\Entities\CrmEntity;

interface CrmentityRepositoryInterface
{

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
    public function insert(array $data): int;

    /**
     * Update an existing crmentity record using raw array
     *
     * Ideal for partial updates (PATCH semantics) where only changed fields are provided.
     * The repository handles sanitization of immutable fields and audit timestamps.
     *
     * @param  int  $crmid  The entity ID to update
     * @param  array<string, mixed>  $data  Fields to update (only provided fields will be updated)
     * @return bool True if rows were affected
     * 
     * @example
     * // Update only label and owner
     * $repo->update(123, [
     *     'label' => 'Nuevo título',
     *     'smownerid' => 456,
     * ]);
     */
    public function update(int $crmid, array $data): bool;

    /**
     * Update an existing crmentity record using a Domain Entity
     * 
     * Ideal for full entity updates where you have a complete CrmEntity instance.
     * Provides type-safety and ensures all entity invariants are respected.
     *
     * @param CrmEntity $entity The entity with updated values
     * @return bool True if rows were affected
     * 
     * @example
     * // Update with full entity
     * $entity = new CrmEntity(...);
     * $repo->updateFromEntity($entity);
     */
    public function updateFromEntity(CrmEntity $entity): bool;

    /**
     * Soft delete an entity
     * @param  int  $crmid  The entity ID to update
     * @return bool True if rows were affected
     */
    public function delete(int $crmid): bool;

    /**
     * Check if an entity exists and is not deleted
     */
    public function exists(int $crmid, string $module): bool;

    /**
     * Get entity metadata by crmid
     */
    public function findById(int $crmid): ?CrmEntity;



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
    public function updateLabel(int $crmid, string $label, ?string $setype = null): bool;
}
