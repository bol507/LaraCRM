<?php

namespace App\Infrastructure\Mappers;

use App\Models\VtigerUser;
use App\Domain\Entities\User;
use App\Infrastructure\Services\UserRoleDataService;

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

    /**
     *  Convert a VtigerUser entity to a User domain entity.
     *
     * @param VtigerUser $model
     * @param array|null $roleData
     * @return User
     */
    public static function toDomain(VtigerUser $model, ?array $roleData = null): User
    {
        $is_admin_raw = $model->is_admin ?? '0';
        $is_admin = in_array($is_admin_raw, ['1', 'on', 'yes', true, 1], true);

        $roleData = $roleData ?? UserRoleDataService::fetchForUser($model->id);

        $legacyRole = $is_admin 
            ? 'Admin' 
            : ($roleData['rolename'] ?? 'Usuario');
        return new User(
            id: $model->id,
            userName: $model->user_name,
            firstName: $model->first_name,
            lastName: $model->last_name,
            email: $model->email1,

            role: $legacyRole, //legacy

            role_id: $roleData['role_id'],
            rolename: $roleData['rolename'],
            role_depth: $roleData['depth'],
            role_parent: $roleData['parentrole'],
            sharing_rule: $roleData['sharing_rule'],

            status: self::determineStatusFromModel($model),
            phoneCrm: $model->phone_crm_extension,
            department: $model->department,
            reportsToId: is_numeric($model->reports_to_id) ? (int) $model->reports_to_id : null,
            isActive: $model->status === 'Active',
            is_admin: $is_admin,
        );
    }

    /**
     *  Convert a database row to a User domain entity.
     *
     * @param object $row
     * @param array|null $roleData
     * @return User
     */
    public static function fromDatabaseRow(object $row, ?array $roleData = null): User
    {
        $is_admin_raw = $row->is_admin ?? '0';
        $is_admin = in_array($is_admin_raw, ['1', 'on', 'yes', true, 1], true);

        // Si no se proporciona roleData, intentar extraer de la row (si viene de un JOIN)
        if (!$roleData) {
            $roleData = [
                'role_id' => $row->role_id ?? null,
                'rolename' => $row->rolename ?? null,
                'depth' => (int) ($row->role_depth ?? 0),
                'parentrole' => $row->role_parent ?? null,
                'sharing_rule' => (int) ($row->sharing_rule ?? 1),
            ];
        }

        $legacyRole = $is_admin 
            ? 'Admin' 
            : ($roleData['rolename'] ?? 'Usuario');

        return User::fromArray([
            'id' => (int) $row->id,
            'user_name' => $row->user_name,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'email' => $row->email1,

            'is_admin' => $is_admin,
            'role' => $legacyRole, // ⚠️ Legacy

            'role_id' => $roleData['role_id'],
            'rolename' => $roleData['rolename'],
            'role_depth' => $roleData['depth'],
            'role_parent' => $roleData['parentrole'],
            'sharing_rule' => $roleData['sharing_rule'],

            'status' => self::determineStatusFromRow($row),
            'phone_crm' => $row->phone_crm ?? $row->phone_crm_extension ?? null,
            'department' => $row->department ?? null,
            'reports_to_id' => is_numeric($row->reports_to_id) ? (int) $row->reports_to_id : null,
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
