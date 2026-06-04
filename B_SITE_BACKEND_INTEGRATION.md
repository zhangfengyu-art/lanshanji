# 🎯 B站后台数据联动架构文档

## 📊 系统概述

B站（岚山跨境求购大厅）与后台的联动采用 **MVC三层架构 + 数据库驱动模式**。

```
HTTP Request (GET /products?search=xx&budget=xx&status=xx)
    ↓
ProductsController::index()
    ├─ 查询 ProcurementOrder 表
    ├─ 应用搜索、预算、状态过滤
    └─ 返回视图 + 模拟数据
    ↓
resources/views/products/index.blade.php
    ├─ 渲染求购单Feed
    ├─ 显示统计卡片
    └─ 提供交互式过滤表单
    ↓
HTML + CSS + JavaScript (浏览器)
    └─ 用户交互：搜索、过滤、点击接单
```

---

## 🔄 核心数据流

### 1️⃣ 后端查询逻辑（ProductsController::index）

**位置**：`app/Http/Controllers/ProductsController.php` (第19-130行)

```php
// 接收所有过滤参数
$search = $request->input('search', '');           // 搜索关键词
$statusFilter = (string) $request->input('status', 'all');  // 状态：all/pending/accepted/sourcing
$budgetFilter = (string) $request->input('budget', '');     // 预算：0-500/500-2000/2000-5000/5000-10000/10000+

// 构建查询
$procurementOrdersQuery = ProcurementOrder::query()->orderBy('created_at', 'desc');

// 应用搜索过滤 - 在item_name或order_narrative中搜索
if ($search) {
    $procurementOrdersQuery->where(function ($q) use ($like) {
        $q->where('item_name', 'like', $like)      // 求购标题
          ->orWhere('order_narrative', 'like', $like); // 求购描述
    });
}

// 应用状态过滤
if ($statusFilter !== 'all') {
    $statusMap = [
        'pending' => 0,   // 待接单
        'accepted' => 1,  // 已接单
        'sourcing' => 2,  // 采购中
    ];
    $procurementOrdersQuery->where('proxy_status', $statusMap[$statusFilter]);
}

// 应用预算过滤 - 按budget_amount字段范围筛选
if ($budgetFilter) {
    switch ($budgetFilter) {
        case '0-500':
            $procurementOrdersQuery->whereBetween('budget_amount', [0, 500]);
            break;
        case '500-2000':
            $procurementOrdersQuery->whereBetween('budget_amount', [500, 2000]);
            break;
        case '2000-5000':
            $procurementOrdersQuery->whereBetween('budget_amount', [2000, 5000]);
            break;
        case '5000-10000':
            $procurementOrdersQuery->whereBetween('budget_amount', [5000, 10000]);
            break;
        case '10000+':
            $procurementOrdersQuery->where('budget_amount', '>=', 10000);
            break;
    }
}

$procurementOrders = $procurementOrdersQuery->limit(42)->get();
```

---

### 2️⃣ 数据库模型

**表**：`procurement_orders`

| 字段 | 类型 | 说明 | 示例 |
|------|------|------|------|
| id | int | 主键 | 1 |
| no | varchar | 求购单号 | PO20260516001234 |
| user_id | int | 发布人ID | 123 |
| item_name | varchar | 求购标题 | 急购日本Peace铁盒50支装 |
| item_image | varchar | 示意图URL | https://via.placeholder.com/... |
| buyer_nickname | varchar | 采购人昵称 | 李*云 |
| proxy_status | int | 状态 (0=待接单, 1=已接单, 2=采购中) | 0 |
| order_narrative | longtext | 求购描述 | 日本免税店正品，需保证原装 |
| **budget_amount** | decimal(10,2) | **预算金额（关键！）** | **2800.00** |
| extra | json | 扩展字段 | {"is_demo_data": true} |
| created_at | timestamp | 发布时间 | 2026-05-16 10:30:00 |
| updated_at | timestamp | 更新时间 | 2026-05-16 10:30:00 |

**模型**：`app/Models/ProcurementOrder.php`

```php
class ProcurementOrder extends Model
{
    const STATUS_PENDING = 0;    // 待接单
    const STATUS_ACCEPTED = 1;   // 已接单
    const STATUS_SOURCING = 2;   // 采购中
    
    protected $fillable = [
        'no', 'order_no', 'user_id', 'item_name', 'item_image',
        'buyer_nickname', 'proxy_status', 'order_narrative',
        'budget_amount', 'extra'
    ];
    
    protected $casts = [
        'proxy_status' => 'integer',
        'budget_amount' => 'float',
        'extra' => 'json',
    ];
}
```

