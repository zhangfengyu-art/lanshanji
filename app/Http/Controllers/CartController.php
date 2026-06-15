<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\AddCartRequest;
use App\Http\Requests\UpdateCartRequest;
use App\Models\ProductSku;
use App\Services\CartService;
use App\Services\EmsShippingFeeService;
use App\Services\OrderCheckoutQuoteService;
use App\Services\OrderTobaccoLimitService;
use App\Services\ShippingModeService;

class CartController extends Controller
{
    protected $cartService;

    protected $tobaccoLimits;

    protected $emsShipping;

    protected $checkoutQuote;

    protected $shippingModes;

    public function __construct(
        CartService $cartService,
        OrderTobaccoLimitService $tobaccoLimits,
        EmsShippingFeeService $emsShipping,
        OrderCheckoutQuoteService $checkoutQuote,
        ShippingModeService $shippingModes
    ) {
        $this->cartService = $cartService;
        $this->tobaccoLimits = $tobaccoLimits;
        $this->emsShipping = $emsShipping;
        $this->checkoutQuote = $checkoutQuote;
        $this->shippingModes = $shippingModes;
    }

    public function index(Request $request)
    {
        $cartItems = $this->cartService->get();
        $addresses = $request->user()->addresses()
            ->orderBy('is_default', 'desc')
            ->orderBy('last_used_at', 'desc')
            ->get();

        return view('cart.index', [
            'cartItems' => $cartItems,
            'addresses' => $addresses,
            'emsTiers' => $this->emsShipping->tiers(),
            'tobaccoLimits' => [
                'max_sticks' => $this->tobaccoLimits->maxCigaretteSticks(),
                'max_boxes' => $this->tobaccoLimits->maxCigaretteBoxes(),
                'max_rolling_grams' => $this->tobaccoLimits->maxRollingTobaccoGrams(),
                'max_billable_grams' => $this->emsShipping->maxBillableGrams(),
            ],
        ]);
    }

    public function add(AddCartRequest $request)
    {
        $this->cartService->add($request->input('sku_id'), $request->input('amount'));

        return ['count' => $this->cartService->getTotalAmount()];
    }

    public function summary(Request $request)
    {
        $items = $this->cartService->get();

        $payload = $items->map(function ($item) {
            $product = $item->productSku->product;

            return [
                'sku_id' => $item->product_sku_id,
                'amount' => $item->amount,
                'price' => $item->productSku->price,
                'sale_status' => $product->inventory_status,
                'max_qty' => $item->productSku->getOrderMaxQty(),
                'title' => $product->title,
                'sku_title' => $item->productSku->title,
                'image_url' => $product->image_url,
                'product_url' => route('products.show', ['product' => $item->productSku->product_id]),
                'tobacco_type' => $product->tobacco_type,
                'shipping_mode' => $this->shippingModes->resolveForProduct($product),
                'unit_weight_grams' => (int) $product->unit_weight_grams,
                'unit_sticks' => (int) $product->unit_sticks,
            ];
        })->values();

        return [
            'count' => (int) $items->sum('amount'),
            'items' => $payload,
        ];
    }

    public function quote(Request $request)
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku_id' => ['required', 'integer'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
        ]);

        if (!is_site_mode_a()) {
            return response()->json([
                'products_total' => 0,
                'service_fee' => 0,
                'packaging_fee' => 0,
                'ems_shipping_fee' => 0,
                'payable' => 0,
                'valid' => true,
            ]);
        }

        $items = $request->input('items', []);

        try {
            $data = $this->checkoutQuote->quote($items);
            $data['valid'] = true;

            return response()->json($data);
        } catch (\App\Exceptions\InvalidRequestException $e) {
            $productsTotal = 0;
            foreach ($items as $row) {
                $sku = ProductSku::query()->find((int) data_get($row, 'sku_id'));
                if ($sku) {
                    $productsTotal += ((float) $sku->price) * (int) data_get($row, 'amount', 0);
                }
            }

            return response()->json([
                'valid' => false,
                'message' => $e->getMessage(),
                'products_total' => round($productsTotal, 2),
                'service_fee' => 0,
                'packaging_fee' => 0,
                'ems_shipping_fee' => 0,
                'payable' => round($productsTotal, 2),
            ], 422);
        }
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
