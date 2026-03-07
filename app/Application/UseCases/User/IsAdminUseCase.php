<?php

namespace App\Application\UseCases\User;

use App\Application\Repositories\UserRepositoryInterface;
use InvalidArgumentException;

/**
 * Is Admin Use Case
 * 
 * Determines if a user has administrator privileges.
 * 
 * @package App\Application\UseCases\User
 */
class IsAdminUseCase
{
    /**
     * User repository for data access
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Execute the use case: check if user is administrator
     * 
     * @param int $userId User ID to check
     * @return bool True if user is administrator, false otherwise
     * 
     * @throws InvalidArgumentException If userId is invalid
     */
    public function execute(int $userId): bool
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException(
                "User ID must be a positive integer, got {$userId}"
            );
        }

        return $this->userRepository->isAdmin($userId);
    }
}