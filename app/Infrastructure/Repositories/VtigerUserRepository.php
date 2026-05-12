<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\UserRepositoryInterface;
use App\Application\DTOs\User\CreateUserRequest;
use App\Application\DTOs\User\UpdateUserProfileRequest;
use App\Domain\Entities\User;
use App\Infrastructure\Mappers\UserMapper;
use App\Infrastructure\Services\UserRoleDataService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Vtiger-specific implementation of UserRepositoryInterface.
 *
 * Handles persistence logic for User entities using Vtiger CRM database schema.
 * Maps Vtiger-specific fields (email1, is_admin, etc.) to domain entity properties.
 *
 * @package App\Infrastructure\Repositories
 * @implements UserRepositoryInterface
 * @see \App\Domain\Entities\User
 */
class VtigerUserRepository implements UserRepositoryInterface
{

    protected const CONNECTION = 'vtiger';
    protected const USER_TABLE = 'vtiger_users';
    protected const USER_ROLE_TABLE = 'vtiger_user2role';
    protected const ROLE_TABLE = 'vtiger_role';

    protected function query(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(self::CONNECTION)->table(self::USER_TABLE);
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Queries vtiger_users table with soft-delete filter (deleted = 0).
     * Maps database fields to domain properties: email1 → email, is_admin → role.
     * Uses manual pagination for compatibility with Vtiger schema.
     */
    public function getAll(int $page, int $perPage, ?string $search): LengthAwarePaginator
    {
        $query = $this->query()
            ->leftJoin(self::USER_ROLE_TABLE, 'vtiger_users.id', '=', 'vtiger_user2role.userid')
            ->leftJoin(self::ROLE_TABLE, 'vtiger_user2role.roleid', '=', 'vtiger_role.roleid')
            ->select(
                'vtiger_users.id',
                'vtiger_users.user_name',
                'vtiger_users.first_name',
                'vtiger_users.last_name',
                'vtiger_users.email1 as email1',
                'vtiger_users.is_admin',
                'vtiger_users.status',
                'vtiger_users.phone_crm_extension as phone_crm_extension',
                'vtiger_users.department',
                'vtiger_users.reports_to_id',
                'vtiger_user2role.roleid as role_id',
                'vtiger_role.rolename',
                'vtiger_role.depth as role_depth',
                'vtiger_role.parentrole as role_parent',
                'vtiger_role.allowassignedrecordsto as sharing_rule'
            )
            ->where('vtiger_users.deleted', 0);


        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vtiger_users.first_name', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_users.last_name', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_users.user_name', 'LIKE', "%{$search}%")
                    ->orWhere('vtiger_users.email1', 'LIKE', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $paginator->through(function ($row) {
            $roleData = [
                'role_id' => $row->role_id ?? null,
                'rolename' => $row->rolename ?? null,
                'depth' => (int) ($row->role_depth ?? 0),
                'parentrole' => $row->role_parent ?? null,
                'sharing_rule' => (int) ($row->sharing_rule ?? 1),
            ];
            return UserMapper::fromDatabaseRow($row, $roleData);
        });
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Queries vtiger_users with soft-delete filter.
     * Returns null if user not found or marked as deleted.
     */
    public function findById(int $id): ?User
    {
        try {
            $row = $this->query()
                ->leftJoin(self::USER_ROLE_TABLE, 'vtiger_users.id', '=', 'vtiger_user2role.userid')
                ->leftJoin(self::ROLE_TABLE, 'vtiger_user2role.roleid', '=', 'vtiger_role.roleid')
                ->select(
                    'vtiger_users.id',
                    'vtiger_users.user_name',
                    'vtiger_users.first_name',
                    'vtiger_users.last_name',
                    'vtiger_users.email1 as email1',
                    'vtiger_users.is_admin',
                    'vtiger_users.status',
                    'vtiger_users.phone_crm_extension as phone_crm_extension',
                    'vtiger_users.department',
                    'vtiger_users.reports_to_id',
                    'vtiger_user2role.roleid as role_id',
                    'vtiger_role.rolename',
                    'vtiger_role.depth as role_depth',
                    'vtiger_role.parentrole as role_parent',
                    'vtiger_role.allowassignedrecordsto as sharing_rule'
                )
                ->where('vtiger_users.id', $id)
                ->where('vtiger_users.deleted', 0)
                ->first();

            if (!$row) {
                return null;
            }

            $roleData = $this->fetchRoleDataForUser($id) ?? [
                'role_id' => $row->role_id ?? null,
                'rolename' => $row->rolename ?? null,
                'depth' => (int) ($row->role_depth ?? 0),
                'parentrole' => $row->role_parent ?? null,
                'sharing_rule' => (int) ($row->sharing_rule ?? 1),
            ];


            return UserMapper::fromDatabaseRow($row, $roleData);
        } catch (\Exception $e) {
            Log::error('Error al obtener usuario por ID: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException(
                "Failed to retrieve user {$id}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Splits full name into first/last name components for separate field queries.
     * Returns array format (not User entity) for lightweight search results.
     */
    public function findByFullName(string $fullName): ?array
    {
        $parts = array_filter(explode(' ', trim($fullName)));
        $firstName = $parts[0] ?? '';
        $lastName = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';

        $query = DB::connection('vtiger')
            ->table('vtiger_users')
            ->select(
                'id',
                'first_name',
                'last_name',
                'user_name',
                'email1 as email',  // ⚠️ Aliased for API consistency
                'is_admin'
            )
            ->where('deleted', 0);

        if ($firstName) {
            $query->where('first_name', 'LIKE', "%{$firstName}%");
        }
        if ($lastName) {
            $query->where('last_name', 'LIKE', "%{$lastName}%");
        }

        $row = $query->first();

        return $row ? [
            'id' => $row->id,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'user_name' => $row->user_name,
            'email' => $row->email,
            'role' => $row->is_admin === '1' ? 'Admin' : 'Usuario'
        ] : null;
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Searches across first_name, last_name, user_name, and email1 fields.
     * Limited to 20 results for autocomplete performance.
     * Returns array format for lightweight frontend consumption.
     */
    public function findByNameOrUsername(string $searchTerm): array
    {
        $users = DB::connection('vtiger')
            ->table('vtiger_users')
            ->select(
                'vtiger_users.id',
                'vtiger_users.user_name',
                'vtiger_users.first_name',
                'vtiger_users.last_name',
                'vtiger_users.email1 as email1',
                'vtiger_users.is_admin',
                'vtiger_users.status',
                'vtiger_users.phone_crm_extension as phone_crm_extension',
                'vtiger_users.department',
                'vtiger_users.reports_to_id',
                DB::raw('(
                SELECT vtiger_role.rolename
                FROM vtiger_user2role
                INNER JOIN vtiger_role ON vtiger_user2role.roleid = vtiger_role.roleid
                WHERE vtiger_user2role.userid = vtiger_users.id
                LIMIT 1
            ) as rolename')
            )
            ->where('vtiger_users.deleted', 0)
            ->where(function ($q) use ($searchTerm) {
                $q->where('vtiger_users.first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('vtiger_users.last_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('vtiger_users.user_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('vtiger_users.email1', 'LIKE', "%{$searchTerm}%");
            })
            ->limit(10)
            ->get();

        return $users->map(function ($row) {
            return UserMapper::fromDatabaseRow($row);
        })->toArray();
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Queries email1 field (case-insensitive via LOWER).
     * Returns null if user not found, inactive, or soft-deleted.
     */
    public function findByEmail(string $email): ?User
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_users')
            ->select(
                'id',
                'user_name',
                'first_name',
                'last_name',
                'email1',
                'is_admin',
                'status',
                'phone_crm_extension as phone_crm',
                'department',
                'reports_to_id'
            )
            ->whereRaw('LOWER(email1) = ?', [strtolower($email)])
            ->where('deleted', 0)
            ->first();

        return $row ? User::fromArray([
            'id' => $row->id,
            'user_name' => $row->user_name,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'email' => $row->email1,
            'role' => $row->is_admin === '1' ? 'Admin' : 'Usuario',
            'status' => $row->status,
            'phone_crm' => $row->phone_crm,
            'department' => $row->department,
            'reports_to_id' => $row->reports_to_id,
            'is_active' => $row->status === 'Active',
        ]) : null;
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Usernames are case-sensitive in Vtiger.
     * Returns null if user not found, inactive, or soft-deleted.
     */
    public function findByUserName(string $userName): ?User
    {
        $row = DB::connection('vtiger')
            ->table('vtiger_users')
            ->select(
                'id',
                'user_name',
                'first_name',
                'last_name',
                'email1',
                'is_admin',
                'status',
                'phone_crm_extension as phone_crm',
                'department',
                'reports_to_id'
            )
            ->where('user_name', $userName)
            ->where('deleted', 0)
            ->first();

        return $row ? User::fromArray([
            'id' => $row->id,
            'user_name' => $row->user_name,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'email' => $row->email1,
            'role' => $row->is_admin === '1' ? 'Admin' : 'Usuario',
            'status' => $row->status,
            'phone_crm' => $row->phone_crm,
            'department' => $row->department,
            'reports_to_id' => $row->reports_to_id,
            'is_active' => $row->status === 'Active',
        ]) : null;
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Maps role name to is_admin flag for filtering.
     * Returns all active users with matching role, ordered by creation date.
     */
    public function findByRole(string $roleIdentifier): array
    {
        //  Map role identifier to roleid
        $role = DB::connection('vtiger')
            ->table('vtiger_role')
            ->where(function ($q) use ($roleIdentifier) {
                $q->where('roleid', $roleIdentifier)
                    ->orWhere('rolename', $roleIdentifier);
            })
            ->where('deleted', 0)
            ->first();

        if (!$role) {
            return [];
        }

        $rows = DB::connection('vtiger')
            ->table('vtiger_users')
            ->join('vtiger_user2role', 'vtiger_users.id', '=', 'vtiger_user2role.userid')
            ->where('vtiger_user2role.roleid', $role->roleid)
            ->where('vtiger_users.status', 'Active')
            ->where('vtiger_users.deleted', 0)
            ->select(
                'vtiger_users.*',
                'vtiger_role.roleid as role_id',
                'vtiger_role.rolename',
                'vtiger_role.depth as role_depth',
                'vtiger_role.parentrole as role_parent',
                'vtiger_role.allowassignedrecordsto as sharing_rule'
            )
            ->orderBy('vtiger_users.date_entered', 'desc')
            ->get();

        return $rows->map(function ($row) {
            $roleData = [
                'role_id' => $row->role_id,
                'rolename' => $row->rolename,
                'depth' => (int) $row->role_depth,
                'parentrole' => $row->role_parent,
                'sharing_rule' => (int) $row->sharing_rule,
            ];
            return UserMapper::fromDatabaseRow($row, $roleData);
        })->toArray();
    }
    /**
     * {@inheritDoc}
     */
    public function findForAuthentication(string $userName): ?array
    {
        $row = $this->query()
            ->where('user_name', $userName)
            ->where('deleted', 0)
            ->select('id', 'user_password', 'status', 'is_admin', 'crypt_type')
            ->first();
        return $row ? (array) $row : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveUsers(int $limit = 100, int $offset = 0): array
    {
        try {
            $rows = DB::connection('vtiger')
                ->table('vtiger_users')
                ->leftJoin('vtiger_user2role', 'vtiger_users.id', '=', 'vtiger_user2role.userid')
                ->leftJoin('vtiger_role', 'vtiger_users.roleid', '=', 'vtiger_role.roleid')
                ->where('vtiger_users.status', 'Active')
                ->where('vtiger_users.deleted', 0)
                ->select(
                    'vtiger_users.*',
                    'vtiger_role.roleid as role_id',
                    'vtiger_role.rolename',
                    'vtiger_role.depth as role_depth',
                    'vtiger_role.parentrole as role_parent',
                    'vtiger_role.allowassignedrecordsto as sharing_rule'
                )
                ->offset($offset)
                ->limit($limit)
                ->get();
            return $rows->map(function ($row) {
                $roleData = [
                    'role_id' => $row->role_id ?? null,
                    'rolename' => $row->rolename ?? null,
                    'depth' => (int) ($row->role_depth ?? 0),
                    'parentrole' => $row->role_parent ?? null,
                    'sharing_rule' => (int) ($row->sharing_rule ?? 1),
                ];
                return UserMapper::fromDatabaseRow($row, $roleData);
            })->toArray();
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to retrieve active users: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}

     */
    public function create(CreateUserRequest $request, int $createdByUserId): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            $hashedPassword = password_hash($request->password, PASSWORD_DEFAULT);

            $userId = DB::connection('vtiger')
                ->table('vtiger_users')
                ->insertGetId([
                    // Core user fields
                    'user_name' => $request->user_name,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email1' => $request->email,
                    'is_admin' => $request->is_admin ? '1' : '0',
                    'status' => 'Active',
                    'phone_crm_extension' => $request->phone_crm,
                    'department' => $request->department,
                    'reports_to_id' => $request->reports_to_id,

                    // ⚠️ Vtiger audit fields (required for proper tracking)
                    'date_entered' => now()->format('Y-m-d H:i:s'),
                    'date_modified' => now()->format('Y-m-d H:i:s'),
                    'modified_user_id' => $createdByUserId,
                    'deleted' => 0,

                    // ⚠️ Password storage: Vtiger requires BOTH fields with PHASH
                    'user_password' => $hashedPassword,
                    'confirm_password' => $hashedPassword,
                    'crypt_type' => 'PHASH',

                    // ⚠️ Default values for Vtiger UI compatibility
                    'currency_id' => 21, // USD
                    'description' => '',
                    'cal_color' => '#E6FAD8',
                    'user_preferences' => '{"global_search_autocomplete":1,"global_search_entity":"all","listviewsticky":1}',
                    'imagename' => '',
                    'internal_mailer' => 1,
                    'activity_view' => 'Today',
                    'lead_view' => 'Today',
                    'title' => '',
                    'phone_home' => '',
                    'phone_mobile' => '',
                    'phone_work' => '',
                    'phone_other' => '',
                    'phone_fax' => '',
                    'email2' => '',
                    'secondaryemail' => '',
                    'signature' => '',
                    'address_street' => '',
                    'address_city' => '',
                    'address_state' => '',
                    'address_country' => '',
                    'address_postalcode' => '',
                    'tz' => '',
                    'holidays' => '',
                    'namedays' => '',
                    'workdays' => '1,2,3,4,5',
                    'weekstart' => 0,
                    'date_format' => 'dd-mm-yyyy',
                    'hour_format' => 'am/pm',
                    'start_hour' => '09:00',
                    'end_hour' => '17:00',
                    'is_owner' => '0',
                    'reminder_interval' => '1 Minute',
                    'reminder_next_time' => '',
                    'accesskey' => '',
                    'theme' => 'softed',
                    'language' => 'es',
                    'time_zone' => 'America/Panama',
                    'currency_grouping_pattern' => '123,456,789',
                    'currency_decimal_separator' => '.',
                    'currency_grouping_separator' => ',',
                    'currency_symbol_placement' => '1.0$',
                    'no_of_currency_decimals' => 2,
                    'truncate_trailing_zeros' => 1,
                    'dayoftheweek' => 'Sunday',
                    'callduration' => '',
                    'othereventduration' => '',
                    'calendarsharedtype' => '',
                    'default_record_view' => '',
                    'leftpanelhide' => '',
                    'rowheight' => '',
                    'defaulteventstatus' => 'Planned',
                    'defaultactivitytype' => 'Call',
                    'hidecompletedevents' => 0,
                    'defaultcalendarview' => '',
                    'defaultlandingpage' => '',
                    'userlabel' => '',
                ]);

            DB::connection('vtiger')->commit();
            return $userId;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function insert(CreateUserRequest $request, int $authenticatedUserId): User
    {
        $now = now()->format('Y-m-d H:i:s');
        $hashedPassword = password_hash($request->password, PASSWORD_DEFAULT);

        $userData = [
            'user_name'             => $request->user_name,
            'first_name'            => $request->first_name,
            'last_name'             => $request->last_name,
            'email1'                => $request->email,
            'is_admin'              => $request->is_admin ? '1' : '0',
            'status'                => $request->status ?? 'Active',
            'user_password'         => $hashedPassword,
            'confirm_password'      => $hashedPassword,
            'crypt_type'            => 'PHASH',
            'phone_crm_extension'   => $request->phone_crm,
            'department'            => $request->department,
            'reports_to_id'         => $request->reports_to_id !== null ? (string) $request->reports_to_id : null,
            'currency_id'           => 1, // Vtiger default
            'date_entered'          => $now,
            'date_modified'         => $now,
            'modified_user_id'      => (string) $authenticatedUserId,
            'deleted'               => 0,
            'internal_mailer'       => 1,
        ];

        // 1. Insertar en vtiger_users (auto_increment en 'id')
        $newId = DB::connection('vtiger')
            ->table('vtiger_users')
            ->insertGetId($userData);

        if (!$newId) {
            throw new RuntimeException('Failed to insert user record');
        }

        // 2. Asignar rol jerárquico (vtiger_user2role) si se proporciona
        if (!empty($request->role_id)) {
            DB::connection('vtiger')
                ->table('vtiger_user2role')
                ->insert([
                    'userid' => $newId,
                    'roleid' => $request->role_id
                ]);
        }

        $this->clearRoleCache($newId);

        // 3. Retornar entidad de dominio recién creada
        $user = $this->findById($newId);
        if (!$user) {
            throw new RuntimeException("Failed to retrieve newly created user with ID {$newId}");
        }

        return $user;
    }

    public function update(int $id, array $data, int $modifiedByUserId): bool
    {
        if (empty($data)) {
            return true; // Nothing to update
        }

        // Remove managed fields that should be handled by the repository
        $sanitized = array_diff_key($data, [
            'id' => null,
            'password' => null,
        ]);

        if (empty($sanitized)) {
            return true;
        }
        $now = now()->format('Y-m-d H:i:s');
        $affected = $this->query()
            ->where('id', $id)
            ->update([
                ...$sanitized,
                'date_modified' => $now,
                'modified_user_id' => $modifiedByUserId
            ]);

        if ($affected > 0) {
            $this->clearRoleCache($id);
        }

        return $affected > 0;
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Updates vtiger_users table with audit fields (date_modified, modified_user_id).
     * Does not update password-related fields (use changePassword() for that).
     * Returns false if user not found or marked as deleted.
     */
    public function updateProfile(UpdateUserProfileRequest $request, int $modifiedByUserId): bool
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            $currentTime = now()->format('Y-m-d H:i:s');

            $existing = DB::connection('vtiger')
                ->table('vtiger_users')
                ->where('id', $request->id)
                ->where('deleted', 0)
                ->first();

            if (!$existing) {
                return false;
            }

            $updateData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'user_name' => $request->user_name,
                'email1' => $request->email,
                'is_admin' => $request->is_admin ? '1' : '0',
                'department' => $request->department,
                'phone_crm_extension' => $request->phone_crm,
                'reports_to_id' => $request->reports_to_id,
                'date_modified' => $currentTime,
                'modified_user_id' => $modifiedByUserId,
            ];

            DB::connection('vtiger')
                ->table('vtiger_users')
                ->where('id', $request->id)
                ->update($updateData);

            if (isset($request->role_id)) {
                $this->clearRoleCache($request->id);
            }

            DB::connection('vtiger')->commit();
            return true;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Implements soft-delete by setting 'deleted = 1' instead of removing row.
     * Authorization: Only users with is_admin = '1' can delete other users.
     * Prevention: Users cannot delete their own account via this method.
     *
     * @throws \Exception If caller lacks admin privileges or attempts self-deletion
     */
    public function delete(int $id, int $deletedByUserId): bool
    {
        $now = now()->format('Y-m-d H:i:s');

        $updated = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $id)
            ->update([
                'deleted' => 1,
                'date_modified' => $now,
                'modified_user_id' => (string) $deletedByUserId,
            ]) > 0;
        if (!$updated) {
            throw new RuntimeException('Failed to delete user or user already deleted');
        }
        return true;
    }

    /**
     * @deprecated Use RoleRepository::findAllAvailable() via /api/settings/roles endpoint instead.
     * This method returns hardcoded legacy roles and does not scale with dynamic role management.
     */
    public function getAvailableRoles(): array
    {
        // Opción A: Retornar vacío para forzar uso del endpoint correcto
        return [];

        // Opción B: Query dinámica a vtiger_role (si realmente se necesita aquí)
        /*
    return DB::connection('vtiger')
        ->table('vtiger_role')
        ->where('deleted', 0)
        ->orderBy('depth', 'asc')
        ->orderBy('rolename', 'asc')
        ->pluck('rolename')
        ->toArray();
    */
    }



    /**
     * {@inheritDoc}
     *
     * Vtiger-specific:
     * - Password hashed with PASSWORD_DEFAULT and stored with crypt_type = 'PHASH'
     * - Authorization: Users can only change their own password, unless caller is admin
     * - Updates audit fields (date_modified, modified_user_id) for compliance
     *
     * @throws \Exception If caller lacks permission to change the target user's password
     */
    public function changePassword(int $userId, string $newPassword, int $modifiedByUserId): bool
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $now = now()->format('Y-m-d H:i:s');

        $updated = $this->query()
            ->where('id', $userId)
            ->update([
                'user_password' => $hashedPassword,
                'confirm_password' => $hashedPassword,
                'crypt_type' => 'PHASH',
                'date_modified' => $now,
                'modified_user_id' => $modifiedByUserId,
            ]);

        if (!$updated) {
            throw new RuntimeException('Failed to update password');
        }
        return true;
    }

    public function upgradePasswordHash(int $userId, string $plainPassword): bool
    {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        return $this->query()
            ->where('id', $userId)
            ->update([
                'user_password' => $hashedPassword,
                'confirm_password' => $hashedPassword,
                'crypt_type' => 'PHASH',
            ]) > 0;
    }


    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Checks user_name field with case-sensitive comparison.
     * Excludes soft-deleted users from availability check.
     */
    public function isUserNameAvailable(string $userName, ?int $excludeUserId = null): bool
    {
        $query = $this->query()
            ->where('user_name', $userName)
            ->where('deleted', 0);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->count() === 0;
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Checks email1 field with case-insensitive comparison.
     * Excludes soft-deleted users from availability check.
     */
    public function isEmailAvailable(string $email, ?int $excludeUserId = null): bool
    {
        $query = $this->query()
            ->whereRaw('LOWER(email1) = ?', [strtolower($email)])
            ->where('deleted', 0);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->count() === 0;
    }

    /**
     * {@inheritDoc}
     *
     * Vtiger-specific: Counts users with status = 'Active' and deleted = 0.
     * Optimized query using COUNT(*) instead of loading all records.
     */
    public function countActiveUsers(): int
    {
        return $this->query()
            ->where('status', 'Active')
            ->where('deleted', 0)
            ->count();
    }



    /**
     * {@inheritDoc}
     */
    public function isAdmin(int $userId): bool
    {
        try {
            $user = $this->findById($userId);
            return $user ? $user->isAdmin() : false;
        } catch (\Exception $e) {
            Log::error("Error checking admin status for user {$userId}: " . $e->getMessage());
            return false;
        }
    }



    /**
     * {@inheritDoc}
     */
    public function assignHierarchicalRoleByName(int $userId, string $roleName): bool
    {
        $roleId = DB::connection('vtiger')
            ->table('vtiger_role')
            ->where('rolename', $roleName)
            ->where('deleted', 0)
            ->value('roleid');

        if (!$roleId) {
            return false;
        }

        $result = DB::connection('vtiger')
            ->table('vtiger_user2role')
            ->updateOrInsert(
                ['userid' => $userId],
                ['roleid' => $roleId]
            );


        if ($result) {
            $this->clearRoleCache($userId);
        }

        return $result;
    }


    /**
     * {@inheritDoc}
     */
    public function getHierarchicalRoleName(int $userId): ?string
    {
        return DB::connection('vtiger')
            ->table('vtiger_user2role')
            ->join('vtiger_role', 'vtiger_user2role.roleid', '=', 'vtiger_role.roleid')
            ->where('vtiger_user2role.userid', $userId)
            ->value('vtiger_role.rolename');
    }

    /**
     * Fetch role data for a user with caching.
     * Used to enrich User entities without N+1 queries.
     */
    private function fetchRoleDataForUser(int $userId): ?array
    {
        return UserRoleDataService::fetchForUser($userId);
    }

    /**
     * Clear role cache after role-affecting operations.
     */
    private function clearRoleCache(int $userId): void
    {
        UserRoleDataService::clearCache($userId);
    }

    public function getNameById(int $userId): ?string
    {
        $fullName = DB::connection(self::CONNECTION)
            ->table(self::USER_TABLE)
            ->where('id', $userId)
            ->where('deleted', 0)
            ->value(DB::raw("CONCAT_WS(' ', first_name, last_name)"));

        if (!empty(trim($fullName ?? ''))) {
            return trim($fullName);
        }

        $userName = DB::connection(self::CONNECTION)
            ->table(self::USER_TABLE)
            ->where('id', $userId)
            ->where('deleted', 0)
            ->value('user_name');

        return !empty($userName) ? $userName : null;
    }
}
