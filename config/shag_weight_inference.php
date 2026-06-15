<?php

/**
 * 日本手卷烟丝（Shag）重量推断配置。
 * 仅用于 tobacco_type = rolling_tobacco 的商品。
 */
return [
    'default_grams' => 25,

    // 按克数从高到低匹配；带 require 正则的条目需同时满足品牌与包装关键词。
    'brand_tiers' => [
        [
            'grams' => 450,
            'needles' => ['PL88 Red', 'PL88・レッド', '红PL88', 'PL88红', '巨桶', '450g'],
        ],
        [
            'grams' => 200,
            'needles' => ['PL88', 'ＰＬ８８', 'PL-88'],
            'require' => '/(桶|バケツ|bucket|塑料桶)/ui',
        ],
        [
            'grams' => 150,
            'needles' => ['Wild Bison', '野生野牛', 'ワイルドバイソン', 'American Bison', 'アメリカンバイソン', '美洲野牛'],
            'require' => '/(罐|缶|tin|can|罐装|缶入|铁罐)/ui',
        ],
        [
            'grams' => 100,
            'needles' => ['Pueblo', 'プエブロ', 'RAW', 'Pepe', 'ペペ', '佩佩'],
            'require' => '/(罐|缶|tin|can|罐装|缶入|铁罐|筒)/ui',
        ],
        [
            'grams' => 50,
            'needles' => [
                'Golden Virginia', 'ゴールデンバージニア', '金V', 'GV',
                'Drum', 'ドラム', '鼓牌',
                'Akropolis', 'アクロポリス', '雅典卫城',
            ],
        ],
        [
            'grams' => 40,
            'needles' => [
                'Colts', 'コルツ', '柯尔特',
                'Bali Shag', 'バリシャグ', '巴厘岛',
                'Papillon', 'パピヨン', '蝴蝶',
                'American Bison', 'アメリカンバイソン', '美洲野牛',
            ],
            'exclude' => '/(罐|缶|tin|can|罐装|缶入|铁罐)/ui',
        ],
        [
            'grams' => 30,
            'needles' => [
                'Choice', 'チョイス', '乔伊斯',
                'Pueblo', 'プエブロ', '普韦布洛',
                'Ark Royal', 'アークローヤル', '老船长', '皇家方舟',
                'Manitou', 'マニトウ', '马尼图',
                'Black Spider', 'ブラックスパイダー', '黑蜘蛛',
                "D'ora", 'Dora', 'ドラ', '多拉',
            ],
            'exclude' => '/(罐|缶|tin|can|罐装|缶入|铁罐)/ui',
        ],
        [
            'grams' => 25,
            'needles' => [
                'Che', 'チェ', '切格瓦拉',
                'Flandria', 'フランドリア',
                'Excellent', 'エクセレント',
                'Amber Leaf', 'アンバーリーフ', '琥珀叶', '琥珀',
            ],
        ],
        [
            'grams' => 25,
            'needles' => ['Camel', 'キャメル', '骆驼'],
            'require' => '/(シャグ|shag|パウチ|pouch|手卷|手捲|烟丝|煙草)/ui',
            'exclude' => '/(硬盒|软盒|ボックス|硬包|软包)/ui',
        ],
        [
            'grams' => 20,
            'needles' => ['Redfield', 'レッドフィールド', 'ACREMA', 'アクレマ', 'Acrema'],
        ],
        [
            'grams' => 11,
            'needles' => ['TOP', 'トップ', '顶级'],
        ],
    ],

    // 标题有袋装/シャグ但品牌未命中时：有罐装关键词的默认铁罐规格
    'tin_default_grams' => 100,
];
