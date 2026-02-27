<?php

namespace App\Infrastructure\Mappers;

use App\Models\VtigerUser;
use App\Domain\Entities\User;

class UserMapper
{
   
    public static function toDomain(VtigerUser $model): User
    {
        return new User(
            id: $model->id,
            userName: $model->user_name,
            firstName: $model->first_name,
            lastName: $model->last_name,
            email: $model->email1,
            role: $model->is_admin === '1' ? 'Admin' : 'Usuario',
            status: $model->status,
            phoneCrm: $model->phone_crm_extension,
            department: $model->department,
            reportsToId: $model->reports_to_id,
            isActive: $model->status === 'Active'
        );
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
            'reports_to_id' => $entity->getReportsToId(),
        ];
    }
}