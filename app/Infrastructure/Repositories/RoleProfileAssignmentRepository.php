<?php
// app/Infrastructure/Repositories/RoleProfileAssignmentRepository.php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\RoleProfileAssignmentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class RoleProfileAssignmentRepository implements RoleProfileAssignmentRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const TABLE = 'vtiger_profile2role';
    protected const PROFILE_TABLE = 'vtiger_profile';

    public function assign(string $roleId, int $profileId): bool
    {
        return $this->query()
            ->insert([
                'profileid' => $profileId,
                'roleid' => $roleId,
            ]);
    }

    public function deleteByRoleId(string $roleId): bool
    {
        return $this->query()
            ->where('roleid', $roleId)
            ->delete() >= 0; // delete() returns number of affected rows
    }

    public function findByRoleId(string $roleId): ?array
    {
        return $this->query()
            ->join(self::PROFILE_TABLE, self::TABLE . '.profileid', '=', self::PROFILE_TABLE . '.profileid')
            ->where(self::TABLE . '.roleid', $roleId)
            ->select(self::PROFILE_TABLE . '.profileid', self::PROFILE_TABLE . '.profilename')
            ->first();
    }

    /**
     * Base query builder for vtiger_role table.
     */
    protected function query(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE);
    }
}