<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_drive' => [
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'config_path' => storage_path('app/google-drive-config.json'), 
        'auth_type' => env('GOOGLE_DRIVE_AUTH_TYPE', 'oauth'),  // 'oauth' o 'service_account'
        'shared_drive_id' => env('GOOGLE_DRIVE_SHARED_DRIVE_ID'), //only for service_account
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'), //only for OAuth
    ],
];
