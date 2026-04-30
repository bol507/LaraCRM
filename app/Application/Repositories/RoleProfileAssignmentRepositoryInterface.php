<?php
// app/Application/Repositories/RoleProfileAssignmentRepositoryInterface.php

namespace App\Application\Repositories;

interface RoleProfileAssignmentRepositoryInterface
{
    /**
     * Assign a profile to a role using UPSERT to avoid duplicates.
     * 
     * @param string $roleId
     * @param string $profileId
     * @return bool True if assignment was successful
     */
    public function assign(string $roleId, string $profileId): bool;
    
    /**
     * Remove profile assignment for a role.
     */
    public function deleteByRoleId(string $roleId): bool;
    
    /**
     *  Remove specific role-profile assignment.
     *
     * @param string $roleId
     * @param string $profileId
     * @return boolean
     */
    public function delete(string $roleId, string $profileId): bool;
    /**
     * Get the profile currently assigned to a role.
     * 
     * @return array{profileid: string, profilename: string}|null
     */
    public function findByRoleId(string $roleId): ?array;

    /**
     * Get the profile ID assigned to a user's hierarchical role.
     * 
     
     * 
     * @param int $userId
     * @return int|null Profile ID if assigned, null otherwise
     */
    public function findProfileIdByUserId(int $userId): ?int;
}