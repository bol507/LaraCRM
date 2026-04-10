<?php
// app/Console/Commands/GoogleDriveDiagnose.php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GoogleDriveDiagnose extends Command
{
    protected $signature = 'drive:diagnose';
    protected $description = 'Diagnosticar problemas de autenticación de Google Drive';

    public function handle()
    {
        $this->info('🔍 Google Drive Authentication Diagnostic');
        $this->line(str_repeat('=', 60));
        
        // 1. Verificar archivo de credenciales OAuth
        $credsPath = storage_path('app/google-oauth-credentials.json');
        $this->checkFile('OAuth Credentials', $credsPath, ['client_id', 'client_secret']);
        
        // 2. Verificar archivo de configuración
        $configPath = storage_path('app/google-drive-config.json');
        $configExists = $this->checkFile('Drive Config', $configPath, ['refresh_token', 'folder_id']);
        
        if ($configExists) {
            $config = json_decode(file_get_contents($configPath), true);
            $refreshToken = $config['refresh_token'] ?? null;
            
            $this->line('');
            $this->info('📊 Token Details:');
            $this->line('  • Has refresh_token: ' . (!empty($refreshToken) ? '✅ YES' : '❌ NO'));
            $this->line('  • Refresh token length: ' . strlen($refreshToken ?? ''));
            $this->line('  • Folder ID: ' . ($config['folder_id'] ?? '❌ NOT SET'));
            $this->line('  • Auth type: ' . ($config['auth_type'] ?? 'unknown'));
            $this->line('  • Updated at: ' . ($config['updated_at'] ?? 'unknown'));
            
            // 3. Verificar permisos
            $this->line('');
            $this->info('📁 File Permissions:');
            $this->line('  • Config file writable: ' . (is_writable($configPath) ? '✅ YES' : '❌ NO'));
            $this->line('  • Config file permissions: ' . substr(sprintf('%o', fileperms($configPath)), -4));
        }
        
        // 4. Intentar instanciar el servicio
        $this->line('');
        $this->info('🔧 Testing Service Instantiation:');
        try {
            $drive = app(\App\Application\Contracts\GoogleDriveServiceInterface::class);
            $this->info('  ✅ Service instantiated successfully');
            $this->line('  • Auth type: ' . $drive->getAuthType());
        } catch (\Exception $e) {
            $this->error('  ❌ Service instantiation failed: ' . $e->getMessage());
            $this->line('  • Check logs: storage/logs/laravel.log');
        }
        
        return 0;
    }
    
    private function checkFile(string $name, string $path, array $requiredKeys): bool
    {
        $this->line('');
        $this->info("📄 {$name}:");
        $this->line('  • Path: ' . $path);
        $this->line('  • Exists: ' . (file_exists($path) ? '✅ YES' : '❌ NO'));
        
        if (!file_exists($path)) {
            return false;
        }
        
        $content = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('  • JSON valid: ❌ INVALID (' . json_last_error_msg() . ')');
            return false;
        }
        
        $this->line('  • JSON valid: ✅ YES');
        
        foreach ($requiredKeys as $key) {
            $hasKey = isset($content[$key]) && !empty($content[$key]);
            $this->line('  • Has "' . $key . '": ' . ($hasKey ? '✅ YES' : '❌ NO'));
        }
        
        return true;
    }
}