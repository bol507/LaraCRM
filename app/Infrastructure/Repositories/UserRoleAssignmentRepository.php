<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\UserRoleAssignmentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UserRoleAssignmentRepository implements UserRoleAssignmentRepositoryInterface
{
    public function assign(int $userId, string $roleId): void
    {

        DB::connection('vtiger')
            ->table('vtiger_user2role')
            ->updateOrInsert(
                ['userid' => $userId],
                ['roleid' => $roleId]
            );
    }

    public function findByUserId(int $userId): ?string
    {
        return DB::connection('vtiger')
            ->table('vtiger_user2role')
            ->where('userid', $userId)
            ->value('roleid');
    }

    public function findRoleDetailsByUserId(int $userId): ?array
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_user2role')
            ->join('vtiger_role', 'vtiger_user2role.roleid', '=', 'vtiger_role.roleid')
            ->where('vtiger_user2role.userid', $userId)
            ->select('vtiger_role.roleid as role_id', 'vtiger_role.rolename')
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @inheritDoc
     */
    public function delete(int $userId): void
    {
        DB::connection('vtiger')
            ->table('vtiger_user2role')
            ->where('userid', $userId)
            ->delete();
    }
}
