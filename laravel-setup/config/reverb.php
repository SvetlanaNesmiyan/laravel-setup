<?php

return [
    'apps' => [
        [
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'allowed_origins' => ['localhost'],
        ],
    ],

    'scaling' => [
        'driver' => env('REVERB_SCALING_DRIVER', 'single'),
    ],

    'options' => [
        'host' => '0.0.0.0',
        'port' => 8080,
        'heartbeat_interval' => 45,
        'app_max_lifetime' => 7200,
    ],
];
