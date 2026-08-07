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

    'collab' => [
        'username' => env('COLLAB_USERNAME'),
        'password' => env('COLLAB_PASSWORD'),
        // Hari yang masih disentuh sinkron 30-menitan (hari ini + N-1 hari
        // ke belakang untuk toleransi koreksi telat). Hari di luar window ini
        // dianggap final dan hanya diperbarui lagi oleh full sync harian.
        'sync_window_days' => (int) env('COLLAB_SYNC_WINDOW_DAYS', 2),
    ],

];