---

### 3️⃣ 前端表单与参数

**位置**：`resources/views/products/index.blade.php` (第965-985行)

#### 搜索条吸顶表单
```html
<form class="b-hall-search" action="{{ route('products.index') }}" method="GET">
  <!-- 搜索输入框 -->
  <input type="text" name="search" value="{{ request('search') }}" placeholder="按商品名或描述检索">
  
  <!-- 预算下拉框 -->
  <select name="budget" id="budget_range">
    <option value="">全部预算</option>
    <option value="0-500">500元以下</option>
    <option value="500-2000">500-2000元</option>
    <option value="2000-5000">2000-5000元</option>
    <option value="5000-10000">5000-10000元</option>
    <option value="10000+">10000元以上</option>
  </select>
  
  <!-- 隐藏状态参数（保持已选的状态） -->
  <input type="hidden" name="status" value="{{ $statusFilter }}">
  
  <!-- 提交按钮 -->
  <button type="submit">搜索需求</button>
</form>
```

#### 状态筛选Tab
```html
<div class="b-status-tabs" aria-label="状态筛选">
  <a class="b-status-tab {{ $statusFilter === 'all' ? 'is-active' : '' }}"
     href="{{ route('products.index', ['status' => 'all', 'search' => request('search'), 'budget' => request('budget')]) }}">
    全部
  </a>
  <a class="b-status-tab {{ $statusFilter === 'pending' ? 'is-active' : '' }}"
     href="{{ route('products.index', ['status' => 'pending', 'search' => request('search'), 'budget' => request('budget')]) }}">
    待接单
  </a>
  <a class="b-status-tab {{ $statusFilter === 'accepted' ? 'is-active' : '' }}"
     href="{{ route('products.index', ['status' => 'accepted', 'search' => request('search'), 'budget' => request('budget')]) }}">
    已接单
  </a>
  <a class="b-status-tab {{ $statusFilter === 'sourcing' ? 'is-active' : '' }}"
     href="{{ route('products.index', ['status' => 'sourcing', 'search' => request('search'), 'budget' => request('budget')]) }}">
    采购中
  </a>
</div>
```

---

### 4️⃣ 数据渲染到页面

**求购单Feed卡片渲染**：

```blade
@foreach($procurementOrders as $order)
  <div class="b-feed-item">
    <!-- 左侧：示意图 -->
    <div class="b-feed-item__image">
      <i class="fa fa-shopping-bag"></i>
    </div>
    
    <!-- 中间内容 -->
    <div class="b-feed-item__content">
      <!-- 标题 + 状态 -->
      <h4 class="b-feed-item__title">{{ $order->item_name }}</h4>
      <span class="b-feed-item__status {{ $order->proxy_status === 0 ? 'pending' : 'sourcing' }}">
        {{ $order->proxy_status === 0 ? '待接单' : '采购中' }}
      </span>
      
      <!-- 采购人 + 时间 -->
      <span>采购人 {{ $order->buyer_nickname }}</span>
      <span>{{ $order->created_at->diffForHumans() }}</span>
      
      <!-- 标签 -->
      <div class="b-feed-item__tags">
        <span class="b-feed-item__tag">EMS直邮</span>
        <span class="b-feed-item__tag">极速结款</span>
      </div>
      
      <!-- 底部：预算 + 接单按钮 -->
      <div class="b-feed-item__foot">
        <div class="b-feed-item__budget">
          ¥{{ number_format($order->budget_amount) }}
        </div>
        <button class="b-feed-item__action" onclick="acceptOrder({{ $order->id }})">
          我能带（接单）
        </button>
      </div>
    </div>
  </div>
@endforeach
```

**统计卡片数据渲染**：

```blade
@php
  // Controller计算统计数据
  $totalCount = $allHallOrders->count();  // 总求购单数
  $openCount = $allHallOrders->filter(function ($order) {
    return $order->proxy_status === 0; // 待接单数量
  })->count();
  $recentCount = $allHallOrders->filter(function ($order) {
    return $order->created_at >= now()->subDays(7); // 7日新增数量
  })->count();
@endphp

<div class="b-stat">
  <div class="b-stat__value" data-stat-value="{{ $totalCount }}">
    {{ $totalCount > 0 ? $totalCount : '10+' }}
  </div>
</div>
```

---

## 🚀 请求生命周期示例

### 例：用户搜索"日本" + 过滤预算2000-5000 + 查看待接单

1. **用户操作**：
   - 在搜索框输入"日本"
   - 在预算下拉框选择"2000-5000元"
   - 点击"待接单"状态Tab
   - 点击"搜索需求"按钮

