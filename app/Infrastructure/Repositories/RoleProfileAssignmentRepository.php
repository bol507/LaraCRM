<?php
// app/Infrastructure/Repositories/RoleProfileAssignmentRepository.php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\RoleProfileAssignmentRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleProfileAssignmentRepository implements RoleProfileAssignmentRepositoryInterface
{
    protected const CONNECTION = 'vtiger';
    protected const ROLE_PROFILE_TABLE = 'vtiger_role2profile';
    protected const PROFILE_TABLE = 'vtiger_profile';
    protected const USER_ROLE_TABLE = 'vtiger_user2role';


    /**
     * @inheritDoc
     */
    public function assign(string $roleId, string $profileId): bool
    {
        try {
            return DB::connection(self::CONNECTION)
                ->table(self::ROLE_PROFILE_TABLE)
                ->updateOrInsert(
                    ['roleid' => $roleId, 'profileid' => $profileId],
                    ['roleid' => $roleId, 'profileid' => $profileId]
                );
        } catch (\Exception $e) {
            Log::error('Failed to assign profile', [
                'role_id' => $roleId,
                'profile_id' => $profileId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteByRoleId(string $roleId): bool
    {
        try {
            return DB::connection(self::CONNECTION)
                ->table(self::ROLE_PROFILE_TABLE)
                ->where('roleid', $roleId)
                ->delete() > 0;
        } catch (\Exception $e) {
            Log::error('Failed to delete profile assignment', [
                'role_id' => $roleId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /*
    * @inheritDoc
    */
    public function delete(string $roleId, string $profileId): bool
    {
        return $this->query()
            ->where('roleid', $roleId)
            ->where('profileid', $profileId)
            ->delete() > 0;
    }

    /*
     * @inheritDoc
     */
    public function findByRoleId(string $roleId): ?array
    {
        return $this->query()
            ->join(self::PROFILE_TABLE, self::ROLE_PROFILE_TABLE . '.profileid', '=', self::PROFILE_TABLE . '.profileid')
            ->where(self::ROLE_PROFILE_TABLE . '.roleid', $roleId)
            ->select(self::PROFILE_TABLE . '.profileid', self::PROFILE_TABLE . '.profilename')
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findProfileIdByUserId(int $userId): ?int
    {
        try {

            return DB::connection(self::CONNECTION)
                ->table(self::USER_ROLE_TABLE)
                ->join(self::ROLE_PROFILE_TABLE, 
                    self::USER_ROLE_TABLE . '.roleid', '=', 
                    self::ROLE_PROFILE_TABLE . '.roleid')
                ->where(self::USER_ROLE_TABLE . '.userid', $userId)
                ->value(self::ROLE_PROFILE_TABLE . '.profileid');
        } catch (\Exception $e) {
            // Log específico para debugging
            Log::warning('Profile lookup failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'sql_state' => $e->getCode(),
            ]);
            return null;
        }
    }

    /**
     * Check if a table exists in the connected database.
     */
    private function tableExists(string $table): bool
    {
        try {
            $schema = DB::connection(self::CONNECTION)->getDoctrineSchemaManager();
            return $schema->tablesExist([$table]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Base query builder for vtiger_role table.
     */
    protected function query(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(self::CONNECTION)->table(self::ROLE_PROFILE_TABLE);
    }
}
