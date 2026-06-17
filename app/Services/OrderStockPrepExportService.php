<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;

/**
 * 按订单范围汇总香烟/加热烟/手卷烟丝采购数量，导出 ZIP+HTML/PDF 备货表。
 */
class OrderStockPrepExportService
{
    /** @var int[] */
    const TEXT_COLUMN_INDEXES = [1];

    /** @var int[] */
    const IMAGE_COLUMN_INDEXES = [4];

    /** @var int[] */
    const CHECKBOX_COLUMN_INDEXES = [5];

    public static function scopeOptions()
    {
        return [
            'pending_fulfillment' => '待发货采购汇总（S1+S2+S3）',
            's1' => 'S1 待处理',
            's2' => 'S2 处理中',
            's1_s2' => 'S1待处理+S2处理中',
            's3' => 'S3 备货/打包',
            'paid_today' => '今日新支付（待发货）',
            'paid_week' => '近7日新支付（待发货）',
            'refund_applied' => '退款申请中（慎选）',
        ];
    }

    public static function headers()
    {
        return [
            '序号',
            '商品名称',
            '类型',
            '采购(包)',
            '商品图',
            '确认',
        ];
    }

    public static function filename($scope)
    {
        $label = self::scopeOptions()[$scope] ?? '备货';
        $safe = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9_-]+/u', '', $label.'_备货表');
        if ($safe === '') {
            $safe = 'stock_prep';
        }

