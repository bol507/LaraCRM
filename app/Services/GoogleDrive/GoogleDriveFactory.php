<?php

namespace App\Services\GoogleDrive;

use App\Application\Contracts\GoogleDriveServiceInterface;
use Illuminate\Support\Facades\Config;

class GoogleDriveFactory
{
    public static function create(): GoogleDriveServiceInterface
    {
        $authType = Config::get('services.google_drive.auth_type', 'oauth');
        
        
        $config = [
            'folder_id' => Config::get('services.google_drive.folder_id'),
            'shared_drive_id' => Config::get('services.google_drive.shared_drive_id'),
            'refresh_token' => Config::get('services.google_drive.refresh_token'),
        ];

        return match ($authType) {
            'service_account' => new GoogleDriveServiceAccount($config),  
            'oauth' => new GoogleDriveOAuth($config),
            default => throw new \InvalidArgumentException("Unknown auth type: {$authType}"),
        };
    }
}