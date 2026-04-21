<?php
namespace App\Infrastructure\Repositories;

use App\Application\DTOs\Role\RoleOptionResponse;
use App\Application\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Facades\DB;

class RoleRepository implements RoleRepositoryInterface
{
    public function findAllAvailable(): array
    {
        return DB::connection('vtiger')
            ->table('vtiger_role')
            ->select('roleid', 'rolename', 'depth', 'parentrole')
            ->orderBy('depth', 'asc')
            ->orderBy('rolename', 'asc')
            ->get()
            ->map(fn($row) => RoleOptionResponse::fromDbRow($row))
            ->toArray();
    }
}