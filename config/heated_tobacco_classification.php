<?php

/**
 * 加热烟自动归类规则（分类名 / 商品标题）。
 * 匹配且非排除词 → 建议或设为 tobacco_type = heated_tobacco。
 */
return [
  'category_name_patterns' => [
    '加热烟',
    '加热烟草',
    '加熱煙',
    'IQOS',
    'イルマ',
    'テリア',
    'TEREA',
    'Ploom',
    'glo',
    'ヒートスティック',
    'HEATSTICK',
  ],

  'title_include_patterns' => [
    '加热烟',
    '加热烟草',
    '加熱',
    'IQOS',
    'イルマ',
    'テリア',
    'TEREA',
    'Ploom',
    'プルーム',
    'ヒートスティック',
    'HEATSTICK',
    'HEETS',
    'ネオ',
    'NEO',
    'スティック',
  ],

  // 含这些词视为设备/配件，不归为加热烟
  'title_exclude_patterns' => [
    '加热器',
    '加熱器',
    'デバイス',
    '本体',
    '機器',
    '充电器',
    '充電',
    '清洁',
    'クリーン',
    'ケース',
    '收纳',
    '配件',
    '耗材',
  ],

  // 每包/条默认支数（仅当 unit_sticks 为空时写入）
  'default_unit_sticks' => 20,
];
