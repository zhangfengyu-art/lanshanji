<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class Order extends Model
{
    const REFUND_STATUS_PENDING = 'pending';
    const REFUND_STATUS_APPLIED = 'applied';
    const REFUND_STATUS_PROCESSING = 'processing';
    const REFUND_STATUS_SUCCESS = 'success';
    const REFUND_STATUS_FAILED = 'failed';

    const SHIP_STATUS_PENDING = 'pending';
    const SHIP_STATUS_DELIVERED = 'delivered';
    const SHIP_STATUS_RECEIVED = 'received';

    const ACCEPTANCE_STATUS_PENDING = 'pending';
    const ACCEPTANCE_STATUS_ACCEPTED = 'accepted';

    public static $refundStatusMap = [
        self::REFUND_STATUS_PENDING    => '未退款',
        self::REFUND_STATUS_APPLIED    => '已申请退款',
        self::REFUND_STATUS_PROCESSING => '退款中',
        self::REFUND_STATUS_SUCCESS    => '退款成功',
        self::REFUND_STATUS_FAILED     => '退款失败',
    ];

    public static $shipStatusMap = [
        self::SHIP_STATUS_PENDING   => '未发货',
        self::SHIP_STATUS_DELIVERED => '已发货',
        self::SHIP_STATUS_RECEIVED  => '已收货',
    ];

    public static $acceptanceStatusMap = [
        self::ACCEPTANCE_STATUS_PENDING => '未受理',
        self::ACCEPTANCE_STATUS_ACCEPTED => '已受理',
    ];

    // 用于获取订单状态字符串的辅助方法
    public function getOrderStatusText()
    {
        if ($this->closed && data_get($this->extra, 'allocation_voided')) {
            return 'ALLOCATION_VOIDED';
        }
        if (!$this->paid_at && $this->closed) {
            return '已关闭';
        }
        if ($this->paid_at) {
            return $this->refund_status === self::REFUND_STATUS_PENDING
                ? '已支付'
                : self::$refundStatusMap[$this->refund_status];
        }
        return '待支付';
    }

    public function getDisplayStatusAttribute()
    {
        if (!is_site_mode_b()) {
            return $this->getOrderStatusText();
        }

        if (!$this->paid_at && $this->closed) {
            return data_get($this->extra, 'allocation_voided') ? '调拨作废' : '已关闭';
        }

        if (!$this->paid_at) {
            return '待支付';
        }

        if ($this->refund_status !== self::REFUND_STATUS_PENDING) {
            return self::$refundStatusMap[$this->refund_status];
        }

        $shipStatusMapB = [
            self::SHIP_STATUS_PENDING => '待履行/采购中',
            self::SHIP_STATUS_DELIVERED => '已入关/转寄中',
            self::SHIP_STATUS_RECEIVED => '已签收（任务完成）',
        ];

        return data_get($shipStatusMapB, $this->ship_status, '待履行/采购中');
    }

    // 计算订单剩余时间（秒）
    public function getAllocationExpiresIn()
    {
        if ($this->paid_at || $this->closed) {
            return 0;
        }
        $expiresAt = $this->getAllocationExpiresAt();
        return max(0, now()->diffInSeconds($expiresAt, false));
    }

    public function getAllocationExpiresAt()
    {
        $ttl = config('app.order_ttl', 1200);
        return $this->created_at->copy()->addSeconds($ttl);
    }

    public function isAllocationExpired()
    {
        if ($this->paid_at || $this->closed) {
            return false;
        }

        return $this->getAllocationExpiresIn() <= 0;
    }

    public function closeAsAllocationVoided()
    {
        return \DB::transaction(function () {
            $order = self::query()
                ->with(['items.productSku', 'couponCode'])
                ->lockForUpdate()
                ->find($this->id);

            if (!$order || $order->paid_at || $order->closed) {
                return false;
            }

            $extra = $order->extra ?: [];
            $extra['allocation_voided'] = true;
            $extra['allocation_voided_at'] = now()->toDateTimeString();

            $order->update([
                'closed' => true,
                'extra' => $extra,
            ]);

            foreach ($order->items as $item) {
                if ($item->productSku) {
                    $item->productSku->addStock($item->amount);
                }
            }

            if ($order->couponCode) {
                $order->couponCode->changeUsed(false);
            }

            $this->refresh();
            return true;
        });
    }

    // 检查是否已经调拨作废
    public function isAllocationVoided()
    {
        return (bool) data_get($this->extra, 'allocation_voided', false);
    }

    // A站订单在已支付但尚未正式受理前，允许变更收货信息
    public function canChangeInfo()
    {
        if (is_site_mode_b()) {
            return false;
        }

        return (bool) $this->paid_at
            && !$this->closed
            && $this->ship_status === self::SHIP_STATUS_PENDING
            && !$this->isAllocationVoided();
    }

    public function canSwapItem()
    {
        return (bool) $this->paid_at
            && !$this->closed
            && !$this->isAllocationVoided()
            && $this->ship_status === self::SHIP_STATUS_PENDING
            && $this->isPendingAcceptance()
            && !is_site_mode_b();
    }

    public function getEditableAddressSnapshot()
    {
        return [
            'contact_name' => (string) data_get($this->address, 'contact_name', ''),
            'contact_phone' => (string) data_get($this->address, 'contact_phone', ''),
            'zip' => (string) data_get($this->address, 'zip', ''),
            'address' => (string) data_get($this->address, 'address', ''),
        ];
    }

    public function getAcceptanceStatusAttribute()
    {
        $status = (string) data_get($this->extra, 'acceptance.status', '');
        if (in_array($status, [self::ACCEPTANCE_STATUS_PENDING, self::ACCEPTANCE_STATUS_ACCEPTED], true)) {
            return $status;
        }

        return $this->ship_status === self::SHIP_STATUS_PENDING
            ? self::ACCEPTANCE_STATUS_PENDING
            : self::ACCEPTANCE_STATUS_ACCEPTED;
    }

    public function isAccepted()
    {
        return $this->acceptance_status === self::ACCEPTANCE_STATUS_ACCEPTED;
    }

    public function isPendingAcceptance()
    {
        return $this->acceptance_status === self::ACCEPTANCE_STATUS_PENDING;
    }

    public function markAcceptance($status, $operatorId = null)
    {
        if (!in_array($status, [self::ACCEPTANCE_STATUS_PENDING, self::ACCEPTANCE_STATUS_ACCEPTED], true)) {
            return false;
        }

        $extra = $this->extra ?: [];
        $extra['acceptance'] = [
            'status' => $status,
            'updated_at' => now()->toDateTimeString(),
            'updated_by' => $operatorId,
        ];

        return $this->update(['extra' => $extra]);
    }

    protected $fillable = [
        'no',
        'address',
        'total_amount',
        'remark',
        'paid_at',
        'payment_method',
        'payment_no',
        'refund_status',
        'refund_no',
        'closed',
        'reviewed',
        'ship_status',
        'ship_data',
        'fulfillment_photo',
        'tracking_no',
        'extra',
    ];

    protected $casts = [
        'closed'    => 'boolean',
        'reviewed'  => 'boolean',
        'address'   => 'json',
        'ship_data' => 'json',
        'extra'     => 'json',
    ];

    protected $dates = [
        'paid_at',
    ];

    protected static function boot()
    {
        parent::boot();
        // 监听模型创建事件，在写入数据库之前触发
        static::creating(function ($model) {
            // 如果模型的 no 字段为空
            if (!$model->no) {
                // 调用 findAvailableNo 生成订单流水号
                $model->no = static::findAvailableNo();
                // 如果生成失败，则终止创建订单
                if (!$model->no) {
                    return false;
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function couponCode()
    {
        return $this->belongsTo(CouponCode::class);
    }

    // 兼容历史数据与中间层重复转换：当值已是数组/对象时直接返回，避免 json_decode(array) 报错。
    public function fromJson($value, $asObject = false)
    {
        if (is_array($value)) {
            return $asObject ? (object) $value : $value;
        }

        if (is_object($value)) {
            if ($asObject) {
                return $value;
            }

            return json_decode(json_encode($value), true);
        }

        return parent::fromJson($value, $asObject);
    }
    
    public static function findAvailableNo()
    {
        // 订单流水号前缀
        $prefix = date('YmdHis');
        for ($i = 0; $i < 10; $i++) {
            // 随机生成 6 位的数字
            $no = $prefix.str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            // 判断是否已经存在
            if (!static::query()->where('no', $no)->exists()) {
                return $no;
            }
            usleep(100);
        }
        \Log::warning(sprintf('find order no failed'));

        return false;
    }

    public static function getAvailableRefundNo()
    {
        do {
            // Uuid类可以用来生成大概率不重复的字符串
            $no = Uuid::uuid4()->getHex();
            // 为了避免重复我们在生成之后在数据库中查询看看是否已经存在相同的退款订单号
        } while (self::query()->where('refund_no', $no)->exists());

        return $no;
    }
}
