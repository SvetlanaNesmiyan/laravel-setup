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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org/bot'),
        'enabled' => env('TELEGRAM_ENABLED', true),
        'debug' => env('TELEGRAM_DEBUG', false),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'timeout' => env('TELEGRAM_TIMEOUT', 30),
        'retry_times' => env('TELEGRAM_RETRY_TIMES', 3),
        'retry_after' => env('TELEGRAM_RETRY_AFTER', 60),
    ],
];
