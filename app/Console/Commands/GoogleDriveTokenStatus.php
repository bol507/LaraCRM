<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GoogleDriveTokenStatus extends Command
{
    protected $signature = 'drive:token-status';
    protected $description = 'Check Google Drive OAuth token status';

    public function handle()
    {
        $configPath = storage_path('app/google-drive-config.json');
        
        if (!file_exists($configPath)) {
            $this->error('❌ Config file not found: ' . $configPath);
            return 1;
        }
        
        $config = json_decode(file_get_contents($configPath), true);
        
        $this->info('🔍 Google Drive Token Status');
        $this->line('');
        
        // Refresh token
        $refreshToken = $config['refresh_token'] ?? null;
        if ($refreshToken) {
            $this->info('✅ Refresh token: Present');
            $this->line('   Length: ' . strlen($refreshToken) . ' characters');
            $this->line('   Starts with: ' . substr($refreshToken, 0, 20) . '...');
        } else {
            $this->error('❌ Refresh token: MISSING');
            $this->info('💡 Run: php artisan drive:auth to re-authorize');
        }
        
        $this->line('');
        
        // Access token
        $accessToken = $config['access_token'] ?? null;
        if ($accessToken) {
            $this->info('✅ Access token: Present');
            $this->line('   Starts with: ' . substr($accessToken, 0, 20) . '...');
        } else {
            $this->warn('⚠️  Access token: Not set (will be obtained on first API call)');
        }
        
        $this->line('');
        
        // Expiration
        $expiresIn = $config['expires_in'] ?? null;
        $created = $config['created'] ?? null;
        
        if ($expiresIn && $created) {
            $expiresAt = $created + $expiresIn;
            $now = time();
            $remaining = $expiresAt - $now;
            
            if ($remaining > 0) {
                $minutes = floor($remaining / 60);
                $this->info("⏰ Access token expires in: {$minutes} minutes");
            } else {
                $this->error("⏰ Access token: EXPIRED ({$remaining} seconds ago)");
            }
        } else {
            $this->warn('⚠️  Expiration info: Not available');
        }
        
        $this->line('');
        $this->info('📁 Config file: ' . $configPath);
        $this->info('🔄 Last updated: ' . ($config['updated_at'] ?? 'Unknown'));
        
        return 0;
    }
}