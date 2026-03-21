<?php

namespace App\Application\UseCases\User;

use App\Application\DTOs\User\UserSearchResultDto;
use App\Application\Repositories\UserRepositoryInterface;

/**
 * Class FindUsersByNameOrUsernameUseCase
 * 
 * Use case for searching users by name or username.
 * 
 * This use case orchestrates the user search operation by delegating
 * the data retrieval to the repository layer and transforming the
 * resulting entities into Data Transfer Objects (DTOs) for API responses.
 * 
 * This class follows the Single Responsibility Principle by focusing
 * solely on the user search business logic, and the Dependency Inversion
 * Principle by depending on the UserRepositoryInterface abstraction.
 * 
 * @package App\Application\UseCases\User
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @version 1.0.0
 * 
 * @see UserRepositoryInterface For the repository contract
 * @see UserSearchResultDto For the response data structure
 */
class FindUsersByNameOrUsernameUseCase
{
   

    /**
     * FindUsersByNameOrUsernameUseCase constructor.
     * 
     * Injects the UserRepositoryInterface dependency via constructor injection.
     * This enables dependency inversion, facilitating unit testing with
     * mock implementations and promoting loose coupling.
     * 
     * @param UserRepositoryInterface $userRepository The user repository instance
     * 
     * @return void
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Execute the user search operation.
     * 
     * Searches for users matching the provided search term by name or username.
     * The search is case-insensitive and supports partial matching. Results
     * are transformed from domain entities to DTOs and returned as arrays
     * ready for JSON serialization in API responses.
     * 
     * @param string $searchTerm The search term to match against user names and usernames.
     *                           Must be at least 1 character. The search supports
     *                           partial matching and is case-insensitive.
     * 
     * @return array<int, array<string, mixed>> An array of user search result DTOs
     *                                          converted to associative arrays.
     *                                          Each array contains user information
     *                                          formatted for API response.
     * 
     * @throws \InvalidArgumentException If the search term is empty or invalid.
     * @throws \RuntimeException If the repository operation fails.
     * 
     * @example
     * // Search for users with "john" in name or username
     * $useCase->execute('john');
     * 
     * @example
     * // Search for users with "admin" in name or username
     * $useCase->execute('admin');
     * 
     * @example
     * // Response structure
     * [
     *     [
     *         'id' => 1,
     *         'name' => 'John Doe',
     *         'username' => 'johndoe',
     *         'email' => 'john@example.com'
     *     ],
     *     ...
     * ]
     * 
     * @see UserRepositoryInterface::findByNameOrUsername() For the data access implementation
     * @see UserSearchResultDto::fromEntity() For the entity to DTO transformation
     * @see UserSearchResultDto::toArray() For the DTO to array conversion
     */
    public function execute(string $searchTerm): array
    {
        $users = $this->userRepository->findByNameOrUsername($searchTerm);

        return array_map(
            fn($user) => UserSearchResultDto::fromEntity($user)->toArray(),
            $users
        );
    }
}
