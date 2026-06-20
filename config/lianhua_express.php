<?php

return [
    'enabled' => env('LIANHUA_ENABLED', false),

    'base_url' => rtrim(env('LIANHUA_BASE_URL', 'https://www.lianhua-ex.com'), '/'),

    'account' => env('LIANHUA_ACCOUNT', ''),

    'password' => env('LIANHUA_PASSWORD', ''),

    'list_url' => env('LIANHUA_LIST_URL', ''),

    'list_params' => [
        'pageNumber' => 1,
        'pageSize' => (int) env('LIANHUA_LIST_PAGE_SIZE', 200),
    ],

    'shipped_filter' => array_filter([
        'PreState' => env('LIANHUA_SHIPPED_PRE_STATE'),
        'Status' => env('LIANHUA_SHIPPED_STATUS', '已发货'),
        'SendStatus' => env('LIANHUA_SHIPPED_SEND_STATUS'),
        'TabStatus' => env('LIANHUA_SHIPPED_TAB_STATUS'),
    ], function ($value) {
        return $value !== null && $value !== '';
    }),

    'field_map' => [
        'recipient' => env('LIANHUA_FIELD_RECIPIENT', 'ReceiverName'),
        'phone' => env('LIANHUA_FIELD_PHONE', 'ReceiverPhone'),
        'tracking' => env('LIANHUA_FIELD_TRACKING', 'SendNo'),
        'shipping_method' => env('LIANHUA_FIELD_SHIPPING_METHOD', 'SendType'),
        'status' => env('LIANHUA_FIELD_STATUS', 'Status'),
    ],

    'express_company' => env('LIANHUA_EXPRESS_COMPANY', ''),

    'only_shipping_methods' => array_values(array_filter(array_map('trim', explode(',', env('LIANHUA_ONLY_SHIPPING_METHODS', 'EMS'))))),

    'tracking_pattern' => env('LIANHUA_TRACKING_PATTERN', '/^EN\d{9}JP$/i'),

    'timezone' => env('LIANHUA_SYNC_TIMEZONE', 'Asia/Tokyo'),

    'schedule_between' => [
        env('LIANHUA_SYNC_BETWEEN_START', '08:00'),
        env('LIANHUA_SYNC_BETWEEN_END', '22:00'),
    ],

    'probe_html_path' => storage_path('app/lianhua_probe_storage_pre_search.html'),

    'discovery_cache_path' => storage_path('app/lianhua_endpoint_cache.json'),

    'probe_max_candidates' => (int) env('LIANHUA_PROBE_MAX_CANDIDATES', 10),

    'probe_max_attempts' => (int) env('LIANHUA_PROBE_MAX_ATTEMPTS', 80),
];
