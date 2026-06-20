<?php

return [
    'enabled' => env('DAILY_EXPORT_ENABLED', false),

    'timezone' => env('DAILY_EXPORT_TIMEZONE', 'Asia/Tokyo'),

    'run_at' => env('DAILY_EXPORT_RUN_AT', '05:00'),

    'google' => [
        'service_account_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON', storage_path('app/google-service-account.json')),
        'folder_orders' => env('GOOGLE_DRIVE_FOLDER_ORDERS', ''),
        'folder_stock_prep' => env('GOOGLE_DRIVE_FOLDER_STOCK_PREP', ''),
    ],

    'scopes' => [
        'orders' => 'pending',
        'stock_prep' => 'pending',
    ],
];
