<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait VtigerDatabaseTrait
{
    protected function setUpVtigerDatabase(): void
    {
        // Limpiar tablas antes de cada test
        DB::connection('vtiger_testing')->table('vtiger_account')->truncate();
        DB::connection('vtiger_testing')->table('vtiger_users')->truncate();
    }

    protected function seedVtigerUsers(): void
    {
        DB::connection('vtiger_testing')->table('vtiger_users')->insert([
            'id' => 1,
            'user_name' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email1' => 'test@user.com',
            'user_password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'status' => 'Active',
            'crypt_type' => 'PHASH'
        ]);
    }

    protected function seedVtigerAccounts(): void
    {
        // Primero: insertar en vtiger_crmentity (donde está 'deleted')
        DB::connection('vtiger_testing')->table('vtiger_crmentity')->insert([
            [
                'crmid' => 1,
                'deleted' => 0, // ✅ Activo
                'setype' => 'Accounts'
            ],
            [
                'crmid' => 2,
                'deleted' => 1, // ✅ Eliminado
                'setype' => 'Accounts'
            ]
        ]);

        // Luego: insertar en vtiger_account (SIN columna 'deleted')
        DB::connection('vtiger_testing')->table('vtiger_account')->insert([
            [
                'accountid' => 1, // ← Debe coincidir con crmid
                'accountname' => 'Cliente Activo',
                'email1' => 'activo@cliente.com',
                'phone' => '1234-5678'
            ],
            [
                'accountid' => 2, // ← Debe coincidir con crmid
                'accountname' => 'Cliente Eliminado',
                'email1' => 'eliminado@cliente.com',
                'phone' => '8765-4321'
            ]
        ]);
    }
}
