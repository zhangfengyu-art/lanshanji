<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\CourierApplication;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\OrderItem;
use App\Models\Category;
use App\Models\ProcurementOrder;
use App\Models\ProcurementReferenceItem;
use App\Models\ProcurementReferenceGallery;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ProductsController extends Controller
{
    protected function categoryQueryForCurrentSite()
    {
        $query = Category::query();

        if (Schema::hasColumn('categories', 'site_mode')) {
            $query->where('site_mode', is_site_mode_b() ? 'B' : 'A');
        }

        return $query;
    }

    protected function siteView(string $name, array $data = [], ?string $fallback = null)
    {
        $candidates = [];

        if (is_site_mode_b()) {
            $candidates[] = 'b_mode.' . ltrim($name, '.');
        }

        $candidates[] = ltrim($name, '.');

        if ($fallback) {
            $candidates[] = ltrim($fallback, '.');
        }

        return view()->first($candidates, $data);
    }

    public function index(Request $request)
    {
        // 创建一个查询构造器
        $builder = Product::query()->with('skus')->where('on_sale', true);

        // A/B 商品硬隔离：A 站只显示常规商品，B 站只显示原生求购商品。
        if (Schema::hasColumn('products', 'is_from_native_procurement')) {
            if (is_site_mode_b()) {
                $builder->where('is_from_native_procurement', true);
            } else {
                $builder->where(function ($query) {
                    $query->whereNull('is_from_native_procurement')
                        ->orWhere('is_from_native_procurement', false);
                });
            }
        }

        $categoryId = $request->input('category');
        $search = $request->input('search', '');
        $order = $request->input('order', '');
        $breadcrumbParent = null;
        $breadcrumbChild = null;

        if ($categoryId) {
            $currentCategory = $this->categoryQueryForCurrentSite()->find((int) $categoryId);
            if ($currentCategory) {
                $builder->whereIn('category_id', $currentCategory->selfAndDescendantIds());

                if ($currentCategory->parent_id) {
                    $breadcrumbParent = $this->categoryQueryForCurrentSite()->find((int) $currentCategory->parent_id);
                    $breadcrumbChild = $currentCategory;
                } else {
                    $breadcrumbParent = $currentCategory;
                }
            } else {
                $builder->where('category_id', (int) $categoryId);
            }
        }

        // 判断是否有提交 search 参数，如果有就赋值给 $search 变量
        // search 参数用来模糊搜索商品
        if ($search) {
            $like = '%'.$search.'%';
            $skuMatchedProductIds = ProductSku::query()
                ->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->pluck('product_id')
                ->all();

            // 模糊搜索商品标题、商品详情、SKU 标题、SKU描述
            $builder->where(function ($query) use ($like, $skuMatchedProductIds) {
                $query->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like);

                if (!empty($skuMatchedProductIds)) {
                    $query->orWhereIn('id', $skuMatchedProductIds);
                }
            });
        }

        // 是否有提交 order 参数，如果有就赋值给 $order 变量
        // order 参数用来控制商品的排序规则
        if ($order) {
            // 是否是以 _asc 或者 _desc 结尾
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                // 如果字符串的开头是这 3 个字符串之一，说明是一个合法的排序值
                if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
                    // 根据传入的排序值来构造排序参数
                    $builder->orderBy($m[1], $m[2]);
                }
            }
        }

        $products = $builder->paginate(16);
        $procurementOrders = collect();
        $referenceGallery = collect();
        $demoModeEnabled = in_array(strtolower((string) env('DEMO_MODE', 'false')), ['1', 'true', 'yes', 'on'], true);
        if (is_site_mode_b() && Schema::hasTable('procurement_orders')) {
            // Mixed feed: show fresh reference seeds first, then real/mock procurement orders.
            $procurementOrders = ProcurementOrder::query()->orderBy('created_at', 'desc')->limit(42)->get();

            if (Schema::hasTable('procurement_reference_gallery')) {
                $referenceGallery = ProcurementReferenceGallery::query()
                    ->with('category')
                    ->orderBy('updated_at', 'desc')
                    ->limit(8)
                    ->get();
            }

            $referenceSeeds = collect();
            if (Schema::hasTable('procurement_reference_items')) {
                $referenceSeeds = ProcurementReferenceItem::query()
                    ->orderBy('id', 'desc')
                    ->limit(6)
                    ->get()
                    ->map(function (ProcurementReferenceItem $item) {
                        return (object) [
                            'item_name' => (string) $item->name,
                            'item_image' => (string) $item->image_url,
                            'buyer_nickname' => '参考用户',
                            'proxy_status' => ProcurementOrder::STATUS_PENDING,
                            'order_narrative' => sprintf('参考商品分类：%s，等待真实用户发起求购。', (string) ($item->category ?: '未分类')),
                            'budget_amount' => (float) $item->reference_price,
                            'extra' => [
                                'is_reference_seed' => true,
                            ],
                        ];
                    });
            }

            if ($referenceSeeds->isNotEmpty()) {
                // Keep UGC procurement requests on top; reference seeds act as fallback cards.
                $procurementOrders = $procurementOrders->concat($referenceSeeds)->take(48)->values();
            }

            $procurementOrders = $procurementOrders->map(function ($order) {
                $itemName = trim((string) data_get($order, 'item_name', ''));
                $order->buy_url = $this->resolveBuyUrl($order, $itemName);

                return $order;
            });
        }

        return $this->siteView('products.index', [
            'products' => $products,
            'procurementOrders' => $procurementOrders,
            'referenceGallery' => $referenceGallery,
            'demoModeEnabled' => $demoModeEnabled,
            'breadcrumbParent' => $breadcrumbParent,
            'breadcrumbChild' => $breadcrumbChild,
            'filters'  => [
                'category' => $categoryId,
                'search' => $search,
                'order'  => $order,
            ],
        ]);
    }

    public function show(Product $product, Request $request)
    {
        if (!$product->on_sale) {
            throw new InvalidRequestException('商品未上架');
        }

        if (Schema::hasColumn('products', 'is_from_native_procurement')) {
            $isNativeProcurement = (bool) $product->is_from_native_procurement;
            // 详情页同样遵守 A/B 隔离，避免通过直链绕过列表过滤。
            if (is_site_mode_b() && !$isNativeProcurement) {
                throw new InvalidRequestException('商品不存在');
            }
            if (!is_site_mode_b() && $isNativeProcurement) {
                throw new InvalidRequestException('商品不存在');
            }
        }

        $favored = false;
        // 用户未登录时返回的是 null，已登录时返回的是对应的用户对象
        if($user = $request->user()) {
            // 从当前用户已收藏的商品中搜索 id 为当前商品 id 的商品
            // boolval() 函数用于把值转为布尔值
            $favored = boolval($user->favoriteProducts()->find($product->id));
        }
        
        $reviews = OrderItem::query()
            ->with(['order.user', 'productSku']) // 预先加载关联关系
            ->where('product_id', $product->id)
            ->whereNotNull('reviewed_at') // 筛选出已评价的
            ->orderBy('reviewed_at', 'desc') // 按评价时间倒序
            ->limit(10) // 取出 10 条
            ->get();

        $product->loadMissing('category');
        $breadcrumbParent = null;
        $breadcrumbChild = null;

        if ($product->category) {
            $siteCategory = $this->categoryQueryForCurrentSite()->find((int) $product->category->id);
            if ($siteCategory) {
                if ($siteCategory->parent_id) {
                    $breadcrumbParent = $this->categoryQueryForCurrentSite()->find((int) $siteCategory->parent_id);
                    $breadcrumbChild = $siteCategory;
                } else {
                    $breadcrumbParent = $siteCategory;
                }
            }
        }
        
        // 最后别忘了注入到模板中
        return $this->siteView('products.show', [
            'product' => $product,
            'favored' => $favored,
            'reviews' => $reviews,
            'breadcrumbParent' => $breadcrumbParent,
            'breadcrumbChild' => $breadcrumbChild,
        ]);
    }

    public function favor(Product $product, Request $request)
    {
        $user = $request->user();
        if ($user->favoriteProducts()->find($product->id)) {
            return [];
        }

        $user->favoriteProducts()->attach($product);

        return [];
    }

    public function disfavor(Product $product, Request $request)
    {
        $user = $request->user();
        $user->favoriteProducts()->detach($product);

        return [];
    }

    public function favorites(Request $request)
    {
        $products = $request->user()->favoriteProducts()->paginate(16);

        return $this->siteView('products.favorites', ['products' => $products]);
    }

    public function procurementDetail(Request $request)
    {
        $procurementOrderId = (int) $request->input('procurement_order_id', 0);
        $courierStatus = 'guest';
        $itemName = trim((string) $request->input('item_name', '未命名求购'));
        $itemImage = trim((string) $request->input('item_image', ''));
        $budgetAmount = (float) $request->input('budget_amount', 0);
        $narrative = trim((string) $request->input('narrative', ''));
        $nativeRequest = filter_var($request->input('native_request', false), FILTER_VALIDATE_BOOLEAN);

        if ($request->user()) {
            $application = CourierApplication::query()
                ->where('user_id', (int) $request->user()->id)
                ->orderBy('id', 'desc')
                ->first();

            $courierStatus = $application ? (string) $application->status : 'none';
        }

        $recommendedProducts = $nativeRequest ? collect() : $this->findRecommendedProducts($itemName);
        $matchedProduct = $nativeRequest ? null : $recommendedProducts->first();
        $quickPayProduct = $nativeRequest ? null : ($matchedProduct ?: $recommendedProducts->first());

        $procurementMeta = [
            'item_name' => $itemName,
            'category' => $this->extractCategoryFromNarrative($narrative),
            'budget_amount' => $budgetAmount,
            'narrative' => $narrative,
            'has_match' => $matchedProduct !== null,
        ];

        return $this->siteView('products.procurement_show', [
            'itemName' => $itemName,
            'itemImage' => $itemImage,
            'budgetAmount' => $budgetAmount,
            'narrative' => $narrative,
            'nativeRequest' => $nativeRequest,
            'procurementOrderId' => $procurementOrderId,
            'courierStatus' => $courierStatus,
            'procurementMeta' => $procurementMeta,
            'matchedProduct' => $matchedProduct,
            'recommendedProducts' => $recommendedProducts,
            'quickPayProduct' => $quickPayProduct,
        ]);
    }

    public function procurementCheckout(Request $request)
    {
        $nativeRequest = filter_var($request->input('native_request', false), FILTER_VALIDATE_BOOLEAN);
        $procurementOrderId = (int) $request->input('procurement_order_id', 0);

        if ($nativeRequest || $procurementOrderId > 0) {
            $procurementOrder = ProcurementOrder::query()->find($procurementOrderId);
            if (!$procurementOrder) {
                throw new InvalidRequestException('求购委托不存在');
            }

            if ((int) data_get($procurementOrder, 'user_id') !== (int) $request->user()->id) {
                throw new InvalidRequestException('无权查看该求购委托');
            }

            $addresses = $request->user()->addresses()->orderBy('last_used_at', 'desc')->orderBy('id', 'desc')->get();
            if ($addresses->isEmpty()) {
                return redirect()->route('user_addresses.create', [
                    'redirect' => route('procurement.checkout', [
                        'procurement_order_id' => $procurementOrderId,
                        'native_request' => 1,
                    ]),
                ]);
            }

            return $this->siteView('products.procurement_checkout_native', [
                'procurementOrder' => $procurementOrder,
                'itemName' => (string) $procurementOrder->item_name,
                'budgetAmount' => (float) $procurementOrder->budget_amount,
                'narrative' => (string) $procurementOrder->order_narrative,
                'serviceRate' => 0.13,
                'packagingFee' => 300,
                'shippingFee' => 0,
                'addresses' => $addresses,
            ]);
        }

        $productId = (int) $request->input('product_id');
        $product = Product::query()->with(['skus' => function ($query) {
            $query->orderBy('price', 'asc');
        }])->find($productId);

        if (!$product || !$product->on_sale) {
            throw new InvalidRequestException('商品不可购买或已下架');
        }

        $addresses = $request->user()->addresses()->orderBy('last_used_at', 'desc')->orderBy('id', 'desc')->get();
        if ($addresses->isEmpty()) {
            return redirect()->route('user_addresses.create', [
                'redirect' => route('procurement.checkout', ['product_id' => $product->id]),
            ]);
        }

        $defaultSku = $product->skus->first();
        if (!$defaultSku) {
            throw new InvalidRequestException('该商品暂无可售规格');
        }

        return $this->siteView('products.procurement_checkout', [
            'product' => $product,
            'addresses' => $addresses,
            'defaultSku' => $defaultSku,
        ]);
    }

    public function procurementAgreement(Request $request)
    {
        $productId = (int) $request->input('product_id');
        $product = Product::query()->with(['skus' => function ($query) {
            $query->orderBy('price', 'asc');
        }])->find($productId);

        if (!$product || !$product->on_sale) {
            throw new InvalidRequestException('商品不可购买或已下架');
        }

        $defaultSku = $product->skus->first();
        if (!$defaultSku) {
            throw new InvalidRequestException('该商品暂无可售规格');
        }

        $addresses = $request->user()->addresses()->orderBy('last_used_at', 'desc')->orderBy('id', 'desc')->get();
        if ($addresses->isEmpty()) {
            return redirect()->route('user_addresses.create', [
                'redirect' => route('procurement.agreement', [
                    'product_id' => $product->id,
                    'item_name' => $request->input('item_name', ''),
                    'budget_amount' => $request->input('budget_amount', 0),
                    'narrative' => $request->input('narrative', ''),
                ]),
            ]);
        }

        return $this->siteView('products.procurement_agreement', [
            'product' => $product,
            'defaultSku' => $defaultSku,
            'addresses' => $addresses,
            'itemName' => trim((string) $request->input('item_name', $product->title)),
            'budgetAmount' => (float) $request->input('budget_amount', $defaultSku->price),
            'narrative' => trim((string) $request->input('narrative', '')),
        ]);
    }

    public function procurementCreate(Request $request)
    {
        abort_unless(is_site_mode_b(), 404);

        $categories = Category::query()->orderBy('name', 'asc')->get(['id', 'name']);
        if ($categories->isEmpty()) {
            $fallback = $this->ensureFallbackCategory();
            $categories = collect([$fallback])->values();
        }

        return view('products.procurement_create', [
            'categories' => $categories,
        ]);
    }

    public function procurementAccept(Request $request)
    {
        abort_unless(is_site_mode_b(), 404);

        $procurementOrderId = (int) $request->input('procurement_order_id', 0);
        $user = $request->user();

        $application = CourierApplication::query()
            ->where('user_id', (int) $user->id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$application) {
            return redirect()->route('procurement.apply')
                ->with('warning', '承接任务前请先完成代购资质申请');
        }

        if ($application->status === CourierApplication::STATUS_PENDING) {
            return redirect()->back()->with('warning', '您的代购资质正在审核中，请耐心等待');
        }

        if ($application->status !== CourierApplication::STATUS_APPROVED) {
            return redirect()->route('procurement.apply')
                ->with('warning', '您的代购资质未通过审核，请重新提交资料');
        }

        // 如果没有传递 procurement_order_id，尝试按 item_name 和 budget_amount 查找
        if (! $procurementOrderId) {
            $itemName = trim((string) $request->input('item_name', ''));
            $budgetAmount = (float) $request->input('budget_amount', 0);
            $nativeRequest = filter_var($request->input('native_request', false), FILTER_VALIDATE_BOOLEAN);

            if ($itemName === '' || $budgetAmount <= 0) {
                throw new InvalidRequestException('无效的求购信息');
            }

            // 按参数查找对应的 ProcurementOrder
            $procurementOrder = ProcurementOrder::query()
                ->where('item_name', $itemName)
                ->where('budget_amount', $budgetAmount)
                ->where('proxy_status', ProcurementOrder::STATUS_PENDING)
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $procurementOrder) {
                throw new InvalidRequestException('求购需求不存在或已被接单');
            }
        } else {
            $procurementOrder = ProcurementOrder::query()->find($procurementOrderId);
            if (! $procurementOrder) {
                throw new InvalidRequestException('求购需求不存在');
            }
        }

        // 验证：代购方不能是需求方
        if ((int) $procurementOrder->user_id === (int) $user->id) {
            throw new InvalidRequestException('你不能接自己发起的需求');
        }

        // 验证：已接单的需求不能再接单
        if ($procurementOrder->proxy_status !== ProcurementOrder::STATUS_PENDING) {
            throw new InvalidRequestException('该求购需求已被接单或已完成，无法重复接单');
        }

        // 验证：已有接单人的需求不能再接单
        if ($procurementOrder->accepted_by !== null) {
            throw new InvalidRequestException('该求购需求已被其他代购人接单');
        }

        // 标记为已接单
        $procurementOrder->update([
            'accepted_by' => $user->id,
            'accepted_at' => now(),
            'proxy_status' => ProcurementOrder::STATUS_ACCEPTED,
        ]);

        return redirect()->route('procurement.detail', [
            'procurement_order_id' => (int) $procurementOrder->id,
            'item_name' => (string) $procurementOrder->item_name,
            'item_image' => (string) $procurementOrder->item_image,
            'budget_amount' => (float) $procurementOrder->budget_amount,
            'narrative' => (string) $procurementOrder->order_narrative,
            'native_request' => 1,
        ])->with('success', '接单成功！订单号：' . $procurementOrder->no . '，请等待需求方支付');
    }

    public function procurementStore(Request $request)
    {
        abort_unless(is_site_mode_b(), 404);

        $fallbackCategory = $this->ensureFallbackCategory();
        if (!(int) $request->input('category_id')) {
            $request->merge(['category_id' => (int) $fallbackCategory->id]);
        }

        $validated = $this->validate($request, [
            'item_name' => 'required|string|max:120',
            'category_id' => 'required|integer|exists:categories,id',
            'budget_amount' => 'required|numeric|min:0.01',
            'order_narrative' => 'nullable|string|max:3000',
            'image_url' => 'nullable|image|max:5120',
        ], [
            'item_name.required' => '请填写委托物品名称',
            'category_id.required' => '请选择需求分类',
            'category_id.exists' => '需求分类不合法，请重新选择',
            'budget_amount.required' => '请填写委托预算',
            'budget_amount.min' => '委托预算必须大于 0',
            'image_url.image' => '上传文件必须是图片格式',
            'image_url.max' => '图片大小不能超过 5MB',
        ]);

        $category = Category::query()->find((int) $validated['category_id']) ?: $fallbackCategory;
        $itemImage = '';
        if ($request->hasFile('image_url')) {
            $itemImage = (string) $request->file('image_url')->store('references', 'public');
        }

        $actor = $this->resolveProcurementUser($request);
        $buyerNickname = trim((string) data_get($actor, 'name', ''));
        if ($buyerNickname === '') {
            $buyerNickname = '用户' . (string) data_get($actor, 'id', '');
        }

        $procurementOrder = ProcurementOrder::query()->create([
            'order_no' => null,
            'user_id' => data_get($actor, 'id'),
            'item_name' => trim((string) $validated['item_name']),
            'item_image' => $itemImage,
            'buyer_nickname' => $buyerNickname,
            'proxy_status' => ProcurementOrder::STATUS_PENDING,
            'order_narrative' => trim((string) ($validated['order_narrative'] ?? '')),
            'budget_amount' => (float) $validated['budget_amount'],
            'extra' => [
                'is_demo_data' => false,
                'is_user_generated' => true,
                'is_native_request' => true,
                'budget_equals_item_amount' => true,
                'no_sku' => true,
                'requested_amount' => (float) $validated['budget_amount'],
                'item_amount' => (float) $validated['budget_amount'],
                'category_id' => (int) $validated['category_id'],
                'category_name' => (string) data_get($category, 'name', ''),
                'order_id' => null,
            ],
        ]);

        return redirect()->route('procurement.checkout', [
            'procurement_order_id' => $procurementOrder->id,
            'native_request' => 1,
        ])->with('success', '求购委托已发布，正在进入金额核算页。');
    }

    protected function ensureFallbackCategory()
    {
        if ($category = Category::query()->orderBy('id', 'asc')->first()) {
            return $category;
        }

        if ($byId = Category::query()->find(1)) {
            return $byId;
        }

        $category = new Category();
        $category->id = 1;
        $category->name = '综合代购';
        $category->is_directory = false;
        $category->parent_id = null;
        $category->level = 0;
        $category->save();

        return $category;
    }

    protected function resolveProcurementUser(Request $request)
    {
        if ($request->user()) {
            return $request->user();
        }

        return User::query()->firstOrCreate([
            'email' => 'guest.procurement@arashiyama.local',
        ], [
            'name' => '游客用户',
            'password' => bcrypt(str_random(24)),
            'email_verified' => true,
        ]);
    }

    protected function resolveBuyUrl($order, $itemName)
    {
        if ($itemName === '') {
            return route('products.index');
        }

        if (data_get($order, 'extra.is_user_generated', false)) {
                return route('procurement.detail', [
                    'procurement_order_id' => (int) data_get($order, 'id', 0),
                'item_name' => $itemName,
                'item_image' => (string) data_get($order, 'item_image', ''),
                'budget_amount' => (float) data_get($order, 'budget_amount', 0),
                'narrative' => (string) data_get($order, 'order_narrative', ''),
                'native_request' => 1,
            ]);
        }

        $product = Product::query()
            ->where('on_sale', true)
            ->where(function ($query) use ($itemName) {
                $query->where('title', $itemName)
                    ->orWhere('title', 'like', '%' . $itemName . '%');
            })
            ->orderBy('sold_count', 'desc')
            ->first();

        if ($product) {
            return route('products.show', ['product' => $product->id]);
        }

        return route('procurement.detail', [
            'item_name' => $itemName,
            'item_image' => (string) data_get($order, 'item_image', ''),
            'budget_amount' => (float) data_get($order, 'budget_amount', 0),
            'narrative' => (string) data_get($order, 'order_narrative', ''),
        ]);
    }

    protected function findRecommendedProducts($itemName)
    {
        if ($itemName === '') {
            return collect();
        }

        $like = '%' . $itemName . '%';
        $skuMatchedProductIds = ProductSku::query()
            ->where('title', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->pluck('product_id')
            ->all();

        $matched = Product::query()
            ->with(['skus' => function ($query) {
                $query->orderBy('price', 'asc');
            }, 'category.parent'])
            ->where('on_sale', true)
            ->where(function ($query) use ($itemName, $like, $skuMatchedProductIds) {
                $query->where('title', $itemName)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like);

                if (!empty($skuMatchedProductIds)) {
                    $query->orWhereIn('id', $skuMatchedProductIds);
                }
            })
            ->orderBy('sold_count', 'desc')
            ->limit(8)
            ->get();

        if ($matched->isNotEmpty()) {
            return $matched;
        }

        return Product::query()
            ->with(['skus' => function ($query) {
                $query->orderBy('price', 'asc');
            }, 'category.parent'])
            ->where('on_sale', true)
            ->orderBy('sold_count', 'desc')
            ->limit(6)
            ->get();
    }

    protected function extractCategoryFromNarrative($narrative)
    {
        $text = trim((string) $narrative);
        if ($text === '') {
            return '未指定';
        }

        if (preg_match('/参考商品分类：([^，。]+)/u', $text, $matches)) {
            return trim((string) $matches[1]) ?: '未指定';
        }

        return '未指定';
    }
}
