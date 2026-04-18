<?php
namespace App\Application\UseCases\Role;

use App\Application\DTOs\Role\AssignRoleRequest;
use App\Application\Repositories\UserRepositoryInterface;
use App\Application\Repositories\UserRoleAssignmentRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class AssignUserRoleUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserRoleAssignmentRepositoryInterface $assignmentRepository,
    ) {}

    /**
     * Assign a hierarchical role to a user.
     * 
     * @throws InvalidArgumentException If role doesn't exist or user is invalid
     * @throws RuntimeException If user not found
     */
    public function execute(AssignRoleRequest $request): void
    {
        $userId = $request->userId;
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new RuntimeException("User with ID {$userId} not found");
        }

        $this->assignmentRepository->assign($userId, $request->roleId);
    }
}