<?php

namespace App\Application\UseCases\Role;

use App\Application\Repositories\UserRoleAssignmentRepositoryInterface;

/**
 * Fetches the hierarchical role details (role_id & rolename) for a given user.
 * Used primarily for the /me endpoint to populate frontend session/cache.
 */
class GetUserHierarchicalRoleUseCase
{
    public function __construct(
        private readonly UserRoleAssignmentRepositoryInterface $assignmentRepo
    ) {}

    /**
     * @return array{role_id: string|null, rolename: string|null}
     */
    public function execute(int $userId): array
    {
        $details = $this->assignmentRepo->findRoleDetailsByUserId($userId);
        
        return [
            'role_id' => $details['role_id'] ?? null,
            'rolename' => $details['rolename'] ?? null,
        ];
    }
}