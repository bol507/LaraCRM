<?php
namespace App\Application\Repositories;

interface UserRoleAssignmentRepositoryInterface
{
    /**
     * Assign or update a hierarchical role for a user in vtiger_user2role.
     * Idempotent: safe to call multiple times with the same data.
     */
    public function assign(int $userId, string $roleId): void;

    /**
     * Get the currently assigned role ID for a user.
     */
    public function findByUserId(int $userId): ?string;

     /**
     * Fetch role_id and rolename for a user via join.
     * @return array<string, mixed>|null
     */
    public function findRoleDetailsByUserId(int $userId): ?array;
}