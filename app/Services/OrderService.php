<?php

namespace App\Services;

use App\Exceptions\CouponCodeUnavailableException;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\CouponCode;
use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use Carbon\Carbon;

class OrderService
{
    public function store(User $user, UserAddress $address, $remark, $items, CouponCode $coupon = null)
    {
        if (empty($items) || count($items) === 0) {
            throw new InvalidRequestException('请至少选择一件商品后再提交订单');
        }

        $isNativeProcurement = false;
        $procurementOrderId = null;
        $procurementOrderBudget = 0;

        // 检查是否是原生求购单
        if (!empty($items[0]) && isset($items[0]['is_native_procurement']) && $items[0]['is_native_procurement']) {
            $isNativeProcurement = true;
            $procurementOrderId = (int) data_get($items[0], 'procurement_order_id', 0);
            if ($procurementOrderId <= 0) {
                throw new InvalidRequestException('原生求购委托单号不合法');
            }
            $procurementOrder = \App\Models\ProcurementOrder::query()->find($procurementOrderId);
            if (!$procurementOrder) {
                throw new InvalidRequestException('原生求购委托不存在');
            }
            if ((int) $procurementOrder->user_id !== (int) $user->id) {
                throw new InvalidRequestException('无权操作该求购委托');
            }
            // 检查审核状态，只有已通过审核的订单才能创建订单
            if ((int) $procurementOrder->review_status !== \App\Models\ProcurementOrder::REVIEW_STATUS_APPROVED) {
                throw new InvalidRequestException('该求购单未通过管理员审核，无法支付');
            }
            $procurementOrderBudget = (float) $procurementOrder->budget_amount;
        }

        if ($coupon) {
            $coupon->checkAvailable($user);
        }
        // 开启一个数据库事务
        $order = \DB::transaction(function () use ($user, $address, $remark, $items, $coupon, $isNativeProcurement, $procurementOrderId, $procurementOrderBudget) {
            // 更新此地址的最后使用时间
            $address->update(['last_used_at' => Carbon::now()]);
            // 创建一个订单
            $order   = new Order([
                'address'      => [ // 将地址信息放入订单中
                    'address'       => $address->full_address,
                    'zip'           => $address->zip,
                    'contact_name'  => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark'       => $remark,
                'total_amount' => 0,
            ]);
            // 订单关联到当前用户
            $order->user()->associate($user);
            // 写入数据库
            $order->save();

            $totalAmount = 0;
            
            // 原生求购单的特殊处理
            if ($isNativeProcurement) {
                $procurementOrder = \App\Models\ProcurementOrder::query()->find($procurementOrderId);
                
                // 检查是否已经生成了虚拟商品，如果没有则创建
                $product = $procurementOrder->product ?? null;
                if (!$product) {
                    $product = \App\Models\Product::query()->create([
                        'title' => (string) $procurementOrder->item_name,
                        'description' => (string) $procurementOrder->order_narrative,
                        'image' => (string) $procurementOrder->item_image,
                        'on_sale' => true,
                        'is_from_native_procurement' => true,
                        'procurement_order_id' => $procurementOrderId,
                        'category_id' => 0, // B站原生求购商品用0类别
                        'price' => round($procurementOrderBudget, 2),
                    ]);
                    
                    // 创建对应的SKU
                    \App\Models\ProductSku::query()->create([
                        'product_id' => $product->id,
                        'title' => '原生求购单',
                        'description' => '来自B站原生求购的虚拟规格',
                        'price' => round($procurementOrderBudget, 2),
                        'stock' => 999,
                        'limit_qty' => 0,
                    ]);
                }
                
                $totalAmount = $procurementOrderBudget;
                
                // 创建OrderItem关联到虚拟商品
                $item = $order->items()->make([
                    'amount' => 1,
                    'price' => round($procurementOrderBudget, 2),
                ]);
                $item->product()->associate($product->id);
                
                // 创建对应的SKU关联
                $sku = $product->skus()->first();
                if ($sku) {
                    $item->productSku()->associate($sku->id);
                }
                
                $item->save();
            } else {
                // 遍历用户提交的 SKU
                foreach ($items as $data) {
                    $sku  = ProductSku::query()->lockForUpdate()->find($data['sku_id']);
                    if (!$sku) {
                        throw new InvalidRequestException('该商品不存在');
                    }
                    if (!$sku->product->on_sale) {
                        throw new InvalidRequestException('该商品未上架');
                    }

                    $amount = (int) $data['amount'];
                    if ($amount > $sku->getOrderMaxQty()) {
                        throw new InvalidRequestException('物资调拨状态已变更，请重新确认配额。');
                    }
                    // 创建一个 OrderItem 并直接与当前订单关联
                    $item = $order->items()->make([
                        'amount' => $amount,
                        'price'  => $sku->price,
                    ]);
                    $item->product()->associate($sku->product_id);
                    $item->productSku()->associate($sku);
                    $item->save();
                    $totalAmount += $sku->price * $amount;
                    if ($sku->decreaseStock($amount) <= 0) {
                        throw new InvalidRequestException('物资调拨状态已变更，请重新确认配额。');
                    }
                }
            }

            if ($coupon) {
                // 总金额已经计算出来了，检查是否符合优惠券规则
                $coupon->checkAvailable($user, $totalAmount);
                // 把订单金额修改为优惠后的金额
                $totalAmount = $coupon->getAdjustedPrice($totalAmount);
                // 将订单与优惠券关联
                $order->couponCode()->associate($coupon);
                // 增加优惠券的用量，需判断返回值
                if ($coupon->changeUsed() <= 0) {
                    throw new CouponCodeUnavailableException('该优惠券已被兑完');
                }
            }

            $serviceFee = round($totalAmount * 0.13, 2);
            $packagingFee = 300.00;
            $emsShippingFee = $isNativeProcurement ? 0 : 1750.00;
            $finalPayAmount = round($totalAmount + $serviceFee + $packagingFee + $emsShippingFee, 2);

            $extra = $order->extra ?: [];
            $extra['fee_details'] = [
                'base_amount' => round($totalAmount, 2),
                'service_fee' => $serviceFee,
                'packaging_fee' => $packagingFee,
                'ems_shipping_fee' => $emsShippingFee,
            ];
            
            if ($isNativeProcurement) {
                $extra['is_native_procurement'] = true;
                $extra['procurement_order_id'] = $procurementOrderId;
            }

            $order->update([
                'total_amount' => $finalPayAmount,
                'extra' => $extra,
            ]);
            
            // 将下单的商品从购物车中移除（原生求购单无SKU，跳过此步骤）
            if (!$isNativeProcurement) {
                $skuIds = collect($items)->pluck('sku_id')->all();
                app(CartService::class)->remove($skuIds);
            }

            return $order;
        });

        return $order;
    }
}
