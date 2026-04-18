<?php
namespace App\Application\Repositories;

interface RoleRepositoryInterface
{
    /**
     * Fetch all hierarchical roles for UI selection.
     *
     * @return array<RoleOptionResponse>
     */
    public function findAllAvailable(): array;
}