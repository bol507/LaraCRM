<?php

namespace App\Application\UseCases\Role;

use App\Application\DTOs\Role\CreateRoleRequest;
use App\Application\DTOs\Role\RoleDto;
use App\Application\Repositories\RoleRepositoryInterface;
use InvalidArgumentException;

class CreateRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository
    ) {}

    public function execute(CreateRoleRequest $request): RoleDto
    {
        $name = trim($request->name);
        if ($name === '') {
            throw new InvalidArgumentException('Role name cannot be empty');
        }
        if (strlen($name) > 100) {
            throw new InvalidArgumentException('Role name cannot exceed 100 characters');
        }

        $parentRole = null;
        if ($request->parentId !== null) {
            $parentRole = $this->repository->findById($request->parentId);
            if (!$parentRole) {
                throw new InvalidArgumentException("Parent role '{$request->parentId}' does not exist");
            }
        }

        //generate new role id sequence (H + number)
        $newRoleId = $this->generateNextRoleId();

        // calculate depth and parentrole
        $depth = $parentRole ? (int) $parentRole['depth'] + 1 : 0;
        $parentRolePath = $parentRole 
            ? rtrim($parentRole['parentrole'], ':') . '::' . $newRoleId . '::'
            : $newRoleId . '::';
        $this->repository->create($newRoleId, $name, $parentRolePath, $depth, 1);

         return new RoleDto(
            roleid: $newRoleId,
            rolename: $name,
            parentrole: $parentRolePath,
            depth: $depth,
            sharing_rule: 1
        );

        /*return DB::connection('vtiger')->transaction(function () use ($dto) {
            // 1. Generar roleid secuencial (H + number)
            $lastRole = DB::connection('vtiger')->table('vtiger_role')
                ->where('roleid', 'LIKE', 'H%')
                ->orderByRaw('CAST(SUBSTRING(roleid, 2) AS UNSIGNED) DESC')
                ->first();
            $nextId = 'H' . (($lastRole ? (int)substr($lastRole->roleid, 1) : 1) + 1);

            // 2. Calcular depth y parentrole
            $depth = 0;
            $parentRolePath = $nextId;
            if ($dto->parentId) {
                $parent = DB::connection('vtiger')->table('vtiger_role')->where('roleid', $dto->parentId)->first();
                $depth = $parent->depth + 1;
                $parentRolePath = rtrim($parent->parentrole, ':') . '::' . $nextId;
            }

            // 3. Insertar en vtiger_role
            DB::connection('vtiger')->table('vtiger_role')->insert([
                'roleid' => $nextId,
                'rolename' => $dto->name,
                'parentrole' => $parentRolePath,
                'depth' => $depth,
                'allowassignedrecordsto' => 1,
            ]);

            return ['roleid' => $nextId, 'name' => $dto->name, 'depth' => $depth, 'parentrole' => $parentRolePath];
        });*/
    }

    /**
     * Generate next sequential role ID (H + number)
     */
    private function generateNextRoleId(): string
    {
        // query all roles and find the highest number
        $allRoles = $this->repository->findAll();
        $maxNum = 0;
        
        foreach ($allRoles as $role) {
            if (preg_match('/^H(\d+)$/', $role['roleid'], $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) $maxNum = $num;
            }
        }
        
        return 'H' . ($maxNum + 1);
    }
}
