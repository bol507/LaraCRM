<?php
namespace App\Application\UseCases\Role;

use App\Application\Repositories\RoleRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class DeleteRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository
    ) {}

    /**
     * Delete a hierarchical role with cascade cleanup.
     * 
     * @param string $roleId Role ID to delete
     * @param int $authenticatedUserId ID of user performing the action
     * @param bool $force If true, auto-reassign users to parent role
     * @return bool True if deletion was successful
     * 
     * @throws InvalidArgumentException If trying to delete root role or self
     * @throws RuntimeException If persistence fails or role has assignments (without force)
     */
    public function execute(string $roleId, int $authenticatedUserId, bool $force = false): bool
    {
        // Validaciones de negocio adicionales
        if ($roleId === 'H1') {
            throw new InvalidArgumentException('Cannot delete the root Organization role');
        }

        // Ejecutar eliminación con cascada (delegado al repositorio)
        return $this->repository->deleteWithCascade($roleId, $authenticatedUserId, $force);
    }
}