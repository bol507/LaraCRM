<?php

namespace App\Infrastructure\Mappers;

use App\Models\VtigerUser;
use App\Domain\Entities\User;

/**
 * User Mapper
 * 
 * Converts between Vtiger database representations and domain User entities.
 * Supports both Eloquent models and raw database rows for flexibility.
 * 
 * @package App\Infrastructure\Mappers
 * @author Bolivar Delgado <bolivar.delgado@gmail.com>
 * @since 1.0.0
 */
class UserMapper
{


    public static function toDomain(VtigerUser $model): User
    {
        $is_admin_raw = $model->is_admin ?? '0';
        $is_admin = in_array($is_admin_raw, ['1', 'on', 'yes', true, 1], true);

        $roleData = \Illuminate\Support\Facades\DB::connection('vtiger')
            ->table('vtiger_user2role')
            ->join('vtiger_role', 'vtiger_user2role.roleid', '=', 'vtiger_role.roleid')
            ->where('vtiger_user2role.userid', $model->id)
            ->select('vtiger_role.roleid', 'vtiger_role.rolename')
            ->first();

        $role_id = $roleData?->roleid ?? null;
        $rolename = $roleData?->rolename ?? null;

        $role = $is_admin ? 'Admin' : ($rolename ?? 'Usuario');
        return new User(
            id: $model->id,
            userName: $model->user_name,
            firstName: $model->first_name,
            lastName: $model->last_name,
            email: $model->email1,

            role: $role, //legacy

            status: self::determineStatusFromModel($model),
            phoneCrm: $model->phone_crm_extension,
            department: $model->department,
            reportsToId: $model->reports_to_id,
            isActive: $model->status === 'Active',

            is_admin: $is_admin,
            role_id: $role_id,
            rolename: $rolename,
        );
    }

    public static function fromDatabaseRow(object $row): User
    {
        return User::fromArray([
            'id' => (int) $row->id,
            'user_name' => $row->user_name,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'email' => $row->email1,

            'is_admin' => in_array($row->is_admin ?? '0', ['1', 'on', 'yes', true, 1], true),
            'role_id' => $row->role_id ?? null,
            'rolename' => $row->rolename ?? null,

            'status' => self::determineStatusFromRow($row),
            'phone_crm' => $row->phone_crm ?? $row->phone_crm_extension ?? null,
            'department' => $row->department ?? null,
            'reports_to_id' => isset($row->reports_to_id) ? (int) $row->reports_to_id : null,
            'is_active' => ($row->status ?? 'Active') === 'Active',
        ]);
    }

    public static function toPersistence(User $entity): array
    {
        return [
            'user_name' => $entity->getUserName(),
            'first_name' => $entity->getFirstName(),
            'last_name' => $entity->getLastName(),
            'email1' => $entity->getEmail(),
            'is_admin' => $entity->isAdmin() ? '1' : '0',
            'status' => $entity->getStatus(),
            'phone_crm_extension' => $entity->getPhoneCrm(),
            'department' => $entity->getDepartment(),
            'reports_to_id' => $entity->getReportsToId() ? (string) $entity->getReportsToId() : null,
        ];
    }

    

    

    private static function determineStatusFromModel(VtigerUser $model): string
    {
        return self::normalizeStatus($model->status ?? 'Active');
    }

    private static function determineStatusFromRow(object $row): string
    {
        return self::normalizeStatus($row->status ?? 'Active');
    }

    private static function normalizeStatus(string $vtigerStatus): string
    {
        return match (strtolower(trim($vtigerStatus))) {
            'active', 'on', 'enabled' => 'Active',
            'inactive', 'off', 'disabled' => 'Inactive',
            'pending', 'waiting' => 'Pending',
            default => 'Active',
        };
    }
}
