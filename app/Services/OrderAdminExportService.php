<?php

namespace App\Services;

use App\Models\Order;
use App\Models\UserAddress;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OrderAdminExportService
{
    /** @var int[] */
    const TEXT_COLUMN_INDEXES = [0, 4, 6, 21];

    /** @var int[] */
    const IMAGE_COLUMN_INDEXES = [11];

    public static function scopeOptions()
    {
        return [
            'all' => '全部已支付订单',
            'today' => '今日支付订单',
            'week' => '近7日支付订单',
            'pending_ship' => '待发货订单',
            'shipped' => '已发货未签收',
            'refund_applied' => '退款申请中',
            's1_pending' => '待处理（S1，未开始处理）',
        ];
    }

    public static function buildQuery($scope)
    {
        $query = Order::query()
            ->with(['user', 'items.product', 'items.productSku'])
            ->whereNotNull('paid_at')
            ->orderBy('paid_at', 'desc');

        $today = Carbon::today();

        switch ($scope) {
            case 'today':
                $query->whereDate('paid_at', $today);
                break;
            case 'week':
                $query->where('paid_at', '>=', $today->copy()->subDays(6)->startOfDay());
                break;
            case 'pending_ship':
                $query->where('ship_status', Order::SHIP_STATUS_PENDING);
                break;
            case 'shipped':
                $query->where('ship_status', Order::SHIP_STATUS_DELIVERED);
                break;
            case 'refund_applied':
                $query->where('refund_status', Order::REFUND_STATUS_APPLIED);
                break;
            case 's1_pending':
                $query->where('ship_status', Order::SHIP_STATUS_PENDING)
                    ->where('refund_status', Order::REFUND_STATUS_PENDING);
                break;
            case 'all':
            default:
                break;
        }

        return $query;
    }

    public static function headers()
    {
        return [
            '订单流水号',
            '支付时间',
            '买家昵称',
            '收货人',
            '联系电话',
            '收货地址',
            '身份证号',
            '商品名称',
            '规格',
            '数量',
            '单价(日元)',
            '商品图片',
            '寄送模式',
            'EMS计费重量(g)',
            '香烟支数',
            '烟丝重量(g)',
            '订单备注',
            '发货状态',
            '退款状态',
            '支付方式',
            '订单金额(日元)',
            '一键粘贴地址',
        ];
    }

    /**
     * 每个订单商品一行，便于代购对照图片与地址。
     *
     * @return array[]
     */
    public static function rowsForOrder(Order $order)
    {
        $address = (array) $order->address;
        $fullAddress = self::formatFullAddress($address, $order);
        $pasteAddress = self::formatAddressForPaste($address);
        $idCard = self::resolveOrderIdCard($order);
        $pasteLine = self::buildPasteAddressLine($address, $pasteAddress, $idCard);

        $fee = (array) data_get($order->extra, 'fee_details', []);
        $tobacco = (array) data_get($order->extra, 'tobacco_summary', []);
        $mode = data_get($fee, 'shipping_mode', data_get($order->extra, 'shipping_mode', ''));
        $head = self::sharedOrderCells($order, $fullAddress, $idCard);
        $tail = self::tailOrderCells($order, $fee, $tobacco, $mode, $pasteLine);

        $items = $order->items;
        if ($items->isEmpty()) {
            return [array_merge($head, ['—', '—', '', '', ''], $tail)];
        }

        $rows = [];
        foreach ($items as $item) {
            $product = $item->product;
            $title = $product ? $product->title : ($item->product_id ? '商品#'.$item->product_id : '—');
            $skuTitle = optional($item->productSku)->title;
            $imageUrl = $product ? self::imageEmbedForProduct($product) : '';

            $rows[] = array_merge($head, [
                $title,
                $skuTitle ?: '—',
                (int) $item->amount,
                $item->price,
                $imageUrl,
            ], $tail);
        }

        return $rows;
    }

    protected static function sharedOrderCells(Order $order, $fullAddress, $idCard)
    {
        return [
            $order->no,
            optional($order->paid_at)->format('Y-m-d H:i:s'),
            optional($order->user)->name ?: '—',
            data_get($order->address, 'contact_name', ''),
            data_get($order->address, 'contact_phone', ''),
            $fullAddress,
            $idCard,
        ];
    }

    protected static function tailOrderCells(Order $order, $fee, $tobacco, $mode, $pasteLine)
    {
        return [
            ShippingModeService::options()[$mode] ?? $mode,
            data_get($fee, 'ems_weight_grams', ''),
            data_get($tobacco, 'total_cigarette_sticks', ''),
            data_get($tobacco, 'total_rolling_tobacco_grams', ''),
            $order->remark ?: '—',
            self::shipStatusLabel($order),
            Order::$refundStatusMap[$order->refund_status] ?? $order->refund_status,
            self::paymentMethodLabel($order->payment_method),
            $order->total_amount,
            $pasteLine,
        ];
    }

    public static function resolveOrderIdCard(Order $order)
    {
        $fromOrder = trim((string) data_get($order->address, 'id_card', ''));
        if ($fromOrder !== '') {
            return $fromOrder;
        }

        if (!$order->user_id) {
            return '';
        }

        $phone = trim((string) data_get($order->address, 'contact_phone', ''));
        $name = trim((string) data_get($order->address, 'contact_name', ''));

        $query = UserAddress::query()->where('user_id', $order->user_id);

        if ($phone !== '') {
            $match = (clone $query)
                ->where('contact_phone', $phone)
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->first();
            if ($match && trim((string) $match->id_card) !== '') {
                return trim((string) $match->id_card);
            }
        }

        if ($name !== '') {
            $match = (clone $query)
                ->where('contact_name', $name)
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->first();
            if ($match && trim((string) $match->id_card) !== '') {
                return trim((string) $match->id_card);
            }
        }

        $default = (clone $query)
            ->where('is_default', 1)
            ->orderByDesc('last_used_at')
            ->first();

        return $default ? trim((string) $default->id_card) : '';
    }

    public static function resolveOrderZip(Order $order)
    {
        $fromOrder = self::normalizeZip(data_get($order->address, 'zip'));
        if ($fromOrder !== '') {
            return $fromOrder;
        }

        if (!$order->user_id) {
            return '';
        }

        $phone = trim((string) data_get($order->address, 'contact_phone', ''));
        $name = trim((string) data_get($order->address, 'contact_name', ''));

        $query = UserAddress::query()->where('user_id', $order->user_id);

        if ($phone !== '') {
            $match = (clone $query)
                ->where('contact_phone', $phone)
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->first();
            if ($match) {
                $zip = self::normalizeZip($match->zip);
                if ($zip !== '') {
                    return $zip;
                }
            }
        }

        if ($name !== '') {
            $match = (clone $query)
                ->where('contact_name', $name)
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->first();
            if ($match) {
                $zip = self::normalizeZip($match->zip);
                if ($zip !== '') {
                    return $zip;
                }
            }
        }

        $default = (clone $query)
            ->where('is_default', 1)
            ->orderByDesc('last_used_at')
            ->first();

        return $default ? self::normalizeZip($default->zip) : '';
    }

    public static function normalizeZip($zip)
    {
        $zip = trim((string) $zip);
        if ($zip === '' || $zip === '0' || (int) $zip === 0) {
            return '';
        }

        return $zip;
    }

    public static function formatStreetAddress(array $address)
    {
        $province = trim((string) data_get($address, 'province', ''));
        $city = trim((string) data_get($address, 'city', ''));
        $district = trim((string) data_get($address, 'district', ''));
        $detail = trim((string) data_get($address, 'address', ''));
        $storedFull = trim((string) data_get($address, 'full_address', ''));

        if ($detail !== '' && $province !== '' && strpos($detail, $province) !== false) {
            return $detail;
        }

        $built = implode('', array_filter([$province, $city, $district, $detail], function ($part) {
            return $part !== '';
        }));

        if ($built !== '' && $storedFull !== '' && $storedFull === $built.$detail && $detail !== '') {
            return $detail;
        }

        if ($storedFull !== '' && $built !== '' && strpos($storedFull, $built) === 0 && mb_strlen($storedFull) > mb_strlen($built)) {
            if ($detail !== '' && strpos($detail, $province) !== false) {
                return $detail;
            }
        }

        if ($built !== '') {
            return $built;
        }

        return $storedFull;
    }

    public static function formatAddressForPaste(array $address)
    {
        return self::formatStreetAddress($address);
    }

    public static function formatFullAddress(array $address, Order $order = null)
    {
        $street = self::formatStreetAddress($address);
        $zip = self::normalizeZip(data_get($address, 'zip'));
        if ($zip === '' && $order instanceof Order) {
            $zip = self::resolveOrderZip($order);
        }

        if ($zip !== '' && $street !== '') {
            $street .= '（邮编 '.$zip.'）';
        }

        return $street;
    }

    public static function buildPasteAddressLine(array $address, $fullAddress, $idCard)
    {
        $parts = [
            trim((string) data_get($address, 'contact_name', '')),
            trim((string) data_get($address, 'contact_phone', '')),
            trim((string) $fullAddress),
            trim((string) $idCard),
        ];

        $parts = array_values(array_filter($parts, function ($part) {
            return $part !== '';
        }));

        return implode('，', $parts);
    }

    public static function imageEmbedForProduct($product)
    {
        if (!$product) {
            return '';
        }

        static $cache = [];
        $cacheKey = (int) $product->id;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $rawPath = trim((string) ($product->getAttributes()['image'] ?? ''));
        $cache[$cacheKey] = self::resolveImageDataUri($rawPath);

        return $cache[$cacheKey];
    }

    protected static function resolveImageDataUri($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        $localFile = self::resolveLocalImageFile($path);
        if (!$localFile || !is_readable($localFile)) {
            return '';
        }

        return self::encodeImageThumbnail($localFile, 96);
    }

    protected static function encodeImageThumbnail($file, $maxSize)
    {
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return '';
        }

        if (!function_exists('imagecreatefromstring')) {
            return 'data:'.self::mimeTypeForFile($file).';base64,'.base64_encode($raw);
        }

        $img = @imagecreatefromstring($raw);
        if (!$img) {
            return 'data:'.self::mimeTypeForFile($file).';base64,'.base64_encode($raw);
        }

        $width = imagesx($img);
        $height = imagesy($img);
        $scale = min($maxSize / max($width, 1), $maxSize / max($height, 1), 1);

        if ($scale < 1) {
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $thumb = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefill($thumb, 0, 0, $transparent);
            imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($img);
            $img = $thumb;
        }

        ob_start();
        imagejpeg($img, null, 80);
        $jpeg = ob_get_clean();
        imagedestroy($img);

        if ($jpeg === false || $jpeg === '') {
            return '';
        }

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }

    protected static function resolveLocalImageFile($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $urlPath = (string) (parse_url($path, PHP_URL_PATH) ?: '');
            if (preg_match('#/storage/(.+)$#', $urlPath, $matches)) {
                return self::firstExistingFile([
                    public_path('storage/'.$matches[1]),
                    storage_path('app/public/'.$matches[1]),
                ]);
            }

            return null;
        }

        if (Str::startsWith($path, '//')) {
            return self::resolveLocalImageFile('https:'.$path);
        }

        $relative = ltrim($path, '/');

        return self::firstExistingFile([
            storage_path('app/public/'.$relative),
            public_path('storage/'.$relative),
            public_path($relative),
        ]);
    }

    protected static function firstExistingFile(array $candidates)
    {
        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    protected static function mimeTypeForFile($file)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $file);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        return $map[$ext] ?? 'image/jpeg';
    }

    public static function absoluteImageUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        if (Str::startsWith($url, '//')) {
            return 'https:'.$url;
        }

        return url($url);
    }

    protected static function paymentMethodLabel($method)
    {
        $map = [
            'wechat' => '微信支付',
            'alipay' => '支付宝',
        ];

        return $map[$method] ?? (string) $method;
    }

    protected static function shipStatusLabel(Order $order)
    {
        if (is_site_mode_b() && $order->refund_status === Order::REFUND_STATUS_PENDING) {
            return $order->display_status;
        }

        return Order::$shipStatusMap[$order->ship_status] ?? $order->ship_status;
    }

    public static function filename($scope)
    {
        $label = self::scopeOptions()[$scope] ?? '订单';
        $safe = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9_-]+/u', '', $label);
        if ($safe === '') {
            $safe = 'orders';
        }

        return $safe.'_'.date('Ymd_His').'.xls';
    }

    public static function exportRowsWithProducer($scope, OrderFulfillmentService $fulfillment, callable $emitRow)
    {
        static::buildQuery($scope)->chunk(50, function ($orders) use ($scope, $fulfillment, $emitRow) {
            foreach ($orders as $order) {
                if ($scope === 's1_pending' && $fulfillment->resolveStage($order) !== OrderFulfillmentService::STAGE_S1) {
                    continue;
                }
                foreach (self::rowsForOrder($order) as $row) {
                    $emitRow($row);
                }
            }
        });
    }
}
