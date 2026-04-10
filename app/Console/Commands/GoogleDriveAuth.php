<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client;
use Google\Service\Drive;

class GoogleDriveAuth extends Command
{
    protected $signature = 'drive:auth';
    protected $description = 'Autenticar con Google Drive OAuth y guardar refresh token';

    public function handle()
    {
        $this->info('🔐 Google Drive OAuth Authentication');
        $this->line(str_repeat('=', 60));

        
        $credentialsPath = storage_path('app/google-oauth-credentials.json');
        if (!file_exists($credentialsPath)) {
            $this->error('❌ OAuth credentials not found at: ' . $credentialsPath);
            $this->info('💡 Descarga el archivo JSON desde:');
            $this->line('https://console.cloud.google.com/apis/credentials');
            $this->info('Y guárdalo como: storage/app/google-oauth-credentials.json');
            return 1;
        }

        $this->info('✅ OAuth credentials found');

        
        $client = new Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope([
            Drive::DRIVE_FILE,
            Drive::DRIVE_METADATA_READONLY,
        ]);
        $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $client->setAccessType('offline');  // ← IMPORTANTE para obtener refresh token
        $client->setPrompt('consent');      // ← IMPORTANTE para forzar refresh token

        
        $authUrl = $client->createAuthUrl();
        $this->line('');
        $this->info('📍 PASO 1: Abre esta URL en tu navegador:');
        $this->line($authUrl);
        $this->line('');
        $this->info('📍 PASO 2: Inicia sesión con tu cuenta de Google y autoriza la app');
        $this->info('📍 PASO 3: Copia el código de autorización y pégalo abajo:');
        $this->line('');

        
        $authCode = trim($this->ask('Código de autorización'));
        
        if (!$authCode) {
            $this->error('❌ No se proporcionó código de autorización');
            return 1;
        }

        
        try {
            $token = $client->fetchAccessTokenWithAuthCode($authCode);
        } catch (\Google\Exception $e) {
            $this->error('❌ Error al intercambiar código: ' . $e->getMessage());
            
            if (strpos($e->getMessage(), 'redirect_uri_mismatch') !== false) {
                $this->line('');
                $this->info('💡 Solución para redirect_uri_mismatch:');
                $this->info('1. Ve a Google Cloud Console → APIs & Services → Credentials');
                $this->info('2. Edita tu OAuth 2.0 Client ID');
                $this->info('3. En "Authorized redirect URIs", agrega:');
                $this->line('   urn:ietf:wg:oauth:2.0:oob');
                $this->info('4. Guarda y ejecuta este comando nuevamente');
            }
            return 1;
        }

        
        if (isset($token['error'])) {
            $this->error('❌ Error: ' . $token['error']);
            if (isset($token['error_description'])) {
                $this->error('Descripción: ' . $token['error_description']);
            }
            return 1;
        }

        
        $refreshToken = $token['refresh_token'] ?? null;
        
        if (!$refreshToken) {
            $this->warn('⚠️  ADVERTENCIA: No se obtuvo refresh token');
            $this->line('');
            $this->info('Causas comunes:');
            $this->info('• Ya autorizaste esta app antes (sin access_type=offline)');
            $this->info('• Necesitas revocar acceso primero');
            $this->line('');
            $this->info('🔗 Para revocar acceso:');
            $this->line('https://myaccount.google.com/permissions');
            $this->line('');
            $this->info('Busca tu app y haz clic en "Remove Access"');
            $this->info('Luego ejecuta: php artisan drive:auth nuevamente');
            return 1;
        }

        $this->info('✅ Refresh token obtenido exitosamente');

        
        $configPath = storage_path('app/google-drive-config.json');
        $existingConfig = [];
        
        if (file_exists($configPath)) {
            $existingConfig = json_decode(file_get_contents($configPath), true) ?? [];
        }

        
        $newConfig = [
            'refresh_token' => $refreshToken,
            'access_token' => $token['access_token'] ?? null,
            'expires_in' => $token['expires_in'] ?? null,
            'created' => time(),
            'folder_id' => $existingConfig['folder_id'] ?? env('GOOGLE_DRIVE_FOLDER_ID', ''),
            'shared_drive_id' => $existingConfig['shared_drive_id'] ?? null,
            'auth_type' => 'oauth',
            'updated_at' => now()->toIso8601String(),
        ];

        
        $configDir = dirname($configPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        file_put_contents(
            $configPath,
            json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        
        $this->line('');
        $this->info('🎉 ¡Autenticación exitosa!');
        $this->line(str_repeat('=', 60));
        $this->info('📁 Config guardado en: ' . $configPath);
        $this->info('🔑 Refresh token: ' . substr($refreshToken, 0, 20) . '...');
        $this->info('📂 Folder ID: ' . ($newConfig['folder_id'] ?: '⚠️ No configurado'));
        $this->info('🔐 Auth type: oauth');
        $this->line('');
        
        if (!$newConfig['folder_id']) {
            $this->warn('⚠️  IMPORTANTE: Configura el folder_id en .env o en el archivo config');
            $this->info('');
            $this->info('Opción A: En .env');
            $this->line('GOOGLE_DRIVE_FOLDER_ID=tu_folder_id_aqui');
            $this->line('');
            $this->info('Opción B: En ' . $configPath);
            $this->line('"folder_id": "tu_folder_id_aqui"');
        }

        return 0;
    }
}