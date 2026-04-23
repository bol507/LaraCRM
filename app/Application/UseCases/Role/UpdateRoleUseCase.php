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

        if ($request->parentId !== null && $request->parentId !== ($role['parentrole'] ?? '')) {
            $this->validateParentChange($roleId, $request->parentId, $oldPrefix);
            
            $newParent = $this->repository->findById($request->parentId);
            $newDepth = (int) $newParent['depth'] + 1;
            $newPrefix = rtrim($newParent['parentrole'], ':') . '::' . $roleId . '::';
            $depthOffset = $newDepth - $oldDepth;

            // Aplicar cambios al rol actual
            $updateData['parentrole'] = $newPrefix;
            $updateData['depth'] = $newDepth;

            // 🔽 Cascada: actualizar hijos
            $this->repository->cascadePathUpdate($oldPrefix, $newPrefix, $depthOffset);
        }

        if ($request->name !== null) {
            $updateData['rolename'] = trim($request->name);
        }

        if ($request->sharingRule !== null) {
            $updateData['allowassignedrecordsto'] = $request->sharingRule;
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

        // Si el nuevo padre ya está en la ruta actual del rol, es un ciclo
        $pathParts = explode('::', trim($currentPath, ':'));
        if (in_array($newParentId, $pathParts, true)) {
            throw new InvalidArgumentException('Circular hierarchy detected: cannot assign a descendant as parent');
        }

        // Verificar que el nuevo padre existe
        if (!$this->repository->findById($newParentId)) {
            throw new InvalidArgumentException("Parent role '{$newParentId}' does not exist");
        }
    }
}