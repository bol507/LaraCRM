<?php
// app/Application/UseCases/User/CanAssignToUserUseCase.php

namespace App\Application\UseCases\User;

use App\Application\Repositories\RoleRepositoryInterface;
use InvalidArgumentException;

/**
 * Check if a user can assign tasks to another user based on role hierarchy
 * 
 * In Vtiger, users can assign to:
 * - Themselves (always)
 * - Their direct/indirect subordinates (based on parentrole chain)
 * - Anyone (if they are global admin)
 */
class CanAssignToUserUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly IsAdminUseCase $isAdminUseCase,
    ) {}

    /**
     * Check if $assignerId can assign tasks to $targetUserId
     * 
     * @param int $assignerId The user who wants to assign
     * @param int $targetUserId The user who would receive the assignment
     * @return bool True if assignment is permitted
     */
    public function execute(int $assignerId, int $targetUserId): bool
    {
        if ($assignerId <= 0 || $targetUserId <= 0) {
            throw new InvalidArgumentException('User IDs must be positive');
        }

        // Can always assign to themselves
        if ($assignerId === $targetUserId) {
            return true;
        }

        // Global admin can assign to anyone
        if ($this->isAdminUseCase->execute($assignerId)) {
            return true;
        }

        // Get subordinates of the assigner by role hierarchy
        $assignerRoleId = $this->roleRepository->getUserRoleId($assignerId);
        if ($assignerRoleId === null) {
            return false;
            // Optional: throw explicit exception if you prefer feedback to the frontend:
            // throw new DomainException("User {$assignerId} has no role assigned and cannot delegate tasks.");
        }
        $subordinateIds = $this->roleRepository->findSubordinateUserIds($assignerRoleId);

        // Can assign if the target is in the subordinate chain
        return in_array($targetUserId, $subordinateIds, true);
    }
}