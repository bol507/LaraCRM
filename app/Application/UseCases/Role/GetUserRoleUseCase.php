<?php
// app/Application/UseCases/Role/GetUserRoleUseCase.php

namespace App\Application\UseCases\Role;

use App\Application\Repositories\RoleRepositoryInterface;

class GetUserRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository
    ) {}

    /**
     * Fetch hierarchical role data for a specific user.
     * 
     * @return array{role_id: string|null, rolename: string|null, depth: int, parentrole: string|null, sharing_rule: int}
     */
    public function execute(int $userId): array
    {
        return $this->roleRepository->findByUserId($userId) ?? [
            'role_id' => null,
            'rolename' => null,
            'depth' => 0,
            'parentrole' => null,
            'sharing_rule' => 1,
        ];
    }
}