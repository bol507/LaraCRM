<?php

namespace App\Application\UseCases\User;

use App\Application\Repositories\CrmentityRepositoryInterface;
use App\Application\Repositories\UserRepositoryInterface;
use App\Application\Repositories\UserRoleAssignmentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DeleteUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly CrmentityRepositoryInterface $crmentityRepository,
        private readonly UserRoleAssignmentRepositoryInterface $userRoleAssignmentRepository
    ) {}
    
    /**
     * Soft-delete a user (set deleted=1) with business rule validations.
     *
     * @param int $userId ID of user to delete
     * @param int $authenticatedUserId ID of user performing the action
     * @return bool True if deletion was successful
     *
     * @throws InvalidArgumentException If trying to delete self or an admin without proper permissions
     * @throws RuntimeException If user not found or persistence fails
     */
    public function execute(int $userId, int $authenticatedUserId): bool
    {
        $targetUser = $this->userRepository->findById($userId);
        // User not found
        if (!$targetUser) {
            throw new RuntimeException("User with ID {$userId} not found");
        }

        // Do not permit auto deletes
        if ($userId === $authenticatedUserId) {
            throw new InvalidArgumentException('You cannot delete your own account');
        }

        $authUser = $this->userRepository->findById($authenticatedUserId);
        // Only admins can delete users
        if (!$authUser?->isAdmin()) {
            throw new InvalidArgumentException('Only administrators can delete users');
        }

        return DB::connection('vtiger')->transaction(function () use ($userId, $authenticatedUserId): bool {
            
            // first relations after deleting user
            $this->userRoleAssignmentRepository->delete($userId);
            $this->crmentityRepository->delete($userId);
            $this->userRepository->delete($userId, $authenticatedUserId);
            
            return true;
        });
        

    }
}