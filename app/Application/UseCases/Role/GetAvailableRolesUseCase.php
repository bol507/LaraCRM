<?php

namespace App\Application\UseCases\Role;

use App\Application\DTOs\Role\RoleOptionResponse;
use App\Application\Repositories\RoleRepositoryInterface;

class GetAvailableRolesUseCase
{
    
    public function __construct(
        private readonly RoleRepositoryInterface $repository
    ){}
    
    /**
     * @return array
     */
    public function execute(): array
    {
        return $this->repository->findAllAvailable();
    }
}