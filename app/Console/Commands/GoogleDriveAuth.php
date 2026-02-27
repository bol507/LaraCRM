<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client;
use Google\Service\Drive;

class GoogleDriveAuth extends Command
{
    protected $signature = 'drive:auth';
    protected $description = 'Autenticar con Google Drive y guardar refresh token';

    public function handle()
    {
        // ✅ Configurar cliente OAuth
        $client = new Client();
        $client->setAuthConfig(storage_path('app/google-oauth-credentials.json')); // Archivo descargado
        $client->addScope(Drive::DRIVE_FILE);
        $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob'); // Para aplicaciones de escritorio
        $client->setAccessType('offline'); // ✅ Importante: obtener refresh token
        $client->setPrompt('consent'); // ✅ Forzar consentimiento para obtener refresh token

        // ✅ Generar URL de autorización
        $authUrl = $client->createAuthUrl();
        $this->info('Abre esta URL en tu navegador y autoriza la aplicación:');
        $this->line($authUrl);
        $this->info('');
        $this->info('Después de autorizar, copia el código de autorización y pégalo aquí:');

        // ✅ Obtener código de autorización
        $authCode = trim($this->ask('Código de autorización:'));

        // ✅ Intercambiar código por tokens
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        if (isset($accessToken['error'])) {
            $this->error('Error al obtener tokens: ' . $accessToken['error']);
            return 1;
        }

        // ✅ Guardar refresh token en .env
        $refreshToken = $accessToken['refresh_token'] ?? null;
        
        if (!$refreshToken) {
            $this->error('⚠️ ADVERTENCIA: No se obtuvo refresh token.');
            $this->error('Asegúrate de haber usado setPrompt("consent") y que es la primera autorización.');
            $this->error('Ejecuta el comando nuevamente para forzar una nueva autorización.');
            return 1;
        }

        // ✅ Guardar en archivo de configuración
        $configPath = storage_path('app/google-drive-config.json');
        file_put_contents($configPath, json_encode([
            'client_id' => $client->getClientId(),
            'client_secret' => $client->getClientSecret(),
            'refresh_token' => $refreshToken,
            'folder_id' => '', // Se configurará manualmente después
        ], JSON_PRETTY_PRINT));

        $this->info('');
        $this->info('✅ Autenticación exitosa!');
        $this->info('Refresh token guardado en: ' . $configPath);
        $this->info('');
        $this->info('⚠️ IMPORTANTE: Ahora debes:');
        $this->info('1. Crear una carpeta en tu Google Drive llamada "CW-Projects"');
        $this->info('2. Obtener el ID de la carpeta (desde la URL)');
        $this->info('3. Editar ' . $configPath . ' y agregar el folder_id');
        $this->info('');
        $this->info('Ejemplo de URL: https://drive.google.com/drive/folders/1AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPp');
        $this->info('ID de la carpeta: 1AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPp');

        return 0;
    }
}