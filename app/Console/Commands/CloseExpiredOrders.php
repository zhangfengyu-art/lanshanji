<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;

class CloseExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:close-expired';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = '关闭已过期且未支付的订单（调拨作废）';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // 获取订单TTL配置（秒数）
        $orderTtl = config('app.order_ttl', 1200);
        
        // 计算过期时间点：订单创建时间 + TTL < 当前时间
        $expiredAt = Carbon::now()->subSeconds($orderTtl);
        
        // 找出所有未支付且未关闭，且创建时间超过TTL的订单
        $orders = Order::where('paid_at', null)
            ->where('closed', false)
            ->where('created_at', '<', $expiredAt)
            ->get();
        
        $count = 0;
        foreach ($orders as $order) {
            \DB::transaction(function() use ($order) {
                // 标记为调拨作废
                $extra = $order->extra ?: [];
                $extra['allocation_voided'] = true;
                $extra['allocation_voided_at'] = now()->toDateTimeString();
                
                // 将订单的 closed 字段标记为 true，即关闭订单
                $order->update([
                    'closed' => true,
                    'extra' => $extra,
                ]);
                
                // 循环遍历订单中的商品 SKU，将订单中的数量加回到 SKU 的库存中去
                foreach ($order->items as $item) {
                    $item->productSku->addStock($item->amount);
                }
                
                // 恢复优惠券使用次数
                if ($order->couponCode) {
                    $order->couponCode->changeUsed(false);
                }
            });
            $count++;
        }
        
        $this->info("已关闭 {$count} 个过期订单");
        
        return 0;
    }
}
