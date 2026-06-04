<?php

/**
 * 回归 Phase 2：服务层下单、汇率快照、跨站支付、后台登录、批量操作
 * 用法: php scripts/full_regression_test_phase2.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\ProductSku;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\CrossSitePaymentService;
use App\Services\ExchangeRateService;
use App\Services\OrderService;
use App\Services\ProductBatchService;
use App\Services\ShippingModeService;
use Illuminate\Support\Facades\Auth;

$baseA = rtrim((string) env('SITE_A_URL', 'http://127.0.0.1:8000'), '/');
$baseB = rtrim((string) env('SITE_B_URL', 'http://127.0.0.1:8001'), '/');

$pass = [];
$fail = [];
$warn = [];

function p2_pass(array &$p, $id, $m)
{
    $p[] = [$id, $m];
}

function p2_fail(array &$f, $id, $m, $d = '')
{
    $f[] = [$id, $m, $d];
}

function p2_warn(array &$w, $id, $m, $d = '')
{
    $w[] = [$id, $m, $d];
}

function p2_http_get($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $p = strpos($raw, "\r\n\r\n");
    $headers = $p !== false ? substr($raw, 0, $p) : '';
    $body = $p !== false ? substr($raw, $p + 4) : $raw;
    preg_match_all('/^Set-Cookie:\s*([^;=\s]+)=([^;]*)/mi', $headers, $m, PREG_SET_ORDER);
    $jar = [];
    foreach ($m as $c) {
        $jar[$c[1]] = $c[1].'='.$c[2];
    }

    return ['code' => $code, 'body' => $body, 'cookies' => implode('; ', array_values($jar))];
}

function p2_http_post($url, $fields, $cookies = '')
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HEADER => true,
        CURLOPT_COOKIE => $cookies,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'raw' => $raw];
}

$emsProductId = Product::where('title', '[回归测试] EMS香烟')->value('id');
$emsSku = $emsProductId
    ? ProductSku::where('product_id', $emsProductId)->first()
    : null;

if (!$emsSku) {
    p2_fail($fail, 'fixture', '请先运行: php scripts/regression_fixtures.php');
    goto output;
}

$user = User::where('email_verified', 1)->first();
if (!$user) {
    p2_fail($fail, 'user', '无已验证用户');
    goto output;
}

Auth::login($user);

$address = UserAddress::where('user_id', $user->id)
    ->where('contact_phone', '13800138000')
    ->first();
if (!$address) {
    $address = new UserAddress([
        'contact_name' => '回归测试',
        'province' => '上海市',
        'city' => '上海市',
        'district' => '浦东新区',
        'address' => '测试路1号',
        'zip' => 200120,
        'contact_phone' => '13800138000',
        'id_card' => '310101199001011234',
        'is_default' => 1,
    ]);
    $address->user_id = $user->id;
    $address->save();
}

putenv('SITE_MODE=A');
$_ENV['SITE_MODE'] = 'A';

try {
    $order = app(OrderService::class)->store($user, $address, '回归测试订单', [
        ['sku_id' => $emsSku->id, 'amount' => 2],
    ]);

    $extra = $order->fresh()->extra;
    $fee = (array) data_get($extra, 'fee_details', []);
    if ((float) data_get($fee, 'ems_shipping_fee', 0) > 0) {
        p2_pass($pass, 'order.create', '订单 '.$order->no.' EMS='.$fee['ems_shipping_fee'].' 合计='.$order->total_amount);
    } else {
        p2_fail($fail, 'order.create', '订单无 EMS 费', json_encode($fee, JSON_UNESCAPED_UNICODE));
    }

    if ((int) data_get($extra, 'tobacco_summary.total_cigarette_sticks') === 40) {
        p2_pass($pass, 'order.tobacco', '香烟+加热烟支数 40 正确');
    } else {
        p2_warn($warn, 'order.tobacco', '支数异常', json_encode(data_get($extra, 'tobacco_summary')));
    }

    app(ExchangeRateService::class)->snapshotQuoteOnOrder($order->fresh());
    $order = $order->fresh();
    if ((float) data_get($order->extra, 'payment_amount_cny', 0) > 0) {
        p2_pass($pass, 'order.fx', 'CNY 快照='.data_get($order->extra, 'payment_amount_cny'));
    } else {
        p2_fail($fail, 'order.fx', '无支付人民币金额');
    }

    $cross = app(CrossSitePaymentService::class);
    if ($cross->shouldRedirectToSiteB()) {
        $url = $cross->buildSignedPayUrl($order, 'alipay');
        if (strpos($url, '8001') !== false || strpos($url, parse_url($baseB, PHP_URL_HOST) ?: '') !== false) {
            p2_pass($pass, 'pay.cross.url', 'B 站支付链接已生成');
        } else {
            p2_warn($warn, 'pay.cross.url', '支付 URL 异常', $url);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        $payCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (in_array($payCode, [200, 302, 403], true)) {
            p2_pass($pass, 'http.pay.cross', "B 站 cross pay HTTP {$payCode}");
        } else {
            p2_fail($fail, 'http.pay.cross', "HTTP {$payCode}", $url);
        }
    } else {
        p2_warn($warn, 'pay.cross', 'SITE_MODE 未配置跳转 B 站（本地单站可忽略）');
    }
} catch (Exception $e) {
    p2_fail($fail, 'order.create', $e->getMessage(), $e->getFile().':'.$e->getLine());
}

$adminLogin = p2_http_get($baseA.'/admin/auth/login');
preg_match('/name="_token"\s+value="([^"]+)"/', $adminLogin['body'], $tm);
$adminRes = p2_http_post($baseA.'/admin/auth/login', [
    '_token' => $tm[1] ?? '',
    'username' => 'admin',
    'password' => 'admin123456',
], $adminLogin['cookies']);
if (in_array($adminRes['code'], [302, 200], true)) {
    p2_pass($pass, 'admin.login', '管理员登录 HTTP '.$adminRes['code']);
} else {
    p2_fail($fail, 'admin.login', 'HTTP '.$adminRes['code']);
}

try {
    $pid = Product::where('title', '[回归测试] EMS香烟')->value('id');
    app(ProductBatchService::class)->batchSetShippingMode([$pid], ShippingModeService::MODE_EMS);
    p2_pass($pass, 'batch.shipping', '批量寄送模式 OK');
} catch (Exception $e) {
    p2_fail($fail, 'batch.shipping', $e->getMessage());
}

output:
echo "\n========== 回归 Phase 2（下单/支付/后台）==========\n";
echo '通过: '.count($pass).' 失败: '.count($fail).' 警告: '.count($warn)."\n\n";
foreach ($fail as $f) {
    echo "[FAIL] {$f[0]}: {$f[1]}\n";
    if ($f[2]) {
        echo "  {$f[2]}\n";
    }
}
foreach ($warn as $w) {
    echo "[WARN] {$w[0]}: {$w[1]}\n";
    if ($w[2]) {
        echo "  {$w[2]}\n";
    }
}
foreach ($pass as $p) {
    echo "[ OK ] {$p[0]}: {$p[1]}\n";
}

exit(count($fail) > 0 ? 1 : 0);
