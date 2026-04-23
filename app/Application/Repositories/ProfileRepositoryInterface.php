<?php
// app/Application/Repositories/ProfileRepositoryInterface.php

namespace App\Application\Repositories;

interface ProfileRepositoryInterface
{
    /**
     * Fetch all profiles with their module permissions.
     * 
     * @return array<int, array{
     *   profileid: string,
     *   profilename: string,
     *   modules: array<int, array{tabid: int, name: string, permissions: string[]}>
     * }>
     */
    public function findAll(): array;

    /**
     * Find a single profile by ID with module details.
     * 
     * @return array{
     *   profileid: string,
     *   profilename: string,
     *   modules: array<int, array{tabid: int, name: string, permissions: string[]}>
     * }|null
     */
    public function findById(int $profileId): ?array;

    /**
     * Update profile name.
     */
    public function updateName(int $profileId, string $name): bool;

     /**
     * Update module permissions for a profile.
     * 
     * Business logic:
     * - If a module is in the $modules array → enable with calculated permissions
     * - If a module is NOT in the array → disable (permissions = 0)
     * - Uses vtiger_profile2tab.permissions as both visibility + CRUD flags
     * 
     * @param int $profileId Profile ID to update
     * @param array<int, array{tabid: int, permissions: string[]}> $modules Modules with permissions
     * @return bool True if update was successful
     */
    public function updateModulePermissions(int $profileId, array $modules): bool;

     /**
     * Check if a profile with the given name already exists.
     */
    public function existsByName(string $name): bool;
    
    /**
     * Create a new profile in vtiger_profile.
     * Returns the new profileid.
     */
    public function create(string $name, string $description = ''): int;

    /**
     * Parse Vtiger's bitwise permission value back to string array.
     * Used for reading permissions in findAll()/findById().
     * 
     * @param int $value Bitwise permission value (0-15)
     * @return string[] Array of permission strings
     */
    public function parsePermissionValue(int $value): array;

    
}