        return $safe.'_'.date('Ymd_His').'.zip';
    }

    public static function pdfFilename($scope)
    {
        return preg_replace('/\.zip$/', '.pdf', self::filename($scope));
    }

    public static function pdfExportOptions($scopeLabel)
    {
        return [
            'text_columns' => self::TEXT_COLUMN_INDEXES,
            'image_columns' => self::IMAGE_COLUMN_INDEXES,
            'checkbox_columns' => self::CHECKBOX_COLUMN_INDEXES,
            'qty_columns' => [3],
            'numeric_columns' => [0, 3],
            'center_columns' => [0, 2, 3, 5],
            'column_widths_mm' => ['10mm', '108mm', '24mm', '22mm', '48mm', '14mm'],
            'title_note' => '烟草备货表 · '.$scopeLabel.' · '.date('Y-m-d H:i'),
            'pdf_title' => '烟草备货表',
            'style_mode' => 'pdf',
            'image_max_size' => 480,
            'image_display_width' => 108,
            'image_display_height' => 150,
            'image_jpeg_quality' => 96,
            'table_font_size' => 16,
            'checkbox_cell_size' => 36,
            'footer_note' => self::pdfFooterNote(),
        ];
    }

    public static function htmlExportOptions($scopeLabel)
    {
        return [
            'text_columns' => self::TEXT_COLUMN_INDEXES,
            'image_columns' => self::IMAGE_COLUMN_INDEXES,
            'checkbox_columns' => self::CHECKBOX_COLUMN_INDEXES,
            'html_basename' => '备货表.html',
            'title_note' => '烟草备货表（香烟/加热烟/烟丝） · '.$scopeLabel.' · '.date('Y-m-d H:i'),
            'pdf_title' => '烟草备货表',
            'image_max_size' => 240,
            'image_display_size' => 160,
            'image_display_width' => 160,
            'image_display_height' => 160,
            'image_jpeg_quality' => 92,
            'table_font_size' => 16,
            'checkbox_cell_size' => 48,
            'column_widths' => ['5%', '42%', '10%', '10%', '28%', '5%'],
            'qty_columns' => [3],
            'enable_print_css' => true,
            'footer_note' => '请解压本 ZIP 后，用 Excel 或 WPS 打开「备货表.html」，打印前可在浏览器中预览。'
                .'本表汇总香烟、加热烟、手卷烟丝按包采购数量，不含用户地址与身份信息；已退款成功、已发货（S4）订单不计入。'
                .'「确认」列留空供现场打勾。',
        ];
    }

    public static function pdfFooterNote()
    {
        return '本表汇总香烟、加热烟、手卷烟丝按包采购数量，不含用户地址与身份信息；已退款成功、已发货（S4）订单不计入。「确认」列留空供现场打勾。';
    }

    public static function buildQuery($scope)
    {
        $query = Order::query()
            ->with(['items.product'])
            ->whereNotNull('paid_at')
            ->where('closed', false);

        $today = Carbon::today();

        switch ($scope) {
            case 'pending_fulfillment':
            case 's1':
            case 's2':
            case 's1_s2':
            case 's3':
                $query->where('ship_status', Order::SHIP_STATUS_PENDING);
                break;
            case 'paid_today':
                $query->whereDate('paid_at', $today)
                    ->where('ship_status', Order::SHIP_STATUS_PENDING);
                break;
            case 'paid_week':
                $query->where('paid_at', '>=', $today->copy()->subDays(6)->startOfDay())
                    ->where('ship_status', Order::SHIP_STATUS_PENDING);
                break;
            case 'refund_applied':
                $query->where('refund_status', Order::REFUND_STATUS_APPLIED);
                break;
            default:
                $query->where('ship_status', Order::SHIP_STATUS_PENDING);
                break;
        }

        return $query;
    }

    public static function exportRowsWithProducer($scope, OrderFulfillmentService $fulfillment, callable $emitRow)
    {
        $aggregates = [];

        self::buildQuery($scope)->chunk(50, function ($orders) use ($scope, $fulfillment, &$aggregates) {
            foreach ($orders as $order) {
                if (!self::orderIncludedInStockPrep($order, $scope, $fulfillment)) {
                    continue;
                }

                foreach ($order->items as $item) {
                    $product = $item->product;
                    if (!$product || !self::productIncludedInStockPrep($product)) {
                        continue;
                    }

                    $qty = (int) $item->amount;
                    if ($qty < 1) {
                        continue;
                    }

                    $productId = (int) $product->id;
                    if (!isset($aggregates[$productId])) {
                        $aggregates[$productId] = [
                            'product' => $product,
                            'title' => (string) $product->title,
                            'tobacco_type_label' => (string) $product->tobacco_type_label,
                            'qty' => 0,
                        ];
                    }

                    $aggregates[$productId]['qty'] += $qty;
                }
            }
        });

        uasort($aggregates, function ($a, $b) {
            $typeCmp = self::typeSortOrder($a['tobacco_type_label']) - self::typeSortOrder($b['tobacco_type_label']);
            if ($typeCmp !== 0) {
                return $typeCmp;
            }

            if ($a['qty'] !== $b['qty']) {
                return $b['qty'] - $a['qty'];
            }

            return strcmp($a['title'], $b['title']);
        });

        $index = 0;
        $totalPacks = 0;

        foreach ($aggregates as $row) {
            $index++;
            $packs = (int) $row['qty'];
            $totalPacks += $packs;

            $emitRow([
                $index,
                $row['title'],
                $row['tobacco_type_label'],
                $packs,
                OrderAdminExportService::imageLocalPathForProduct($row['product']),
                '',
            ]);
        }

        if ($index === 0) {
            $emitRow(['—', '（当前范围无香烟/加热烟/烟丝采购项）', '—', 0, '', '']);

            return;
        }

        $emitRow([
            '合计',
            $index.' 种商品',
            '—',
            $totalPacks,
            '',
            '',
        ]);
    }

    protected static function productIncludedInStockPrep($product)
    {
        $type = (string) $product->tobacco_type;

        return in_array($type, [
            OrderTobaccoLimitService::TYPE_CIGARETTE,
            OrderTobaccoLimitService::TYPE_HEATED_TOBACCO,
            OrderTobaccoLimitService::TYPE_ROLLING_TOBACCO,
        ], true);
    }

    protected static function typeSortOrder($label)
    {
        $map = [
            '香烟' => 1,
            '加热烟' => 2,
            '手卷烟丝' => 3,
        ];

        return $map[(string) $label] ?? 99;
    }

    protected static function orderIncludedInStockPrep(Order $order, $scope, OrderFulfillmentService $fulfillment)
    {
        if ($order->closed) {
            return false;
        }

        if ($order->refund_status === Order::REFUND_STATUS_SUCCESS) {
            return false;
        }

        $stage = $fulfillment->resolveStage($order);

        switch ($scope) {
            case 'pending_fulfillment':
                return in_array($stage, [
                    OrderFulfillmentService::STAGE_S1,
                    OrderFulfillmentService::STAGE_S2,
                    OrderFulfillmentService::STAGE_S3,
                ], true);
            case 's1':
                return $stage === OrderFulfillmentService::STAGE_S1;
            case 's2':
                return $stage === OrderFulfillmentService::STAGE_S2;
            case 's1_s2':
                return in_array($stage, [
                    OrderFulfillmentService::STAGE_S1,
                    OrderFulfillmentService::STAGE_S2,
                ], true);
            case 's3':
                return $stage === OrderFulfillmentService::STAGE_S3;
            case 'paid_today':
            case 'paid_week':
                return in_array($stage, [
                    OrderFulfillmentService::STAGE_S1,
                    OrderFulfillmentService::STAGE_S2,
                    OrderFulfillmentService::STAGE_S3,
                ], true);
            case 'refund_applied':
                return true;
            default:
                return in_array($stage, [
                    OrderFulfillmentService::STAGE_S1,
                    OrderFulfillmentService::STAGE_S2,
                    OrderFulfillmentService::STAGE_S3,
                ], true);
        }
    }
}
