<?php

/**
 * 日本加热烟（加热香烟 / 加热烟条）EMS 计重配置。
 * 仅用于 tobacco_type = heated_tobacco；毛重为单包（通常 20 支 + 纸盒）。
 */
return [
    'default_sticks' => 20,
    'default_grams' => 24,

    // 按优先级从高到低匹配
    'product_rules' => [
        [
            'grams' => 29,
            'needles' => ['neo', 'ネオ', 'NEO'],
            'require' => '/(爆珠|クリック|click|カプセル)/ui',
        ],
        [
            'grams' => 18,
            'needles' => ['Kent', 'ケント', '健牌'],
            'require' => '/(超细|超細|スリム|slim|glo\s*pro|グロープロ|プロ)/ui',
        ],
        [
            'grams' => 25,
            'needles' => ['TEREA', 'テリア', '泰瑞雅'],
        ],
        [
            'grams' => 24,
            'needles' => ['SENTIA', 'センティア', '森缇亚'],
        ],
        [
            'grams' => 29,
            'needles' => ['Lucky Strike', 'ラッキーストライク', '好彩'],
            'require' => '/(glo|グロー|hyper|ハイパー|加热|ヒート)/ui',
        ],
        [
            'grams' => 29,
            'needles' => ['neo', 'ネオ', 'NEO'],
            'require' => '/(glo|グロー|hyper|ハイパー|加热|ヒート)/ui',
        ],
        [
            'grams' => 22,
            'needles' => ['Marlboro', 'マールボロ', '万宝路'],
            'require' => '/(HeatStick|ヒートスティック|HEATSTICK|IQOS\s*3|加热|ヒート)/ui',
            'exclude' => '/(ILUMA|イルマ|TEREA|テリア)/ui',
        ],
        [
            'grams' => 22,
            'needles' => ['HEETS', 'ヒーツ'],
        ],
        [
            'grams' => 23,
            'needles' => ['MEVIUS', 'メビウス', '梅比乌斯'],
            'require' => '/(Ploom|プルーム|加热|ヒート|スティック|stick)/ui',
            'exclude' => '/(纸烟|紙巻|香烟|ソフト|ボックス|硬盒|软包)/ui',
        ],
        [
            'grams' => 23,
            'needles' => ['Camel', 'キャメル', '骆驼'],
            'require' => '/(Ploom|プルーム|加热|ヒート|スティック|stick)/ui',
            'exclude' => '/(纸烟|紙巻|香烟|シャグ|shag|ソフト|ボックス)/ui',
        ],
    ],

    // 品牌未命中时，按设备/系列关键词兜底
    'device_defaults' => [
        ['grams' => 25, 'pattern' => '/(ILUMA|イルマ|TEREA|テリア)/ui'],
        ['grams' => 22, 'pattern' => '/(IQOS\s*3|HeatStick|ヒートスティック|HEATSTICK|HEETS|ヒーツ)/ui'],
        ['grams' => 29, 'pattern' => '/(glo\s*hyper|グローハイパー|グロー\s*ハイパー)/ui'],
        ['grams' => 18, 'pattern' => '/(glo\s*pro|グロープロ|グロー\s*プロ)/ui'],
        ['grams' => 23, 'pattern' => '/(Ploom\s*X|プルーム|Ploom)/ui'],
        ['grams' => 24, 'pattern' => '/(IQOS|アイコス|加热|ヒート|スティック)/ui'],
    ],
];
