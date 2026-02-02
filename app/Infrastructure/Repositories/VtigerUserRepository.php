<?php

namespace App\Infrastructure\Repositories;

use App\Application\Repositories\UserRepositoryInterface;
use App\Application\DTOs\CreateUserRequest;
use App\Application\DTOs\UpdateUserProfileRequest;
use App\Application\DTOs\UpdateUserRequest;
use App\Domain\Entities\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class VtigerUserRepository implements UserRepositoryInterface
{
    public function getAll(int $page = 1, int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        $query = DB::connection('vtiger')
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
            ->where('deleted', 0);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email1', 'LIKE', "%{$search}%")
                    ->orWhere('user_name', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get();

        $users = $items->map(fn($row) => new User(
            id: $row->id,
            user_name: $row->user_name,
            first_name: $row->first_name,
            last_name: $row->last_name,
            email: $row->email1,
            role: $row->is_admin === '1' ? 'Admin' : 'Usuario',
            status: $row->status,
            phone_crm: $row->phone_crm,
            department: $row->department,
            reports_to_id: $row->reports_to_id,
            is_active: $row->status === 'Active'
        ));

        return new LengthAwarePaginator(
            $users instanceof Collection ? $users : collect($users),
            $total,
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }

    public function findById(int $id): ?User
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
            ->where('id', $id)
            ->where('deleted', 0)
            ->first();

        return $row ? new User(
            id: $row->id,
            user_name: $row->user_name,
            first_name: $row->first_name,
            last_name: $row->last_name,
            email: $row->email1,
            role: $row->is_admin === '1' ? 'Admin' : 'Usuario',
            status: $row->status,
            phone_crm: $row->phone_crm,
            department: $row->department,
            reports_to_id: $row->reports_to_id,
            is_active: $row->status === 'Active'
        ) : null;
    }

    public function create(CreateUserRequest $request, int $createdByUserId): int
    {
        DB::connection('vtiger')->beginTransaction();

        try {
            $hashedPassword = password_hash($request->password, PASSWORD_DEFAULT);

            $userId = DB::connection('vtiger')
                ->table('vtiger_users')
                ->insertGetId([
                    // fields 
                    'user_name' => $request->user_name,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email1' => $request->email,
                    'is_admin' => ($request->role === 'Admin') ? '1' : '0',
                    'status' => 'Active',
                    'phone_crm_extension' => $request->phone_crm,
                    'department' => $request->department,
                    'reports_to_id' => $request->reports_to_id,

                    // 
                    'date_entered' => now()->format('Y-m-d H:i:s'),
                    'date_modified' => now()->format('Y-m-d H:i:s'),
                    'modified_user_id' => $createdByUserId,
                    'deleted' => 0,

                    // password and security
                    'user_password' => $hashedPassword,
                    'confirm_password' => $hashedPassword,
                    'crypt_type' => 'PHASH',

                    // address of invoice
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
                'is_admin' => ($request->role === 'Admin') ? '1' : '0',
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

            DB::connection('vtiger')->commit();
            return true;
        } catch (\Exception $e) {
            DB::connection('vtiger')->rollback();
            throw $e;
        }
    }

    public function delete(int $id, int $deletedByUserId): bool
    {
        // Verificar que el usuario exista y esté activo
        $user = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $id)
            ->where('deleted', 0)
            ->first();

        if (!$user) {
            return false;
        }

        // Verificar permisos: solo admin puede eliminar usuarios
        $deleterIsAdmin = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $deletedByUserId)
            ->where('is_admin', '1')
            ->exists();

        if (!$deleterIsAdmin) {
            throw new \Exception('Solo los administradores pueden eliminar usuarios');
        }

        // Verificar que no se esté eliminando a sí mismo
        if ($deletedByUserId === $id) {
            throw new \Exception('No puedes eliminarte a ti mismo');
        }

        // Realizar soft delete
        return DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $id)
            ->update([
                'deleted' => 1,
                'date_modified' => now()->format('Y-m-d H:i:s'),
                'modified_user_id' => $deletedByUserId,
            ]) > 0;
    }

    public function getAvailableRoles(): array
    {
        return ['Admin', 'Usuario', 'Cliente'];
    }

    private function getRoleName(?string $roleid): string
    {
        if (!$roleid) return 'Usuario';

        // Mapeo básico - en producción deberías consultar vtiger_role
        $roles = [
            'H1' => 'Admin',
            'H2' => 'Usuario',
            'H3' => 'Cliente'
        ];

        return $roles[$roleid] ?? 'Usuario';
    }

    private function getRoleId(string $roleName): string
    {
        $roles = [
            'Admin' => 'H1',
            'Usuario' => 'H2',
            'Cliente' => 'H3'
        ];

        return $roles[$roleName] ?? 'H2';
    }

    public function changePassword(int $userId, string $newPassword, int $modifiedByUserId): bool
    {
        // Verificar que el usuario exista
        $user = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $userId)
            ->where('deleted', 0)
            ->first();

        if (!$user) {
            return false;
        }


        $modifierIsAdmin = DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $modifiedByUserId)
            ->where('is_admin', '1')
            ->exists();

        if ($modifiedByUserId !== $userId && !$modifierIsAdmin) {
            throw new \Exception('No tienes permiso para cambiar esta contraseña');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        DB::connection('vtiger')
            ->table('vtiger_users')
            ->where('id', $userId)
            ->update([
                'user_password' => $hashedPassword,
                'confirm_password' => $hashedPassword,
                'crypt_type' => 'PHASH',
                'date_modified' => now()->format('Y-m-d H:i:s'),
                'modified_user_id' => $modifiedByUserId,
            ]);

        return true;
    }
}
