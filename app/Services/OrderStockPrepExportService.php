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
    const TEXT_COLUMN_INDEXES = [0];

    /** @var int[] */
    const IMAGE_COLUMN_INDEXES = [5];

    /** @var int[] */
    const CHECKBOX_COLUMN_INDEXES = [6];

    public static function scopeOptions()
    {
        return [
            'history_total' => '历史总发货代购汇总',
            'pending' => '待处理',
            'paid_today' => '今日新支付（待发货）',
            'paid_week' => '近7日新支付（待发货）',
            'refund_applied' => '退款申请中（慎选）',
        ];
    }

    public static function exportStatusCodes()
    {
        return [
            'history_total' => '总采购汇总',
            'pending' => '待处理',
            'paid_today' => '今日新支付待发货',
            'paid_week' => '近7日新支付待发货',
            'refund_applied' => '退款中',
        ];
    }

    public static function headers()
    {
        return [
            '商品名称',
            '类型',
            '采购(包)',
            '单价(日元)',
            '合计(日元)',
            '商品图',
            '确认',
        ];
    }

    public static function filename($scope)
    {
        return AdminExportFilenameBuilder::buildPdfFilename(
            '备货',
            self::exportStatusCodes()[$scope] ?? '备货',
            AdminExportFilenameBuilder::timeCodeForScope($scope)
        );
    }

    public static function pdfFilename($scope)
    {
        return self::filename($scope);
    }

    public static function pdfExportOptions($scope, $scopeLabel = null)
    {
        $scopeLabel = $scopeLabel ?? (self::scopeOptions()[$scope] ?? '');

        return [
            'text_columns' => self::TEXT_COLUMN_INDEXES,
            'image_columns' => self::IMAGE_COLUMN_INDEXES,
            'checkbox_columns' => self::CHECKBOX_COLUMN_INDEXES,
            'qty_columns' => [2],
            'numeric_columns' => [2, 3, 4],
            'center_columns' => [1, 2, 3, 4, 6],
            'badge_columns' => [1],
            'large_text_columns' => [0],
            'large_text_font_scale' => 1.25,
            'column_widths_mm' => ['72mm', '22mm', '18mm', '22mm', '26mm', '44mm', '14mm'],
            'title_note' => '烟草备货表 · '.$scopeLabel.' · '.date('Y-m-d H:i'),
            'pdf_title' => '烟草备货表',
            'style_mode' => 'pdf',
            'image_max_size' => 480,
            'image_display_width' => 108,
            'image_display_height' => 150,
            'image_jpeg_quality' => 96,
            'table_font_size' => 16,
            'checkbox_cell_size' => 36,
            'footer_note' => self::pdfFooterNote($scope),
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
            'column_widths' => ['28%', '10%', '9%', '11%', '12%', '25%', '5%'],
            'qty_columns' => [2],
            'large_text_columns' => [0],
            'large_text_font_scale' => 1.25,
            'enable_print_css' => true,
            'footer_note' => '请解压本 ZIP 后，用 Excel 或 WPS 打开「备货表.html」，打印前可在浏览器中预览。'
                .'本表汇总香烟、加热烟、手卷烟丝按包采购数量，不含用户地址与身份信息；已退款成功、已发货（S4）订单不计入。'
                .'「确认」列留空供现场打勾。',
        ];
    }

    public static function pdfFooterNote($scope = null)
    {
        if ($scope === 'history_total') {
            return '本表汇总历史香烟、加热烟、手卷烟丝按包采购数量，含待处理、已打包与已发货订单；不含已退款成功订单。「确认」列留空供现场打勾。';
        }

        return '本表汇总香烟、加热烟、手卷烟丝按包采购数量，不含用户地址与身份信息；已退款成功、已发货订单不计入。「确认」列留空供现场打勾。';
    }

    public static function buildQuery($scope)
    {
        $query = Order::query()
            ->with(['items.product'])
            ->whereNotNull('paid_at')
            ->where('closed', false);

        $today = Carbon::today();

        switch ($scope) {
            case 'history_total':
                break;
            case 'pending':
            case 's1':
            case 's2':
            case 's1_s2':
            case 's3':
            case 'pending_fulfillment':
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
                            'unit_price' => round((float) $product->price, 2),
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
        $totalAmount = 0.0;

        foreach ($aggregates as $row) {
            $index++;
            $packs = (int) $row['qty'];
            $unitPrice = round((float) $row['unit_price'], 2);
            $lineTotal = round($unitPrice * $packs, 2);
            $totalPacks += $packs;
            $totalAmount += $lineTotal;

            $emitRow([
                $row['title'],
                $row['tobacco_type_label'],
                $packs,
                self::formatJpyAmount($unitPrice),
                self::formatJpyAmount($lineTotal),
                OrderAdminExportService::imageLocalPathForProduct($row['product']),
                '',
            ]);
        }

        if ($index === 0) {
            $emitRow(['（当前范围无香烟/加热烟/烟丝采购项）', '—', 0, '—', '—', '', '']);

            return;
        }

        $emitRow([
            '合计',
            $index.' 种商品',
            $totalPacks,
            '—',
            self::formatJpyAmount($totalAmount),
            '',
            '',
        ]);
    }

    protected static function formatJpyAmount($amount)
    {
        return number_format((float) $amount, 2, '.', '');
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
            case 'history_total':
                return in_array($stage, [
                    OrderFulfillmentService::STAGE_S1,
                    OrderFulfillmentService::STAGE_S2,
                    OrderFulfillmentService::STAGE_S3,
                    OrderFulfillmentService::STAGE_S4,
                ], true);
            case 'pending':
            case 's1_s2':
            case 's1':
            case 's2':
                return in_array($stage, [
                    OrderFulfillmentService::STAGE_S1,
                    OrderFulfillmentService::STAGE_S2,
                ], true);
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
