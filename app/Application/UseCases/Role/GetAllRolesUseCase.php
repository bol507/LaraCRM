<?php

namespace App\Application\UseCases\Role;

use App\Application\DTOs\Role\RoleDto;
use App\Application\Repositories\RoleRepositoryInterface;

class GetAllRolesUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository
    ) {}

    /**
     * Retrieve all roles with hierarchy metadata and usage counts.
     * Sorted by depth ASC for predictable tree rendering.
     */
    public function execute(): array
    {
        $rawRoles = $this->repository->findAll();
        
        return array_map(function ($row) {
            return new RoleDto(
                roleid: $row['roleid'],
                rolename: $row['rolename'],
                parentrole: $row['parentrole'],
                depth: (int) $row['depth'],
                sharing_rule: (int) ($row['allowassignedrecordsto'] ?? 1),
                children_count: (int) ($row['children_count'] ?? 0),
                users_count: (int) ($row['users_count'] ?? 0)
            );
        }, $rawRoles);
    }
}