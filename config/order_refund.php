<?php

return [
    /*
    | S1 待处理阶段：全额秒退（100%），防刷限制（按用户统计）
    */
    'instant' => [
        'window_hours' => (int) env('ORDER_INSTANT_REFUND_WINDOW_HOURS', 24),
        'max_per_window' => (int) env('ORDER_INSTANT_REFUND_MAX_PER_WINDOW', 3),
        'min_interval_minutes' => (int) env('ORDER_INSTANT_REFUND_MIN_INTERVAL_MINUTES', 5),
    ],
];
