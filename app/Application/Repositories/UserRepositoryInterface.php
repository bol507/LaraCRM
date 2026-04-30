<?php

namespace App\Application\Repositories;

use App\Application\DTOs\User\CreateUserRequest;
use App\Application\DTOs\User\UpdateUserProfileRequest;
use App\Domain\Entities\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository interface for User entity operations.
 *
 * Defines the contract for user data access and manipulation.
 * Implementations should handle persistence logic while keeping
 * domain entities clean from infrastructure concerns.
 *
 * @package App\Application\Repositories
 * @see \App\Domain\Entities\User
 * @see \App\Infrastructure\Repositories\VtigerUserRepository
 */
interface UserRepositoryInterface
{
    /**
     * Retrieve a paginated list of users with optional search filtering.
     *
     * Supports searching by username, email, first name, or last name.
     * Results are ordered by creation date (newest first).
     *
     * @param int $page Current page number (1-indexed)
     * @param int $perPage Number of items per page (default: 20)
     * @param string|null $search Optional search term for filtering
     * @return LengthAwarePaginator Paginated collection of User entities
     */
    public function getAll(int $page, int $perPage, ?string $search): LengthAwarePaginator;

    /**
     * Find a user by their unique identifier.
     *
     * Returns null if no user exists with the given ID or if the user
     * has been soft-deleted.
     *
     * @param int $id The unique user identifier
     * @return User|null The user entity if found, null otherwise
     */
    public function findById(int $id): ?User;

    /**
     * Find a user by their email address.
     *
     * Email addresses are case-insensitive for lookup.
     * Returns null if no active user exists with the given email.
     *
     * @param string $email The email address to search for
     * @return User|null The user entity if found, null otherwise
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find a user by their username.
     *
     * Usernames are case-sensitive and unique across the system.
     * Returns null if no active user exists with the given username.
     *
     * @param string $userName The username to search for
     * @return User|null The user entity if found, null otherwise
     */
    public function findByUserName(string $userName): ?User;

    /**
     * Fetch ONLY authentication data for login verification.
     * Returns minimal fields to reduce exposure and improve performance.
     *
     * @param string $userName
     * @return array{id: int, user_password: string, status: string, is_admin: string|int, deleted: int}|null
     */
    public function findForAuthentication(string $userName): ?array;

    /**
     * {@deprecated} Use insert() instead
     */
    public function create(CreateUserRequest $request, int $createdByUserId): int;

    /**
     * Insert a new user into Vtiger and assign hierarchical role.
     *
     * @param CreateUserRequest $request Validated creation DTO
     * @param int $authenticatedUserId ID of the admin creating the user (for audit)
     * @return User Newly created domain entity
     *
     * @throws RuntimeException If persistence fails or entity cannot be retrieved
     * @note Should be called within a DB transaction from the UseCase
     */
    public function insert(CreateUserRequest $request, int $authenticatedUserId): User;

    /**
     * Update an existing user with data without password
     *
     * @param integer $userId
     * @param array $data
     * @param integer $modifiedByUserId
     * @return boolean
     */
    public function update(int $userId, array $data, int $modifiedByUserId): bool;

    /**
     * Update an existing user's profile information.
     *
     * Updates: first_name, last_name, email, username, department, phone.
     * Does NOT update password (use changePassword() for that).
     * Validates email and username uniqueness (excluding current user).
     *
     * @param UpdateUserProfileRequest $request Data transfer object with updated information
     * @param int $modifiedByUserId ID of the authenticated user making the change
     * @return bool True if update was successful, false if user not found
     * @throws \DomainException If new email or username conflicts with existing user
     * @throws \InvalidArgumentException If validation fails
     */
    public function updateProfile(UpdateUserProfileRequest $request, int $modifiedByUserId): bool;

    /**
     * Change user password with secure hashing.
     *
     * @param int $userId
     * @param string $newPassword
     * @param int $modifiedByUserId ID of user performing the change (for audit)
     * @return bool True if update was successful
     */
    public function changePassword(int $userId, string $newPassword, int $modifiedByUserId): bool;

