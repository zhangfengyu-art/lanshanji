<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\AddCartRequest;
use App\Http\Requests\UpdateCartRequest;
use App\Models\ProductSku;
use App\Services\CartService;

class CartController extends Controller
{
    protected $cartService;

    // 利用 Laravel 的自动解析功能注入 CartService 类
    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function index(Request $request)
    {
        $cartItems = $this->cartService->get();
        $addresses = $request->user()->addresses()
            ->orderBy('is_default', 'desc')
            ->orderBy('last_used_at', 'desc')
            ->get();

        return view('cart.index', ['cartItems' => $cartItems, 'addresses' => $addresses]);
    }

    public function add(AddCartRequest $request)
    {
        $this->cartService->add($request->input('sku_id'), $request->input('amount'));

        return [];
    }

    public function summary(Request $request)
    {
        $items = $this->cartService->get();
        $limits = $this->cartService->validateLogisticsLimits($items);

        $sticksProgress = $limits['sticks_limit'] > 0
            ? round(($limits['total_sticks'] / $limits['sticks_limit']) * 100, 1)
            : 0;
        $weightProgress = $limits['weight_limit'] > 0
            ? round(($limits['total_weight'] / $limits['weight_limit']) * 100, 1)
            : 0;

        $payload = $items->map(function ($item) {
            $sku = $item->productSku;
            $product = $sku ? $sku->product : null;

            return [
                'sku_id' => $item->product_sku_id,
                'amount' => $item->amount,
                'price' => $sku ? $sku->price : 0,
                'stock' => $sku ? $sku->stock : 0,
                'title' => $product ? $product->title : '商品已失效',
                'sku_title' => $sku ? $sku->title : '',
                'image_url' => $product ? $product->image_url : '/images/b_mode/proc-placeholder.svg',
                'product_url' => $product ? route('products.show', ['product' => $sku->product_id]) : null,
            ];
        })->values();

        return [
            'count' => (int) $items->sum('amount'),
            'items' => $payload,
            'logistics_summary' => [
                'total_sticks' => (int) $limits['total_sticks'],
                'sticks_limit' => (int) $limits['sticks_limit'],
                'sticks_progress' => $sticksProgress,
                'total_weight' => (int) $limits['total_weight'],
                'weight_limit' => (int) $limits['weight_limit'],
                'weight_progress' => $weightProgress,
                'exceeded' => (bool) $limits['exceeded'],
                'reason' => $limits['reason'],
            ],
        ];
    }

    public function remove(ProductSku $sku, Request $request)
    {
        $this->cartService->remove($sku->id);

        return [];
    }

    public function update(ProductSku $sku, UpdateCartRequest $request)
    {
        $item = $this->cartService->update($sku->id, $request->input('amount'));

        if (!$item) {
            abort(404, '购物车记录不存在');
        }

        return [];
    }
}
