<?php

namespace App\Application\DTOs;

use App\Domain\Entities\User;

class UserDto
{
    public static function fromEntity(User $user): array
    {
        return [
            'id' => $user->id,
            'user_name' => $user->user_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'phone_crm' => $user->phone_crm,
            'department' => $user->department,
            'reports_to_id' => $user->reports_to_id,
            'is_active' => $user->is_active,
        ];
    }
}