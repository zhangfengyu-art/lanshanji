<?php

return [
    'enabled' => env('DAILY_EXPORT_ENABLED', false),

    'timezone' => env('DAILY_EXPORT_TIMEZONE', 'Asia/Tokyo'),

    'run_at' => env('DAILY_EXPORT_RUN_AT', '17:00'),

    'google' => [
        'service_account_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON', storage_path('app/google-service-account.json')),
        'folder_orders' => env('GOOGLE_DRIVE_FOLDER_ORDERS', ''),
        'folder_stock_prep' => env('GOOGLE_DRIVE_FOLDER_STOCK_PREP', ''),
        // 共享云端硬盘（Google Workspace）：文件夹须在共享盘中，并设 true
        'supports_all_drives' => env('GOOGLE_DRIVE_SUPPORTS_ALL_DRIVES', false),
        // 域委派（Google Workspace 管理员配置后）：以该用户身份上传
        'impersonate_email' => env('GOOGLE_DRIVE_IMPERSONATE_EMAIL', ''),
        // 个人 Gmail：OAuth 客户端 + refresh_token（优先于服务账号）
        'oauth' => [
            'client_id' => env('GOOGLE_DRIVE_OAUTH_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_DRIVE_OAUTH_CLIENT_SECRET', ''),
            'refresh_token' => env('GOOGLE_DRIVE_OAUTH_REFRESH_TOKEN', ''),
        ],
    ],

    'scopes' => [
        'orders' => 'pending',
        'stock_prep' => 'pending',
    ],
];
