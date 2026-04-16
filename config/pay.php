<?php

return [
    'alipay' => [
        'app_id'         => '',
        'ali_public_key' => '',
        'private_key'    => '',
        'log'            => [
            'file' => storage_path('logs/alipay.log'),
        ],
    ],

    'wechat' => [
        'app_id'      => '',
        'mch_id'      => '',
        'key'         => '',
        'cert_client' => '',
        'cert_key'    => '',
        'http'        => [
            // 生产建议保持 true；本地若缺少 CA 证书可在 .env 设 WECHAT_HTTP_VERIFY=false
            'verify' => env('WECHAT_HTTP_VERIFY', null),
        ],
        'log'         => [
            'file' => storage_path('logs/wechat_pay.log'),
        ],
    ],
];
