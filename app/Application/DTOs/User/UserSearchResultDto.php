<?php

namespace App\Application\DTOs\User;

use App\Domain\Entities\User;

/**
 * Class UserSearchResultDto
 * 
 * Data Transfer Object for user search results used in autocomplete functionality.
 * 
 * This DTO contains only the essential fields required for displaying user
 * search results in the frontend, minimizing payload size and protecting
 * sensitive user information from being exposed unnecessarily.
 * 
 * The DTO follows the immutability pattern using readonly properties,
 * ensuring that once created, the data cannot be modified. This promotes
 * safer data handling and predictable behavior throughout the application.
 * 
 * @package App\Application\DTOs\User
 * @author Bolivar Delgado <bolivar.delagado@gmail.com>
 * @version 1.0.0
 * 
 * @see User For the domain entity that this DTO transforms
 * @see \App\Application\UseCases\User\FindUsersByNameOrUsernameUseCase For the use case that utilizes this DTO
 */
class UserSearchResultDto
{
    

    /**
     * UserSearchResultDto constructor.
     * 
     * Creates an immutable DTO instance containing user search result data.
     * All properties are marked as readonly to ensure immutability after
     * instantiation, promoting safer data handling and preventing accidental
     * modifications.
     * 
     * @param int $id The unique identifier of the user.
     * @param string $first_name The first name of the user.
     * @param string $last_name The last name of the user.
     * @param string $user_name The username of the user.
     * @param string|null $email The email address of the user (optional).
     * @param string|null $role The role assigned to the user (optional).
     * 
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $user_name,
        public readonly ?string $email = null,
        public readonly ?string $role = null
    ) {}

    /**
     * Create a UserSearchResultDto instance from a User domain entity.
     * 
     * This static factory method transforms a User entity into a DTO
     * suitable for API responses. It extracts only the fields required
     * for search result display, adhering to the principle of least
     * privilege by not exposing unnecessary sensitive data.
     * 
     * The method uses getter methods from the User entity to access
     * property values, maintaining proper encapsulation and allowing
     * the entity to control its own data access logic.
     * 
     * @param User $user The User domain entity to transform.
     * 
     * @return self A new UserSearchResultDto instance populated with
     *              data from the provided User entity.
     * 
     * @example
     * // Transform a User entity to DTO
     * $user = $userRepository->findById(1);
     * $dto = UserSearchResultDto::fromEntity($user);
     * 
     * @example
     * // Use in array mapping for multiple users
     * $users = $userRepository->findByNameOrUsername('john');
     * $results = array_map(
     *     fn($user) => UserSearchResultDto::fromEntity($user)->toArray(),
     *     $users
     * );
     * 
     * @see User::getId()
     * @see User::getFirstName()
     * @see User::getLastName()
     * @see User::getUserName()
     * @see User::getEmail()
     * @see User::getRole()
     */
    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId(),
            first_name: $user->getFirstName(),
            last_name: $user->getLastName(),
            user_name: $user->getUserName(),
            email: $user->getEmail(),
            role: $user->getRole()
        );
    }

    /**
     * Convert the DTO to an associative array for JSON serialization.
     * 
     * This method prepares the DTO data for transmission in API responses.
     * The returned array uses snake_case keys to conform to common JSON
     * API conventions and includes all public properties of the DTO.
     * 
     * Note: The array includes both 'id' and 'user_id' keys for backward
     * compatibility with frontend components that may expect either field name.
     * This duplication is intentional and should be reviewed in future versions
     * to standardize on a single field name.
     * 
     * @return array<string, mixed> An associative array containing all DTO
     *                              properties formatted for JSON serialization.
     * 
     * @example
     * // Convert DTO to array for API response
     * $dto = UserSearchResultDto::fromEntity($user);
     * $responseData = $dto->toArray();
     * // Returns:
     * // [
     * //     'id' => 1,
     * //     'user_id' => 1,
     * //     'first_name' => 'John',
     * //     'last_name' => 'Doe',
     * //     'user_name' => 'johndoe',
     * //     'email' => 'john@example.com',
     * //     'role' => 'admin'
     * // ]
     * 
     * @example
     * // Use in controller response
     * return response()->json($dto->toArray());
     * 
     * @see json_encode() For PHP's JSON serialization function
     * @see \Illuminate\Http\JsonResponse For Laravel's JSON response class
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->id, 
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'user_name' => $this->user_name,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}