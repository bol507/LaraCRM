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
    /**
     * Valid roles for the domain User entity
     * 
     * @var array<string>
     */
    private const VALID_ROLES = ['Admin', 'Usuario', 'Cliente'];

    /**
     * Map Eloquent VtigerUser model to domain User entity
     * 
     * @param VtigerUser $model Eloquent model instance
     * @return User Domain entity
     */
    public static function toDomain(VtigerUser $model): User
    {
        return new User(
            id: $model->id,
            userName: $model->user_name,
            firstName: $model->first_name,
            lastName: $model->last_name,
            email: $model->email1,
            role: self::determineRoleFromModel($model),
            status: self::determineStatusFromModel($model),
            phoneCrm: $model->phone_crm_extension,
            department: $model->department,
            reportsToId: $model->reports_to_id,
            isActive: $model->status === 'Active'
        );
    }

    /**
     * Map raw database row to domain User entity
     * 
     * Useful for repositories that use query builder instead of Eloquent.
     * 
     * @param object $row Raw database row from query builder
     * @return User Domain entity
     */
    public static function fromDatabaseRow(object $row): User
    {
        return User::fromArray([
            'id' => (int) $row->id,
            'user_name' => $row->user_name,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'email' => $row->email1,
            'role' => self::determineRoleFromRow($row),
            'status' => self::determineStatusFromRow($row),
            'phone_crm' => $row->phone_crm ?? $row->phone_crm_extension ?? null,
            'department' => $row->department ?? null,
            'reports_to_id' => isset($row->reports_to_id) ? (int) $row->reports_to_id : null,
            'is_active' => ($row->status ?? 'Active') === 'Active',
        ]);
    }

    /**
     * Map domain User entity to persistence array
     * 
     * @param User $entity Domain entity
     * @return array Associative array for database insertion/update
     */
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
            'reports_to_id' => $entity->getReportsToId(),
        ];
    }

    /**
     * Determine domain role value from Eloquent model
     * 
     * @param VtigerUser $model Eloquent model
     * @return string One of: 'Admin', 'Usuario', 'Cliente'
     */
    private static function determineRoleFromModel(VtigerUser $model): string
    {
        // Check is_admin flag (Vtiger standard)
        if ($model->is_admin === '1' || $model->is_admin === 'on' || $model->is_admin === 1) {
            return 'Admin';
        }

        // Check role_id (typically 1 = admin)
        if ($model->role_id === 1) {
            return 'Admin';
        }

        // Check role name via relationship or direct field
        if (!empty($model->rolename) && stripos($model->rolename, 'admin') !== false) {
            return 'Admin';
        }
        if (!empty($model->role_name) && stripos($model->role_name, 'admin') !== false) {
            return 'Admin';
        }

        // Check for client role
        if (!empty($model->rolename) && stripos($model->rolename, 'cliente') !== false) {
            return 'Cliente';
        }
        if (!empty($model->role_name) && stripos($model->role_name, 'cliente') !== false) {
            return 'Cliente';
        }

        // Default to regular user
        return 'Usuario';
    }

    /**
     * Determine domain role value from raw database row
     * 
     * @param object $row Raw database row
     * @return string One of: 'Admin', 'Usuario', 'Cliente'
     */
    private static function determineRoleFromRow(object $row): string
    {
        // Check is_admin flag (Vtiger standard)
        if (isset($row->is_admin) && ($row->is_admin === '1' || $row->is_admin === 'on' || $row->is_admin === 1)) {
            return 'Admin';
        }

        // Check role_id (typically 1 = admin)
        if (isset($row->role_id) && (int) $row->role_id === 1) {
            return 'Admin';
        }

        // Check role name via joined table
        if (isset($row->rolename) && stripos($row->rolename, 'admin') !== false) {
            return 'Admin';
        }

        // Check for client role
        if (isset($row->rolename) && stripos($row->rolename, 'cliente') !== false) {
            return 'Cliente';
        }

        // Default to regular user
        return 'Usuario';
    }

    /**
     * Determine domain status value from Eloquent model
     * 
     * @param VtigerUser $model Eloquent model
     * @return string One of: 'Active', 'Inactive', 'Pending'
     */
    private static function determineStatusFromModel(VtigerUser $model): string
    {
        return self::normalizeStatus($model->status ?? 'Active');
    }

    /**
     * Determine domain status value from raw database row
     * 
     * @param object $row Raw database row
     * @return string One of: 'Active', 'Inactive', 'Pending'
     */
    private static function determineStatusFromRow(object $row): string
    {
        return self::normalizeStatus($row->status ?? 'Active');
    }

    /**
     * Normalize Vtiger status value to domain status
     * 
     * @param string $vtigerStatus Raw status from Vtiger
     * @return string Normalized domain status
     */
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