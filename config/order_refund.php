<?php

return [
    /*
    | S1 待处理阶段：未开始处理时可全额秒退（100%），防刷限制（按用户统计）
    */
    'instant' => [
        'window_hours' => (int) env('ORDER_INSTANT_REFUND_WINDOW_HOURS', 24),
        'max_per_window' => (int) env('ORDER_INSTANT_REFUND_MAX_PER_WINDOW', 3),
        'min_interval_minutes' => (int) env('ORDER_INSTANT_REFUND_MIN_INTERVAL_MINUTES', 5),
        // 东京时间每日截止：支付于该时刻前 → 当日该时刻锁定；之后支付 → 次日该时刻锁定
        'daily_lock_at' => env('ORDER_INSTANT_REFUND_DAILY_LOCK_AT', '17:00'),
        'daily_lock_timezone' => env('ORDER_INSTANT_REFUND_DAILY_LOCK_TIMEZONE', 'Asia/Tokyo'),
    ],

    /*
    | 等待供应商配送：满 N 小时后可全额退（否则 80%）
    */
    'waiting_supplier_hours' => (int) env('ORDER_WAITING_SUPPLIER_HOURS', 7 * 24),

    /*
    | 取消费用比例（20% 取消费 = 退 80%）
    */
    'cancellation_fee_ratio' => (float) env('ORDER_CANCELLATION_FEE_RATIO', 0.20),

    'admin_reasons' => [
        'cannot_procure' => '无法采购/备货',
        'supplier_shortage' => '供应商无法正常供货',
        'customs_limit' => '海关/限购无法发出',
        'user_requested' => '用户申请取消',
        'duplicate_order' => '重复下单',
        'address_issue' => '地址/身份信息问题',
        'quality_issue' => '商品质量问题',
        'logistics_exception' => '物流异常无法继续',
        's4_special_approval' => '已发货特批退款',
        'other' => '其他',
    ],
];
