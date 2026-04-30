<?php
namespace App\Application\Repositories;

interface RoleRepositoryInterface
{
    /**
     * Fetch all roles ordered by hierarchy depth.
     */
    public function findAll(): array;
    /**
     * Find a single role by its ID.
     */
    public function findById(string $roleId): ?array;
    /**
     * Fetch direct child roles of a given parent.
     */
    public function findByParentId(string $parentId): array;
    /**
     * Find role data for a given user
     *
     * @param integer $userId
     * @return array|null
     */
    public function findByUserId(int $userId): ?array;
    /**
     * Fetch all hierarchical roles for UI selection.
     *
     * @return array<RoleOptionResponse>
     */
    public function findAllAvailable(): array;

    /**
     * Find all users assigned to a role and its descendants.
     *
     * @param string $roleId
     * @return array
     */
    public function findSubordinateUserIds(string $roleId): array;

    /**
     * Get the role ID for a specific user
     *
     * @param integer $userId
     * @return string|null
     */
    public function getUserRoleId(int $userId): ?string;

    /**
     * Count how many users are currently assigned to this role.
     */
    public function countUsersAssigned(string $roleId): int;
    /**
     * Create a new hierarchical role.
     * Returns the created roleid.
     */
    public function create(string $roleId, string $name, string $parentRole, int $depth, int $sharingRule): string;
    /**
     * Update role properties.
     * Expected keys: rolename, parentrole, depth, allowassignedrecordsto
     */
    public function update(string $roleId, array $data): bool;

    /**
     * Update role name.
     *
     * @param string $roleId
     * @param string $newName
     * @return boolean
     */
    public function updateName(string $roleId, string $newName): bool;
    /**
     * Hard-delete a role.
     * Note: UseCase layer should guarantee no users/children are assigned before calling this.
     */
    public function delete(string $roleId): bool;

    

    /**
     * Cascade hierarchy path and depth updates to all descendants.
     * Updates parentrole path prefix and adjusts depth by offset.
     */
    public function cascadePathUpdate(string $oldPrefix, string $newPrefix, int $depthOffset): int;

     /**
     * Delete a role and all its assignments (cascade).
     * 
     * @param string $roleId Role ID to delete
     * @param int $authenticatedUserId ID of user performing the action (for audit)
     * @return bool True if deletion was successful
     * 
     * @throws RuntimeException If role has active assignments and force=false
     */
    public function deleteWithCascade(string $roleId, int $authenticatedUserId, bool $force = false): bool;
    
    /**
     * Check if a role has active user assignments.
     */
    public function hasUserAssignments(string $roleId): bool;
    
    /**
     * Check if a role has profile assignments.
     */
    public function hasProfileAssignments(string $roleId): bool;

    public function existsByName(string $name): bool;
}
