<?php

/**
 * 日本常规成品纸卷烟（紙巻たばこ）EMS 计重配置。
 * 仅用于 tobacco_type = cigarette 的商品；毛重含烟支、滤嘴与外包装。
 */
return [
    'default_sticks' => 20,
    'default_box_grams' => 26,
    'default_soft_grams' => 23,

    // 支数 × 包装 → 克数（优先于默认）
    'stick_profiles' => [
        10 => [
            'mini' => 12,
            'default' => 12,
        ],
        14 => 20,
        19 => 21,
        20 => [
            'soft' => 23,
            'box' => 26,
            'default' => 26,
        ],
        50 => [
            'can' => 140,
            'default' => 140,
        ],
    ],

    // 高优先级特例（先匹配更具体的品牌+包装组合）
    'product_rules' => [
        [
            'grams' => 82,
            'needles' => ['The Peace', 'ザ・ピース', 'ザピース', 'THE PEACE'],
            'sticks' => 20,
            'exclude' => '/(50\s*支|圆罐|丸缶|缶入|罐装)/ui',
        ],
        [
            'grams' => 140,
            'needles' => ['Peace', 'ピース', '和平'],
            'require' => '/(罐|缶|can|铁罐|50\s*支|缶入|罐装)/ui',
        ],
        [
            'grams' => 12,
            'needles' => ['Hope', 'ホープ', '霍普'],
            'sticks' => 10,
        ],
        [
            'grams' => 12,
            'needles' => ['Peace', 'ピース', '和平', '小和平'],
            'require' => '/(迷你|mini|ミニ|10\s*支)/ui',
            'exclude' => '/(罐|缶|can|铁罐|50\s*支)/ui',
        ],
        [
            'grams' => 20,
            'needles' => ['Kent', 'ケント', '健牌'],
            'sticks' => 14,
        ],
    ],

    'soft_pattern' => '/(软包|ソフト|soft|軟包)/ui',
    'box_pattern' => '/(硬盒|硬包|ボックス|box|Box|ハード)/ui',
    'mini_pattern' => '/(迷你|mini|ミニ|省税|异形)/ui',
    'can_pattern' => '/(罐装|缶入|铁罐|铁盒|缶|can|tin|听装|筒装)/ui',
    'slim_pattern' => '/(细支|スリム|slim|100\'?s|长支)/ui',
    'cigarillo_pattern' => '/(小雪茄|シガー|cigarillo)/ui',
];