2. **URL生成**：
   ```
   GET /products?search=日本&budget=2000-5000&status=pending
   ```

3. **后端处理**（ProductsController::index）：
   ```php
   $search = '日本';
   $budgetFilter = '2000-5000';
   $statusFilter = 'pending';
   
   // 构建查询
   $query = ProcurementOrder::query()
       ->where(function ($q) {
           $q->where('item_name', 'like', '%日本%')
             ->orWhere('order_narrative', 'like', '%日本%');
       })
       ->whereBetween('budget_amount', [2000, 5000])
       ->where('proxy_status', 0)  // 待接单
       ->orderBy('created_at', 'desc')
       ->limit(42);
   
   $procurementOrders = $query->get();
   ```

4. **数据库查询结果**：
   返回最多42条满足条件的求购单记录

5. **视图渲染**：
   将结果集传给Blade模板，逐条渲染为Feed卡片

6. **浏览器显示**：
   - 搜索框显示"日本"
   - 预算下拉框显示"2000-5000元"已选
   - "待接单"Tab高亮激活
   - Feed区域显示过滤后的求购单列表

---

## 🔌 扩展点：接入真实数据

### 当前状态
- ✅ 模拟数据：Blade视图中硬编码的5条演示单据
- ✅ 路由就绪：`GET /products` 已配置
- ✅ 过滤逻辑：Controller已实现搜索/预算/状态过滤
- ✅ 参数传递：Form和Tab链接已正确传递参数

### 启用真实数据步骤

1. **在表中插入测试数据**：
   ```sql
   INSERT INTO procurement_orders 
   (user_id, item_name, item_image, buyer_nickname, proxy_status, order_narrative, budget_amount, created_at)
   VALUES
   (1, '急购日本Peace烟', 'https://...', '李*云', 0, '原装正品', 2800, NOW()),
   (2, '日本进口烟纸', 'https://...', '王*强', 2, '长期合作', 5500, NOW()),
   ...
   ```

2. **视图自动过滤**：无需改动，Controller会自动使用真实数据替代演示数据

3. **数据库约束**：确保`budget_amount`字段填充正确值，支持小数点

---

## 📊 查看系统状态

**检查过滤是否工作**：

打开浏览器开发者工具，点击Network标签，执行查询：
```
http://127.0.0.1:8001/products?search=日本&budget=2000-5000&status=pending
```

观察：
- URL参数是否正确传递
- 预算下拉框是否显示正确选项
- 状态Tab是否高亮显示

---

## 🔐 安全性与性能

### 输入验证
- ✅ `$budgetFilter` 使用白名单验证（switch语句）
- ✅ `$search` 使用参数化查询（Eloquent的`where`）
- ✅ `$statusFilter` 映射到常量防止SQL注入

### 性能优化
- ✅ 查询限制：`limit(42)` 防止大量数据加载
- ✅ 数据库索引：建议为`proxy_status`、`budget_amount`、`created_at`添加索引
- ✅ 缓存机制：可选加Redis缓存热门查询

### 推荐索引
```sql
-- 快速筛选
CREATE INDEX idx_procurement_status_created ON procurement_orders(proxy_status, created_at DESC);

-- 预算范围查询
CREATE INDEX idx_procurement_budget_amount ON procurement_orders(budget_amount, created_at DESC);

-- 搜索优化
CREATE INDEX idx_procurement_item_name ON procurement_orders(item_name);
```

---

## 📝 快速参考

| 组件 | 位置 | 功能 |
|------|------|------|
| **Controller** | `app/Http/Controllers/ProductsController.php` | 查询、过滤逻辑 |
| **Model** | `app/Models/ProcurementOrder.php` | 数据模型定义 |
| **View** | `resources/views/products/index.blade.php` | 页面渲染 |
| **Routes** | `routes/web.php` | 路由定义 |
| **Database** | `procurement_orders` 表 | 数据存储 |

---

## 🎯 下一步建议

1. **添加真实数据**：在数据库中插入真实的求购单
2. **接单功能**：实现"我能带（接单）"按钮的后端接口
3. **通知系统**：接单后发送消息通知发布者
4. **支付集成**：接入第三方支付（微信/支付宝）
5. **数据分析**：追踪热门商品、用户转化率等

---

## 💡 技术栈总结

- **后端框架**：Laravel 5.5
- **数据库**：MySQL（myshop_ab）
- **前端渲染**：Blade模板引擎
- **样式框架**：Bootstrap 3.4.1
- **交互**：jQuery + 原生JavaScript
- **前端构建**：Webpack via Laravel Mix

---

