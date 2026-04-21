<?php

namespace App\Application\DTOs\User;

use App\Domain\Entities\User;

/**
 * Data Transfer Object for User entity.
 * 
 * This DTO is used to transfer user data between application layers
 * without exposing the domain entity directly. It provides a stable
 * contract for API responses and request handling.
 * 
 * @package App\Application\DTOs
 * @see \App\Domain\Entities\User
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 */
class UserDto
{
    /**
     * Convert a User entity to an associative array for API responses
     * 
     * This method maps domain entity properties to a flat array structure
     * suitable for JSON serialization. Property names follow snake_case
     * convention for API consistency.
     * 
     * @param User $user The domain entity to convert
     * @return array Associative array with user data in snake_case format
     */
    public static function fromEntity(User $user): array
    {
        return [
            'id' => $user->getId(),
            'user_name' => $user->getUserName(),
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'full_name' => $user->getFullName(), 
            'email' => $user->getEmail(),
             
            'is_admin' => $user->getIsAdmin(),      // boolean
            'role_id'  => $user->getRoleId(),       // string | null
            'rolename' => $user->getRoleName(),     // string | null
            
            'status' => $user->getStatus(),
            'phone_crm' => $user->getPhoneCrm(),
            'department' => $user->getDepartment(),
            'reports_to_id' => $user->getReportsToId(),
            'is_active' => $user->isActive(),
        ];
    }

    /**
     * Convert a collection of User entities to an array of DTOs
     * 
     * Utility method for mapping multiple entities at once, commonly
     * used in list endpoints.
     * 
     * @param User[] $users Array of User entities to convert
     * @return array[] Array of associative arrays with user data
     */
    public static function fromEntities(array $users): array
    {
        return array_map(fn(User $user) => self::fromEntity($user), $users);
    }

    /**
     * Create a User entity from an associative array
     * 
     * This method is the inverse of fromEntity(), useful for mapping
     * API request data or database rows to domain entities.
     * 
     * @internal Primarily used by infrastructure layer (repositories)
     * @param array $data Associative array with user data in snake_case format
     * @return User New User entity instance
     * @throws \InvalidArgumentException If required fields are missing or invalid
     */
    public static function toEntity(array $data): User
    {
        return new User(
            id: (int) ($data['id'] ?? 0),
            userName: $data['user_name'] ?? '',
            firstName: $data['first_name'] ?? '',
            lastName: $data['last_name'] ?? '',
            email: $data['email'] ?? '',
            role: $data['role'] ?? 'Usuario',
            status: $data['status'] ?? 'Active',
            phoneCrm: $data['phone_crm'] ?? null,
            department: $data['department'] ?? null,
            reportsToId: isset($data['reports_to_id']) ? (int) $data['reports_to_id'] : null,
            isActive: ($data['is_active'] ?? true) && ($data['status'] ?? 'Active') === 'Active'
        );
    }
}