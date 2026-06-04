<?php

/**
 * 一键全站回归：夹具 → Phase1 HTTP/规则 → Phase2 下单/支付/后台
 * 用法: php scripts/run_full_regression.php
 */

$php = getenv('PHP_BINARY') ?: 'php';
if (is_file('D:\\EDGEdownload\\phpstudy_pro\\Extensions\\php\\php7.3.4nts\\php.exe')) {
    $php = 'D:\\EDGEdownload\\phpstudy_pro\\Extensions\\php\\php7.3.4nts\\php.exe';
}

$root = dirname(__DIR__);
chdir($root);

$steps = [
    'regression_fixtures.php' => '创建回归测试商品',
    'full_regression_test.php' => 'Phase1：页面/规则/购物车',
    'full_regression_test_phase2.php' => 'Phase2：下单/支付/后台',
];

$failed = false;
foreach ($steps as $script => $label) {
    echo "\n>>> {$label} ({$script})\n";
    passthru(escapeshellarg($php).' '.escapeshellarg($root.'/scripts/'.$script), $code);
    if ($code !== 0) {
        $failed = true;
        echo "!!! 步骤失败 exit={$code}\n";
        break;
    }
}

echo $failed ? "\n全站回归未全部通过。\n" : "\n全站回归全部通过（请查看警告项）。\n";
exit($failed ? 1 : 0);
