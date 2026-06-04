<?php

return [
    'mode' => env('SITE_MODE', 'A'),

    'a_url' => env('SITE_A_URL', 'http://127.0.0.1:8000'),

    'b_url' => env('SITE_B_URL', 'http://127.0.0.1:8001'),

    // 1 人民币 = N 日元（管理员可在后台覆盖）
    'default_jpy_per_cny' => (float) env('JPY_PER_CNY', 22),
];
