<?php

namespace App\Application\UseCases\Role;

use App\Application\DTOs\Role\RoleDto;
use App\Application\DTOs\Role\UpdateRoleRequest;
use App\Application\Repositories\RoleRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class UpdateRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository
    ) {}

    /**
     * Update role name, parent, or sharing rules.
     * Handles hierarchy cascade if parent changes.
     */
    public function execute(string $roleId, UpdateRoleRequest $request): RoleDto
    {
        $role = $this->repository->findById($roleId);
        if (!$role) {
            throw new RuntimeException("Role '{$roleId}' not found");
        }

        if ($roleId === 'H1') {
            throw new InvalidArgumentException('Cannot modify the root Organization role');
        }

        $updateData = [];
        $oldPrefix = $role['parentrole'];
        $oldDepth = (int) $role['depth'];

        // Example: 'H1::H2::H7::H6' -> Parent is 'H7'
        $pathParts = explode('::', trim($oldPrefix, ':'));
        $currentParentId = (count($pathParts) > 1) ? $pathParts[count($pathParts) - 2] : null;

        $newParentId = $request->parentId;

        if ($newParentId !== $currentParentId) {
            
            if ($newParentId !== null) {
                // Validate no circular reference
                $this->validateParentChange($roleId, $newParentId, $oldPrefix);

                // Calculate new path and depth
                $newParent = $this->repository->findById($newParentId);
                $newDepth = (int) $newParent['depth'] + 1;
                $newPrefix = rtrim($newParent['parentrole'], ':') . '::' . $roleId . '::';
                $depthOffset = $newDepth - $oldDepth;

                $updateData['parentrole'] = $newPrefix;
                $updateData['depth'] = $newDepth;

                // Cascade to children
                $this->repository->cascadePathUpdate($oldPrefix, $newPrefix, $depthOffset);
            } else {
                // Case: Move to Root (H1)
                $updateData['parentrole'] = 'H1::' . $roleId . '::';
                $updateData['depth'] = 1;
                $depthOffset = 1 - $oldDepth;
                $this->repository->cascadePathUpdate($oldPrefix, $updateData['parentrole'], $depthOffset);
            }
        }

        if ($request->name !== null) {
            $updateData['rolename'] = trim($request->name);
        }

        if ($request->sharingRule !== null) {
            $updateData['allowassignedrecordsto'] = $request->sharingRule;
        }

        if (!empty($updateData)) {
            $this->repository->update($roleId, $updateData);
        }

        $updated = $this->repository->findById($roleId);
        return new RoleDto(
            roleid: $updated['roleid'],
            rolename: $updated['rolename'],
            parentrole: $updated['parentrole'],
            depth: (int) $updated['depth'],
            sharing_rule: (int) $updated['allowassignedrecordsto']
        );
    }

    /**
     * Prevent circular hierarchy references
     */
    private function validateParentChange(string $currentRoleId, string $newParentId, string $currentPath): void
    {
        if ($currentRoleId === $newParentId) {
            throw new InvalidArgumentException('A role cannot be its own parent');
        }

        // If the new parent is already in the current role's path, it's a cycle
        $pathParts = explode('::', trim($currentPath, ':'));
        if (in_array($newParentId, $pathParts, true)) {
            throw new InvalidArgumentException('Circular hierarchy detected: cannot assign a descendant as parent');
        }

        // Verify that the new parent exists
        if (!$this->repository->findById($newParentId)) {
            throw new InvalidArgumentException("Parent role '{$newParentId}' does not exist");
        }
    }
}