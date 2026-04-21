<?php

namespace App\Application\Repositories;

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
     * Update an existing crmentity record
     *
     * @param  int  $crmid  The entity ID to update
     * @param  array  $data  Fields to update (excluding managed fields)
     * @return bool True if rows were affected
     */
    public function update(int $crmid, array $data): bool;
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
    public function findById(int $crmid): ?array;

    

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
