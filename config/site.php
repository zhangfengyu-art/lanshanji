<?php

return [
    'mode' => env('SITE_MODE', 'A'),

    'a_url' => env('SITE_A_URL', 'http://127.0.0.1:8000'),

    'b_url' => env('SITE_B_URL', 'http://127.0.0.1:8001'),

    // 1 人民币 = N 日元（管理员可在后台覆盖）
    'default_jpy_per_cny' => (float) env('JPY_PER_CNY', 22),

    // B 站 ICP 备案（仅 B 站页脚展示）
    'icp_record' => env('SITE_ICP_RECORD', '苏ICP备2026023642号'),
    'icp_link' => env('SITE_ICP_LINK', 'https://beian.miit.gov.cn/'),

    // A 站后台发货：物流公司仅允许以下选项
    'express_carriers_a' => ['EMS自缴税', '顺丰'],
];