    /**
     * Soft-delete a user account.
     *
     * Sets deleted flag instead of permanently removing from database.
     * Preserves audit trail and related records integrity.
     * Prevents login and API access after deletion.
     *
     * @param int $id The unique user identifier to delete
     * @param int $deletedByUserId ID of the authenticated user performing deletion
     * @return bool True if deletion was successful, false if user not found
     * @throws \DomainException If attempting to delete the last admin user
     */
    public function delete(int $id, int $deletedByUserId): bool;

    /**
     * Retrieve all available user roles in the system.
     *
     * Returns roles that can be assigned to users during creation or update.
     * Typically includes: Admin, Usuario, Cliente.
     *
     * @return array Associative array of role identifiers and labels
     *               Example: ['Admin' => 'Administrator', 'Usuario' => 'User']
     */
    public function getAvailableRoles(): array;

    /**
     * Find users by their full name (first + last name).
     *
     * Performs case-insensitive search across first_name and last_name fields.
     * Supports partial matches (e.g., "John" matches "John Doe").
     *
     * @param string $fullName Full name or partial name to search for
     * @return array|null Array of user data if found, null otherwise
     */
    public function findByFullName(string $fullName): ?array;

    /**
     * Find users by name or username search term.
     *
     * Searches across first_name, last_name, and user_name fields.
     * Returns up to 10 matching results for autocomplete functionality.
     *
     * @param string $searchTerm Search term (minimum 2 characters recommended)
     * @return User[] Array of matching User entities (empty array if none found)
     */
    public function findByNameOrUsername(string $searchTerm): array;

    /**
     * Check if a username is available for registration.
     *
     * Username is available if no active user has that username.
     * Case-sensitive comparison.
     *
     * @param string $userName The username to check
     * @param int|null $excludeUserId Optional user ID to exclude (for updates)
     * @return bool True if username is available, false if taken
     */
    public function isUserNameAvailable(string $userName, ?int $excludeUserId = null): bool;

    /**
     * Check if an email address is available for registration.
     *
     * Email is available if no active user has that email.
     * Case-insensitive comparison.
     *
     * @param string $email The email address to check
     * @param int|null $excludeUserId Optional user ID to exclude (for updates)
     * @return bool True if email is available, false if taken
     */
    public function isEmailAvailable(string $email, ?int $excludeUserId = null): bool;

    /**
     * Count total active users in the system.
     *
     * Excludes soft-deleted users from the count.
     *
     * @return int Total number of active users
     */
    public function countActiveUsers(): int;

    /**
     * Get users by role.
     *
     * Returns all active users with the specified role.
     * Useful for role-based access control and notifications.
     *
     * @param string $role The role to filter by (e.g., 'Admin', 'Usuario')
     * @return User[] Array of User entities with the specified role
     */
    public function findByRole(string $role): array;

    /**
     * Check if a user has administrator privileges
     *
     * @param int $userId User ID to check
     * @return bool True if user is administrator, false otherwise
     */
    public function isAdmin(int $userId): bool;

    /**
     * Get all active users
     *
     * @param int $limit Maximum number of users to return
     * @param int $offset Offset for pagination
     * @return User[] Array of user entities
     */
    public function findActiveUsers(int $limit = 100, int $offset = 0): array;

    /**
     * Assign a hierarchical role to a user via vtiger_user2role
     *
     * @param int $userId
     * @param string $roleName Role name from vtiger_role (e.g., 'CEO', 'Vendedor')
     * @return bool Success
     */
    public function assignHierarchicalRoleByName(int $userId, string $roleName): bool;

    /**
     * Get the hierarchical role name for a user
     */
    public function getHierarchicalRoleName(int $userId): ?string;




    /**
     * Upgrade legacy password hash to modern algorithm.
     *
     * @param int $userId
     * @param string $plainPassword Plain text password to re-hash
     * @return bool True if upgrade was successful
     */
    public function upgradePasswordHash(int $userId, string $plainPassword): bool;
}
