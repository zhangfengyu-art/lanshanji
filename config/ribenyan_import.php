<?php

return [
  'base_url' => 'https://ribenyan.com',

  // ribenyan ftype → 本站根分类名
  'ftype_roots' => [
    1 => '日本香烟',
    2 => '外国香烟',
    3 => '加热烟草',
    4 => '手卷烟丝',
    5 => '烟斗烟丝',
    6 => '其他烟丝',
  ],

  // 仅抓取这些 ftype（不含套餐、加热设备等）
  'allowed_ftypes' => [1, 2, 3, 4, 5, 6],

  'ftype_tobacco_type' => [
    1 => 'cigarette',
    2 => 'cigarette',
    3 => 'heated_tobacco',
    4 => 'rolling_tobacco',
    5 => 'rolling_tobacco',
    6 => 'rolling_tobacco',
  ],

  'request_delay_ms' => 1200,
  'request_timeout' => 60,
  'user_agent' => 'Mozilla/5.0 (compatible; LanshanjiProductImport/1.0)',

  'image_directory' => 'images',
  'default_sku_title' => '默认规格',
];
