<?php

/**
 * EMS 第一区域（中国、中国台湾、韩国）资费表，单位：日元。
 * max_grams 为计费重量上限（含该重量）。
 */
return [
    'zone_label' => '第一区域（中国·中国台湾·韩国）',
    'max_billable_grams' => 16000,
    // 结算计费时每件商品额外计入的包装重量（箱子、泡沫等），单位：克
    'settlement_packaging_grams_per_unit' => 100,
    'tiers' => [
        ['max_grams' => 500, 'fee' => 1450],
        ['max_grams' => 600, 'fee' => 1600],
        ['max_grams' => 700, 'fee' => 1750],
        ['max_grams' => 800, 'fee' => 1900],
        ['max_grams' => 900, 'fee' => 2050],
        ['max_grams' => 1000, 'fee' => 2200],
        ['max_grams' => 1250, 'fee' => 2500],
        ['max_grams' => 1500, 'fee' => 2800],
        ['max_grams' => 1750, 'fee' => 3100],
        ['max_grams' => 2000, 'fee' => 3400],
        ['max_grams' => 2500, 'fee' => 3900],
        ['max_grams' => 3000, 'fee' => 4400],
        ['max_grams' => 3500, 'fee' => 4900],
        ['max_grams' => 4000, 'fee' => 5400],
        ['max_grams' => 4500, 'fee' => 5900],
        ['max_grams' => 5000, 'fee' => 6400],
        ['max_grams' => 5500, 'fee' => 6900],
        ['max_grams' => 6000, 'fee' => 7400],
        ['max_grams' => 7000, 'fee' => 8200],
        ['max_grams' => 8000, 'fee' => 9000],
        ['max_grams' => 9000, 'fee' => 9800],
        ['max_grams' => 10000, 'fee' => 10600],
        ['max_grams' => 11000, 'fee' => 11400],
        ['max_grams' => 12000, 'fee' => 12200],
        ['max_grams' => 13000, 'fee' => 13000],
        ['max_grams' => 14000, 'fee' => 13800],
        ['max_grams' => 15000, 'fee' => 14600],
        ['max_grams' => 16000, 'fee' => 15400],
    ],
    'tobacco_limits' => [
        'max_cigarette_sticks' => 400,
        'max_cigarette_boxes' => 20,
        'max_rolling_tobacco_grams' => 5000,
    ],
];
