<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendReviewRequest;
use App\Exceptions\InvalidRequestException;
use App\Http\Requests\OrderRequest;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProcurementOrder;
use App\Models\ProductSku;
use Illuminate\Http\Request;
use App\Services\OrderService;
use App\Events\OrderReviewd;
use App\Http\Requests\ApplyRefundRequest;
use App\Http\Requests\UpdateOrderInfoRequest;
use App\Exceptions\CouponCodeUnavailableException;
use App\Models\CouponCode;
use App\Services\CartService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrdersController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::query()
            ->with(['items.product', 'items.productSku'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate();

        $procurementOrders = collect();
        if (is_site_mode_b()) {
            $procurementOrders = ProcurementOrder::query()
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        }

        return view('orders.index', [
            'orders' => $orders,
            'procurementOrders' => $procurementOrders,
        ]);
    }

    public function show(Order $order, Request $request)
    {
        $this->authorize('own', $order);

        if (!$order->paid_at && !$order->closed && $order->isAllocationExpired()) {
            $order->closeAsAllocationVoided();
            $order->refresh();
        }

        $views = is_site_mode_b()
            ? ['b_mode.orders.show', 'orders.show']
            : ['orders.show'];

        return view()->first($views, ['order' => $order->load(['items.productSku', 'items.product'])]);
    }

    public function showFulfillmentPhoto($orderNo, Request $request)
    {
        $order = Order::query()
            ->where('no', (string) $orderNo)
            ->where('user_id', optional($request->user())->id)
            ->first();

        if (!$order) {
            abort(403, '无权访问该订单图片');
        }

        $photoPath = trim((string) $order->fulfillment_photo);
        if ($photoPath === '') {
            abort(404, '发货图片尚未上传');
        }

        $disk = Storage::disk('private');
        if (!$disk->exists($photoPath)) {
            abort(404, '发货图片不存在');
        }

        $extension = strtolower((string) pathinfo($photoPath, PATHINFO_EXTENSION));
        $safeExt = $extension !== '' ? $extension : 'jpg';
        $downloadName = 'fulfillment-' . Str::random(24) . '.' . $safeExt;
        $mimeType = (string) ($disk->mimeType($photoPath) ?: 'application/octet-stream');

        return $disk->response($photoPath, $downloadName, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateInfo(UpdateOrderInfoRequest $request, Order $order)
    {
        $this->authorize('own', $order);

        \DB::transaction(function () use ($request, $order) {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);

            if (!$lockedOrder || !$lockedOrder->canChangeInfo()) {
                throw new InvalidRequestException('当前订单状态不可变更信息');
            }

            $address = [
                'address' => trim((string) $request->input('address')),
                'zip' => trim((string) $request->input('zip')),
                'contact_name' => trim((string) $request->input('contact_name')),
                'contact_phone' => trim((string) $request->input('contact_phone')),
            ];
            $remark = trim((string) $request->input('remark', ''));
            $remark = $remark === '' ? null : $remark;

            $extra = $lockedOrder->extra ?: [];
            $history = data_get($extra, 'change_info_history', []);
            $history[] = [
                'changed_at' => now()->toDateTimeString(),
                'changed_by' => $request->user()->id,
                'before' => [
                    'address' => $lockedOrder->address,
                    'remark' => $lockedOrder->remark,
                ],
                'after' => [
                    'address' => $address,
                    'remark' => $remark,
                ],
            ];

            $extra['change_info_history'] = array_slice($history, -10);
            $extra['change_info_updated_at'] = now()->toDateTimeString();

            $lockedOrder->update([
                'address' => $address,
                'remark' => $remark,
                'extra' => $extra,
            ]);
        });

        return ['msg' => '订单信息已更新'];
    }

    public function swapItem(\App\Http\Requests\SwapOrderItemRequest $request, Order $order, OrderItem $orderItem)
    {
        $this->authorize('own', $order);

        if (! $order->canSwapItem()) {
            throw new InvalidRequestException('当前订单状态不可调换商品');
        }

        if ((int) $orderItem->order_id !== (int) $order->id) {
            throw new InvalidRequestException('订单商品不存在');
        }

        $skuCode = trim((string) $request->input('sku_code'));
        if ($skuCode === '') {
            throw new InvalidRequestException('请输入新商品货号');
        }

        \DB::transaction(function () use ($request, $order, $orderItem, $skuCode) {
            $lockedOrder = Order::query()->with(['items.productSku.product'])->lockForUpdate()->find($order->id);
            if (!$lockedOrder || ! $lockedOrder->canSwapItem()) {
                throw new InvalidRequestException('当前订单状态不可调换商品');
            }

            $lockedItem = $lockedOrder->items->firstWhere('id', $orderItem->id);
            if (!$lockedItem) {
                throw new InvalidRequestException('订单商品不存在');
            }

            $newSku = ProductSku::query()->with('product')->where('title', $skuCode)->first();
            if (!$newSku) {
                throw new InvalidRequestException('未找到对应货号的商品，请检查后重试');
            }

            if (!$newSku->product || !$newSku->product->on_sale) {
                throw new InvalidRequestException('该商品未上架，无法调换');
            }

            if ((int) $newSku->id === (int) $lockedItem->product_sku_id) {
                throw new InvalidRequestException('不能调换为当前已选商品');
            }

            if (sprintf('%.2f', (float) $newSku->price) !== sprintf('%.2f', (float) $lockedItem->price)) {
                throw new InvalidRequestException('仅支持同价商品调换，请选择价格完全一致的商品');
            }

            if ((int) $newSku->stock < (int) $lockedItem->amount) {
                throw new InvalidRequestException('新商品库存不足，无法调换');
            }

            $oldSku = ProductSku::query()->lockForUpdate()->find($lockedItem->product_sku_id);
            if (!$oldSku) {
                throw new InvalidRequestException('原商品信息不存在，无法调换');
            }

            if (!$newSku->decreaseStock((int) $lockedItem->amount)) {
                throw new InvalidRequestException('新商品库存不足，无法调换');
            }
            $oldSku->addStock((int) $lockedItem->amount);

            $beforeAddress = [
                'product_id' => $lockedItem->product_id,
                'product_sku_id' => $lockedItem->product_sku_id,
                'product_title' => optional($lockedItem->product)->title,
                'sku_title' => optional($lockedItem->productSku)->title,
                'price' => (float) $lockedItem->price,
                'amount' => (int) $lockedItem->amount,
            ];

            $afterAddress = [
                'product_id' => $newSku->product_id,
                'product_sku_id' => $newSku->id,
                'product_title' => optional($newSku->product)->title,
                'sku_title' => $newSku->title,
                'price' => (float) $lockedItem->price,
                'amount' => (int) $lockedItem->amount,
            ];

            $extra = $lockedOrder->extra ?: [];
            $history = data_get($extra, 'swap_item_history', []);
            $history[] = [
                'changed_at' => now()->toDateTimeString(),
                'changed_by' => $request->user()->id,
                'order_item_id' => $lockedItem->id,
                'before' => $beforeAddress,
                'after' => $afterAddress,
            ];

            $extra['swap_item_history'] = array_slice($history, -10);
            $extra['swap_item_updated_at'] = now()->toDateTimeString();

            $lockedItem->update([
                'product_id' => $newSku->product_id,
                'product_sku_id' => $newSku->id,
                'price' => $lockedItem->price,
            ]);

            $lockedOrder->update(['extra' => $extra]);
        });

        return ['msg' => '商品已调换'];
    }

    public function store(OrderRequest $request, OrderService $orderService)
    {
        app(CartService::class)->validateLogisticsLimits(null, true);

        $user    = $request->user();
        $address = UserAddress::find($request->input('address_id'));
        $coupon  = null;

        if (!$address) {
            throw new InvalidRequestException('收货地址不存在，请刷新后重试');
        }
        
        // 如果用户提交了优惠码
        if ($code = $request->input('coupon_code')) {
            $coupon = CouponCode::where('code', $code)->first();
            if (!$coupon) {
                throw new CouponCodeUnavailableException('优惠券不存在');
            }
        }

        $order = $orderService->store(
            $user,
            $address,
            $request->input('remark'),
            $request->input('items'),
            $coupon
        );

        return ['id' => $order->id];
    }

    public function received(Order $order, Request $request)
    {
        // 校验权限
        $this->authorize('own', $order);

        // 判断订单的发货状态是否为已发货
        if ($order->ship_status !== Order::SHIP_STATUS_DELIVERED) {
            throw new InvalidRequestException('发货状态不正确');
        }

        // 更新发货状态为已收到
        $order->update(['ship_status' => Order::SHIP_STATUS_RECEIVED]);

        return $order;
    }

    public function review(Order $order)
    {
        // 校验权限
        $this->authorize('own', $order);
        // 判断是否已经支付
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可评价');
        }
        // 使用 load 方法加载关联数据，避免 N + 1 性能问题
        return view('orders.review', ['order' => $order->load(['items.productSku', 'items.product'])]);
    }
    
    public function sendReview(Order $order, SendReviewRequest $request)
    {
        // 校验权限
        $this->authorize('own', $order);
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可评价');
        }
        // 判断是否已经评价
        if ($order->reviewed) {
            throw new InvalidRequestException('该订单已评价，不可重复提交');
        }
        $reviews = $request->input('reviews');
        // 开启事务
        \DB::transaction(function () use ($reviews, $order) {
            // 遍历用户提交的数据
            foreach ($reviews as $review) {
                $orderItem = $order->items()->find($review['id']);
                // 保存评分和评价
                $orderItem->update([
                    'rating'      => $review['rating'],
                    'review'      => $review['review'],
                    'reviewed_at' => Carbon::now(),
                ]);
            }
            // 将订单标记为已评价
            $order->update(['reviewed' => true]);
            event(new OrderReviewd($order));
        });    

        return redirect()->back();
    }

    public function applyRefund(Order $order, ApplyRefundRequest $request)
    {
        // 校验订单是否属于当前用户
        $this->authorize('own', $order);
        // 判断订单是否已付款
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可退款');
        }
        if ($order->ship_status !== Order::SHIP_STATUS_PENDING) {
            throw new InvalidRequestException('订单已进入履行流程，请先咨询客服处理');
        }
        if (!$order->isPendingAcceptance()) {
            throw new InvalidRequestException('订单已受理，请先咨询客服处理');
        }
        // 判断订单退款状态是否正确
        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            throw new InvalidRequestException('该订单已经申请过退款，请勿重复申请');
        }
        // 将用户输入的退款理由放到订单的 extra 字段中
        $extra                  = $order->extra ?: [];
        $extra['refund_reason'] = $request->input('reason');
        // 将订单退款状态改为已申请退款
        $order->update([
            'refund_status' => Order::REFUND_STATUS_APPLIED,
            'extra'         => $extra,
        ]);

        return $order;
    }

}
