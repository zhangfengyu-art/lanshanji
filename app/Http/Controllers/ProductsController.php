<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\OrderItem;
use App\Models\Category;
use App\Models\ProcurementOrder;
use App\Models\ProcurementReferenceItem;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        if (is_site_mode_b()) {
            return view('products.index', $this->buildProcurementHallIndexViewData());
        }

        // 创建一个查询构造器
        $builder = Product::query()->with('skus')->where('on_sale', true);
        $categoryId = $request->input('category');
        $search = $request->input('search', '');
        $order = $request->input('order', '');
        $breadcrumbParent = null;
        $breadcrumbChild = null;

        if ($categoryId) {
            $currentCategory = Category::find((int) $categoryId);
            if ($currentCategory) {
                $builder->whereIn('category_id', $currentCategory->selfAndDescendantIds());

                if ($currentCategory->parent_id) {
                    $breadcrumbParent = Category::find((int) $currentCategory->parent_id);
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
        } else {
            $builder->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
        }

        $products = $builder->paginate(16);

        return view('products.index', [
            'products' => $products,
            'procurementOrders' => collect(),
            'demoModeEnabled' => $this->demoModeEnabled(),
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
            if ($product->category->parent_id) {
                $breadcrumbParent = Category::find((int) $product->category->parent_id);
                $breadcrumbChild = $product->category;
            } else {
                $breadcrumbParent = $product->category;
            }
        }
        
        // 最后别忘了注入到模板中
        return view('products.show', [
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

        return view('products.favorites', ['products' => $products]);
    }

    public function procurementDetail(Request $request)
    {
        $orderId = (int) $request->input('id', $request->input('procurement_order_id', 0));
        if ($orderId > 0 && Schema::hasTable('procurement_orders')) {
            $order = ProcurementOrder::query()->find($orderId);
            if ($order) {
                return redirect()->route('procurement.show', ['procurementOrder' => $order->id]);
            }
        }

        $itemName = trim((string) $request->input('item_name', '未命名求购'));
        $itemImage = trim((string) $request->input('item_image', ''));
        $budgetAmount = (float) $request->input('budget_amount', 0);
        $narrative = trim((string) $request->input('narrative', ''));
        $nativeRequest = (bool) $request->input('native_request', false);

        if (Schema::hasTable('procurement_orders') && $itemName !== '' && $budgetAmount > 0) {
            $matchedOrderQuery = ProcurementOrder::query()
                ->where('item_name', $itemName)
                ->where('budget_amount', $budgetAmount);

            if ($narrative !== '') {
                $matchedOrderQuery->where('order_narrative', $narrative);
            }

            $matchedOrder = $matchedOrderQuery->orderBy('created_at', 'desc')->first();
            if ($matchedOrder) {
                return redirect()->route('procurement.show', ['procurementOrder' => $matchedOrder->id]);
            }
        }

        if ($nativeRequest) {
            $recommendedProducts = collect();
            $matchedProduct = null;
            $quickPayProduct = null;
        } else {
            $recommendedProducts = $this->findRecommendedProducts($itemName);
            $matchedProduct = $recommendedProducts->first();
            $quickPayProduct = $matchedProduct ?: $recommendedProducts->first();
        }

        $procurementMeta = [
            'item_name' => $itemName,
            'category' => $this->extractCategoryFromNarrative($narrative),
            'budget_amount' => $budgetAmount,
            'narrative' => $narrative,
            'has_match' => $matchedProduct !== null,
        ];

        return view('products.procurement_show', [
            'itemName' => $itemName,
            'itemImage' => $itemImage,
            'budgetAmount' => $budgetAmount,
            'narrative' => $narrative,
            'nativeRequest' => $nativeRequest,
            'procurementMeta' => $procurementMeta,
            'matchedProduct' => $matchedProduct,
            'recommendedProducts' => $recommendedProducts,
            'quickPayProduct' => $quickPayProduct,
        ]);
    }

    public function procurementCheckout(Request $request)
    {
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

        return view('products.procurement_checkout', [
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

        return view('products.procurement_agreement', [
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

        ProcurementOrder::query()->create([
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
                'category_id' => (int) $validated['category_id'],
                'category_name' => (string) data_get($category, 'name', ''),
                'order_id' => null,
                'budget_equals_item_amount' => true,
                'no_sku' => true,
                'requested_amount' => (float) $validated['budget_amount'],
                'item_amount' => (float) $validated['budget_amount'],
            ],
        ]);

        return redirect()->route('products.index')->with('success', '求购委托已发布，正在等待代购人响应。');
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

    protected function buildProcurementHallIndexViewData()
    {
        $procurementOrders = ProcurementOrder::query()
            ->orderBy('created_at', 'desc')
            ->limit(42)
            ->get();

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

        if ($referenceSeeds->isNotEmpty()) {
            $procurementOrders = $procurementOrders->concat($referenceSeeds)->take(48)->values();
        }

        $procurementOrders = $procurementOrders->map(function ($order) {
            $itemName = trim((string) data_get($order, 'item_name', ''));
            $order->buy_url = $this->resolveBuyUrl($order, $itemName);

            return $order;
        });

        return [
            'products' => new LengthAwarePaginator([], 0, 16),
            'procurementOrders' => $procurementOrders,
            'demoModeEnabled' => $this->demoModeEnabled(),
            'breadcrumbParent' => null,
            'breadcrumbChild' => null,
            'filters' => [
                'category' => null,
                'search' => '',
                'order' => '',
            ],
        ];
    }

    protected function demoModeEnabled()
    {
        return in_array(strtolower((string) env('DEMO_MODE', 'false')), ['1', 'true', 'yes', 'on'], true);
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

        if ($order instanceof ProcurementOrder && $order->id) {
            return route('procurement.show', ['procurementOrder' => $order->id]);
        }

        if (is_object($order) && isset($order->id) && $order->id) {
            return route('procurement.show', ['procurementOrder' => $order->id]);
        }

        if (data_get($order, 'extra.is_reference_seed', false)) {
            return route('procurement.detail', [
                'item_name' => $itemName,
                'item_image' => (string) data_get($order, 'item_image', ''),
                'budget_amount' => (float) data_get($order, 'budget_amount', 0),
                'narrative' => (string) data_get($order, 'order_narrative', ''),
                'native_request' => 1,
            ]);
        }

        if (data_get($order, 'extra.is_user_generated', false)) {
            return route('procurement.detail', [
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

        if (is_object($order) && isset($order->id) && $order->id) {
            return route('procurement.show', ['procurementOrder' => $order->id]);
        }

        return route('procurement.detail', [
            'item_name' => $itemName,
            'item_image' => (string) data_get($order, 'item_image', ''),
            'budget_amount' => (float) data_get($order, 'budget_amount', 0),
            'narrative' => (string) data_get($order, 'order_narrative', ''),
            'native_request' => 1,
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
