<?php
// app/Application/Repositories/RoleProfileAssignmentRepositoryInterface.php

namespace App\Application\Repositories;

interface RoleProfileAssignmentRepositoryInterface
{
    /**
     * Assign a profile to a role in vtiger_profile2role.
     */
    public function assign(string $roleId, int $profileId): bool;
    
    /**
     * Remove profile assignment for a role.
     */
    public function deleteByRoleId(string $roleId): bool;
    
    /**
     * Get the profile currently assigned to a role.
     * 
     * @return array{profileid: string, profilename: string}|null
     */
    public function findByRoleId(string $roleId): ?array;
}