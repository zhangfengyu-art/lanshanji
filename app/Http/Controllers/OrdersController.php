<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendReviewRequest;
use App\Exceptions\InvalidRequestException;
use App\Http\Requests\OrderRequest;
use App\Models\UserAddress;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Services\OrderService;
use App\Events\OrderReviewd;
use App\Http\Requests\ApplyRefundRequest;
use App\Http\Requests\UpdateOrderAddressRequest;
use App\Services\OrderFulfillmentService;
use App\Services\OrderRefundService;
use App\Exceptions\CouponCodeUnavailableException;
use App\Models\CouponCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrdersController extends Controller
{
    /** @var OrderFulfillmentService */
    protected $fulfillment;

    /** @var OrderRefundService */
    protected $refunds;

    public function __construct(OrderFulfillmentService $fulfillment, OrderRefundService $refunds)
    {
        $this->fulfillment = $fulfillment;
        $this->refunds = $refunds;
    }

    public function index(Request $request)
    {
        $orders = Order::query()
            ->with(['items.product', 'items.productSku'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate();

        return view('orders.index', ['orders' => $orders]);
    }

    public function show(Order $order, Request $request)
    {
        $this->authorize('own', $order);

        if (!$order->paid_at && !$order->closed && $order->isAllocationExpired()) {
            $order->closeAsAllocationVoided();
            $order->refresh();
        }
        
        return view('orders.show', ['order' => $order->load(['items.productSku', 'items.product'])]);
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

    public function store(OrderRequest $request, OrderService $orderService)
    {
        \Log::info('OrdersController::store called', [
            'user_id' => $request->user()->id,
            'items' => $request->input('items'),
            'address_id' => $request->input('address_id'),
        ]);
        
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
            $coupon,
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
        $this->authorize('own', $order);

        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可退款');
        }

        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            throw new InvalidRequestException('该订单已经申请过退款，请勿重复申请');
        }

        if ($this->refunds->shouldUseRefundFeedback($order)) {
            throw new InvalidRequestException('订单已开始处理，不可自助退款，请通过客户反馈联系本站。');
        }

        if ($this->refunds->canSelfInstantRefund($order)) {
            $order = $this->refunds->executeInstantRefund($order, $request->input('reason'));

            $message = $order->refund_status === Order::REFUND_STATUS_SUCCESS
                ? '已提交全额退款，款项将原路退回。'
                : '已提交全额退款，请稍候查看订单退款状态。';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'refund_status' => $order->refund_status,
                ]);
            }

            return redirect()
                ->route('orders.show', $order)
                ->with('success', $message);
        }

        $extra = $order->extra ?: [];
        $extra['refund_reason'] = $request->input('reason');
        $order->update([
            'refund_status' => Order::REFUND_STATUS_APPLIED,
            'extra' => $extra,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => trans('frontend.order.refund_requested_success'),
            ]);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', trans('frontend.order.refund_requested_success'));
    }

    public function editAddress(Order $order)
    {
        $this->authorize('own', $order);

        if (!$order->canSelfChangeAddress()) {
            throw new InvalidRequestException('当前订单状态不可自助改址，请通过客户反馈联系本站。');
        }

        $addr = $order->address ?: [];
        $detail = (string) data_get($addr, 'address', '');
        if (empty($addr['province']) && $detail !== '') {
            // 旧订单仅保存了合并后的地址字符串，需重新选择省市区
            $detail = (string) data_get($addr, 'full_address', $detail);
        }

        return view('orders.change_address', [
            'order' => $order,
            'addressForm' => [
                'province' => (string) data_get($addr, 'province', ''),
                'city' => (string) data_get($addr, 'city', ''),
                'district' => (string) data_get($addr, 'district', ''),
                'address' => $detail,
                'zip' => (int) data_get($addr, 'zip', 0),
                'contact_name' => (string) data_get($addr, 'contact_name', ''),
                'contact_phone' => (string) data_get($addr, 'contact_phone', ''),
            ],
            'legacyAddressOnly' => empty($addr['province']) && $detail !== '',
            'remainingChanges' => $this->fulfillment->remainingAddressChanges($order),
        ]);
    }

    public function updateAddress(Order $order, UpdateOrderAddressRequest $request)
    {
        $this->authorize('own', $order);

        $order = $this->fulfillment->updateAddress($order, $request->only([
            'province',
            'city',
            'district',
            'address',
            'contact_name',
            'contact_phone',
            'zip',
        ]));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => '收件信息已更新',
                'redirect' => route('orders.show', $order),
            ]);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', '收件信息已更新');
    }
}
