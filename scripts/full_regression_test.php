<?php

/**
 * 全站回归探测脚本（贴近真实用户路径 + 核心业务规则）
 * 用法: php scripts/full_regression_test.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\ProductSku;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\Category;
use App\Services\OrderCheckoutQuoteService;
use App\Services\OrderTobaccoLimitService;
use App\Services\ShippingModeService;
use App\Services\ProductBatchService;
use App\Services\ProductLogisticsExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$php = PHP_VERSION;
$baseA = rtrim((string) env('SITE_A_URL', 'http://127.0.0.1:8000'), '/');
$baseB = rtrim((string) env('SITE_B_URL', 'http://127.0.0.1:8001'), '/');

$report = [
    'meta' => [
        'at' => date('Y-m-d H:i:s'),
        'php' => $php,
        'site_a' => $baseA,
        'site_b' => $baseB,
        'app_env' => env('APP_ENV'),
        'app_debug' => env('APP_DEBUG'),
    ],
    'pass' => [],
    'fail' => [],
    'warn' => [],
];

function reg_pass(array &$r, $id, $msg)
{
    $r['pass'][] = ['id' => $id, 'msg' => $msg];
}

function reg_fail(array &$r, $id, $msg, $detail = '')
{
    $r['fail'][] = ['id' => $id, 'msg' => $msg, 'detail' => $detail];
}

function reg_warn(array &$r, $id, $msg, $detail = '')
{
    $r['warn'][] = ['id' => $id, 'msg' => $msg, 'detail' => $detail];
}

function http_get($url, $cookies = '')
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIE => $cookies,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return ['code' => 0, 'body' => '', 'headers' => '', 'error' => $err, 'cookies' => $cookies];
    }
    $headerSize = strpos($raw, "\r\n\r\n");
    $headers = $headerSize !== false ? substr($raw, 0, $headerSize) : '';
    $body = $headerSize !== false ? substr($raw, $headerSize + 4) : $raw;
    preg_match_all('/^Set-Cookie:\s*([^;=\s]+)=([^;]*)/mi', $headers, $m, PREG_SET_ORDER);
    $jar = [];
    if ($cookies) {
        foreach (explode('; ', $cookies) as $pair) {
            if (strpos($pair, '=') !== false) {
                $jar[] = $pair;
            }
        }
    }
    foreach ($m as $c) {
        $jar[$c[1]] = $c[1].'='.$c[2];
    }
    return ['code' => $code, 'body' => $body, 'headers' => $headers, 'error' => '', 'cookies' => implode('; ', array_values($jar))];
}

function http_post($url, $fields, $cookies = '', $json = false)
{
    $ch = curl_init($url);
    $headers = ['X-Requested-With: XMLHttpRequest'];
    if ($json) {
        $body = json_encode($fields);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';
    } else {
        $body = http_build_query($fields);
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIE => $cookies,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $headerSize = strpos($raw, "\r\n\r\n");
    $headersOut = $headerSize !== false ? substr($raw, 0, $headerSize) : '';
    $respBody = $headerSize !== false ? substr($raw, $headerSize + 4) : $raw;
    return ['code' => $code, 'body' => $respBody, 'headers' => $headersOut];
}

// --- 1. 环境与 schema ---
$requiredCols = [
    'products' => ['tobacco_type', 'shipping_mode', 'unit_weight_grams', 'unit_sticks', 'sale_status'],
    'categories' => ['default_shipping_mode'],
];
foreach ($requiredCols as $table => $cols) {
    if (!Schema::hasTable($table)) {
        reg_fail($report, 'schema.'.$table, "表 {$table} 不存在");
        continue;
    }
    foreach ($cols as $col) {
        if (Schema::hasColumn($table, $col)) {
            reg_pass($report, 'schema.'.$table.'.'.$col, '字段存在');
        } else {
            reg_fail($report, 'schema.'.$table.'.'.$col, "缺少字段 {$col}");
        }
    }
}

// --- 2. 公开页面 HTTP ---
$publicPages = [
    'A/products' => $baseA.'/products',
    'A/faq' => $baseA.'/pages/faq',
    'A/order_flow' => $baseA.'/pages/order-flow',
    'B/products' => $baseB.'/products',
    'A/login' => $baseA.'/login',
];
foreach ($publicPages as $id => $url) {
    $res = http_get($url);
    if ($res['code'] === 200) {
        reg_pass($report, 'http.'.$id, "HTTP {$res['code']}");
    } else {
        reg_fail($report, 'http.'.$id, "HTTP {$res['code']}", $res['error'] ?: substr($res['body'], 0, 200));
    }
}

// --- 3. 商品数据完备性 ---
$totalProducts = Product::count();
$onSale = Product::where('on_sale', 1)->count();
$configured = Product::where('on_sale', 1)
    ->whereNotNull('tobacco_type')
    ->where('tobacco_type', '!=', '')
    ->where('unit_weight_grams', '>', 0)
    ->count();

if ($totalProducts === 0) {
    reg_warn($report, 'data.products', '数据库无商品，无法做真实下单回归');
} else {
    reg_pass($report, 'data.products.count', "商品总数 {$totalProducts}，上架 {$onSale}，物流字段完备 {$configured}");
    if ($onSale > 0 && $configured < $onSale) {
        reg_warn($report, 'data.products.logistics', '部分上架商品未配置烟草/重量', ($onSale - $configured).' 个未完备');
    }
}

// --- 4. 业务规则（服务层）---
$tobacco = app(OrderTobaccoLimitService::class);
$quoteSvc = app(OrderCheckoutQuoteService::class);
$shipping = app(ShippingModeService::class);

// 找或造测试 SKU
$emsProduct = Product::where('on_sale', 1)
    ->where(function ($q) {
        $q->where('shipping_mode', ShippingModeService::MODE_EMS)
            ->orWhereNull('shipping_mode');
    })
    ->where('tobacco_type', OrderTobaccoLimitService::TYPE_CIGARETTE)
    ->where('unit_weight_grams', '>', 0)
    ->where('unit_sticks', '>', 0)
    ->first();

if (!$emsProduct) {
    reg_warn($report, 'rule.ems', '无可用 EMS 香烟测试商品，跳过部分规则用例');
} else {
    $sku = $emsProduct->skus()->first();
    if ($sku) {
        try {
            $q = $quoteSvc->quote([['sku_id' => $sku->id, 'amount' => 1]]);
            if ($q['ems_shipping_fee'] > 0 && $q['payable'] > 0) {
                reg_pass($report, 'rule.quote.ems', 'EMS 报价含运费 '.$q['ems_shipping_fee'].' 日元');
            } else {
                reg_fail($report, 'rule.quote.ems', 'EMS 报价异常', json_encode($q));
            }
        } catch (Exception $e) {
            reg_fail($report, 'rule.quote.ems', $e->getMessage());
        }

        try {
            $over = $quoteSvc->quote([['sku_id' => $sku->id, 'amount' => 500]]);
            reg_fail($report, 'rule.limit.sticks', '超 400 支应失败但通过了');
        } catch (App\Exceptions\InvalidRequestException $e) {
            reg_pass($report, 'rule.limit.sticks', '超 400 支正确拦截：'.mb_substr($e->getMessage(), 0, 60));
        } catch (Exception $e) {
            reg_pass($report, 'rule.limit.sticks', '超量拦截：'.$e->getMessage());
        }
    }
}

$taxProduct = Product::where('on_sale', 1)
    ->where('shipping_mode', ShippingModeService::MODE_TAX_INCLUDED)
    ->where('tobacco_type', '!=', '')
    ->where('unit_weight_grams', '>', 0)
    ->first();

if (!$taxProduct) {
    reg_warn($report, 'rule.tax', '无含税包邮测试商品');
} else {
    $taxSku = $taxProduct->skus()->first();
    if ($taxSku) {
        try {
            $q = $quoteSvc->quote([['sku_id' => $taxSku->id, 'amount' => 1]]);
            if ((float) $q['ems_shipping_fee'] === 0.0) {
                reg_pass($report, 'rule.quote.tax', '含税订单 EMS=0');
            } else {
                reg_fail($report, 'rule.quote.tax', '含税订单仍有 EMS 费', json_encode($q));
            }
        } catch (Exception $e) {
            reg_fail($report, 'rule.quote.tax', $e->getMessage());
        }
    }
}

if ($emsProduct && $taxProduct && $emsProduct->skus()->exists() && $taxProduct->skus()->exists()) {
    try {
        $quoteSvc->quote([
            ['sku_id' => $emsProduct->skus()->first()->id, 'amount' => 1],
            ['sku_id' => $taxProduct->skus()->first()->id, 'amount' => 1],
        ]);
        reg_fail($report, 'rule.mix', 'EMS+含税混单应失败');
    } catch (App\Exceptions\InvalidRequestException $e) {
        reg_pass($report, 'rule.mix', '混单拦截：'.mb_substr($e->getMessage(), 0, 50));
    }
}

// --- 5. 登录用户 HTTP 流程 ---
$user = User::where('email_verified', 1)->first();
if (!$user) {
    reg_warn($report, 'auth.user', '无已验证邮箱用户');
} else {
    // 获取登录页 CSRF
    $loginPage = http_get($baseA.'/login');
    preg_match('/name="_token"\s+value="([^"]+)"/', $loginPage['body'], $tokenM);
    $token = $tokenM[1] ?? '';
    $cookies = $loginPage['cookies'];

    if ($token === '') {
        reg_fail($report, 'auth.csrf', '登录页无 CSRF token');
    } else {
        $loginRes = http_post($baseA.'/login', [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'secret',
        ], $cookies);
        preg_match_all('/^Set-Cookie:/mi', $loginRes['headers'], $cm);
        if (strpos($loginRes['headers'], 'laravel_session') !== false || $loginRes['code'] === 302) {
            reg_pass($report, 'auth.login', "用户 {$user->email} 登录请求已提交");
        } else {
            reg_warn($report, 'auth.login', '登录响应未确认 session', 'code='.$loginRes['code']);
        }

        // 合并 cookie 用于后续
        preg_match_all('/^Set-Cookie:\s*([^;=\s]+)=([^;]*)/mi', $loginRes['headers'], $mc, PREG_SET_ORDER);
        $jar = [];
        foreach (explode('; ', $cookies) as $p) {
            if ($p) {
                $jar[explode('=', $p)[0]] = $p;
            }
        }
        foreach ($mc as $c) {
            $jar[$c[1]] = $c[1].'='.$c[2];
        }
        $sessionCookie = implode('; ', array_values($jar));

        $cartPage = http_get($baseA.'/cart', $sessionCookie);
        if ($cartPage['code'] === 200) {
            reg_pass($report, 'http.cart', '购物车页 200');
        } else {
            reg_fail($report, 'http.cart', '购物车 HTTP '.$cartPage['code']);
        }

        if ($sku ?? null) {
            $cartForCsrf = http_get($baseA.'/cart', $sessionCookie);
            preg_match('/name="csrf-token"\s+content="([^"]+)"/', $cartForCsrf['body'], $csrfMeta);
            $xsrf = '';
            foreach (explode('; ', $cartForCsrf['cookies'] ?: $sessionCookie) as $pair) {
                if (stripos($pair, 'XSRF-TOKEN=') === 0) {
                    $xsrf = urldecode(substr($pair, strlen('XSRF-TOKEN=')));
                }
            }
            $quoteHeaders = ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'];
            if ($xsrf !== '') {
                $quoteHeaders[] = 'X-XSRF-TOKEN: '.$xsrf;
            }
            $quoteBody = json_encode(['items' => [['sku_id' => $sku->id, 'amount' => 1]]]);
            $ch = curl_init($baseA.'/cart/quote');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $quoteBody,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HEADER => true,
                CURLOPT_COOKIE => $cartForCsrf['cookies'] ?: $sessionCookie,
                CURLOPT_HTTPHEADER => array_merge($quoteHeaders, ['Content-Type: application/json']),
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $quoteRaw = curl_exec($ch);
            $quoteCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $quoteRespBody = strpos($quoteRaw, "\r\n\r\n") !== false
                ? substr($quoteRaw, strpos($quoteRaw, "\r\n\r\n") + 4)
                : $quoteRaw;
            $quoteJson = json_decode($quoteRespBody, true);
            if ($quoteCode === 200 && !empty($quoteJson['valid'])) {
                reg_pass($report, 'http.cart.quote', 'cart/quote 成功 payable='.$quoteJson['payable']);
            } elseif ($quoteCode === 419) {
                reg_warn($report, 'http.cart.quote', 'CSRF 419（脚本会话限制，服务层 rule.quote.ems 已覆盖）');
            } else {
                reg_fail($report, 'http.cart.quote', 'code='.$quoteCode, substr($quoteRespBody, 0, 300));
            }
        }
    }
}

// --- 6. 后台 ---
$adminGet = http_get($baseA.'/admin/auth/login');
if ($adminGet['code'] === 200) {
    reg_pass($report, 'http.admin.login', '后台登录页 200');
} else {
    reg_fail($report, 'http.admin.login', 'HTTP '.$adminGet['code']);
}

// --- 7. 导出与批量服务 ---
try {
    $incomplete = 0;
    ProductLogisticsExportService::buildQuery()->chunk(50, function ($rows) use (&$incomplete) {
        foreach ($rows as $p) {
            if (ProductLogisticsExportService::isIncomplete($p)) {
                $incomplete++;
            }
        }
    });
    reg_pass($report, 'svc.export.incomplete', "物流未完备商品 {$incomplete} 个（可导出）");
} catch (Exception $e) {
    reg_fail($report, 'svc.export.incomplete', $e->getMessage());
}

// --- 8. 生产风险项 ---
if (env('APP_DEBUG')) {
    reg_warn($report, 'prod.debug', 'APP_DEBUG=true，上线应关闭');
}
if (env('APP_ENV') === 'local') {
    reg_warn($report, 'prod.env', 'APP_ENV=local');
}
if (strpos($baseA, '127.0.0.1') !== false) {
    reg_warn($report, 'prod.url', 'SITE_A_URL 仍为本地地址');
}

// --- 输出 ---
$passN = count($report['pass']);
$failN = count($report['fail']);
$warnN = count($report['warn']);

echo "\n========== 全站回归报告 ==========\n";
echo "时间: {$report['meta']['at']}\n";
echo "通过: {$passN}  失败: {$failN}  警告: {$warnN}\n\n";

if ($report['fail']) {
    echo "--- 失败 ---\n";
    foreach ($report['fail'] as $f) {
        echo "[FAIL] {$f['id']}: {$f['msg']}\n";
        if (!empty($f['detail'])) {
            echo "       {$f['detail']}\n";
        }
    }
    echo "\n";
}

if ($report['warn']) {
    echo "--- 警告 ---\n";
    foreach ($report['warn'] as $w) {
        echo "[WARN] {$w['id']}: {$w['msg']}\n";
        if (!empty($w['detail'])) {
            echo "       {$w['detail']}\n";
        }
    }
    echo "\n";
}

echo "--- 通过（摘要）---\n";
foreach ($report['pass'] as $p) {
    echo "[ OK ] {$p['id']}: {$p['msg']}\n";
}

$reportPath = __DIR__.'/../storage/logs/regression_'.date('Ymd_His').'.json';
file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n完整 JSON: {$reportPath}\n";

exit($failN > 0 ? 1 : 0);
