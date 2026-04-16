<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CourierApplication;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentSetting;
use App\Models\ProcurementOrder;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\SiteSetting;
use App\Models\SupportFeedback;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\ShadowOrder;
use App\Services\PaymentReturnTicketService;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FullSiteRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp()
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');

        $this->withoutMiddleware(\App\Http\Middleware\ForceDevSitePort::class);

        $this->ensureSiteSettings();
        $this->setSiteMode('A');

        config([
            'site.shadow_order_sign_secret' => 'test-shadow-secret',
            'site.payment_return_sign_secret' => 'test-payment-ticket-secret',
            'site.shadow_order_allowed_merchants' => ['site-a'],
        ]);
    }

    public function test_public_pages_and_product_pages_are_accessible()
    {
        list($product) = $this->createProductWithSku('A', 120.00, 20);

        $this->get('/products')->assertStatus(200);
        $this->get('/products/' . $product->id)->assertStatus(200);

        $this->get('/help/order-flow')->assertStatus(200);
        $this->get('/help/change-exchange-return')->assertStatus(200);
        $this->get('/help/faq')->assertStatus(200);
        $this->get('/b/help/faq-guidelines')->assertStatus(200);

        $this->get('/procurement/detail?item_name=test')->assertStatus(200);
    }

    public function test_guest_is_redirected_for_protected_routes()
    {
        $this->get('/cart')->assertStatus(302);
        $this->get('/orders')->assertStatus(302);
        $this->get('/support/feedbacks/create')->assertStatus(302);
        $this->get('/procurement/create')->assertStatus(302);
    }

    public function test_user_address_crud_flow_works()
    {
        $user = $this->createUser(true);

        $this->actingAs($user)
            ->post('/user_addresses', [
                'province' => '北京市',
                'city' => '北京市',
                'district' => '朝阳区',
                'address' => '建国路88号A座',
                'zip' => '100022',
                'contact_name' => '测试用户',
                'contact_phone' => '13800138000',
                'id_card' => '11010519491231002X',
                'is_default' => '1',
            ])
            ->assertStatus(302);

        $address = UserAddress::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($address);

        $this->actingAs($user)
            ->put('/user_addresses/' . $address->id, [
                'province' => '上海市',
                'city' => '上海市',
                'district' => '黄浦区',
                'address' => '南京东路100号',
                'zip' => '200001',
                'contact_name' => '新联系人',
                'contact_phone' => '13900139000',
                'id_card' => '11010519491231002X',
                'is_default' => '1',
            ])
            ->assertStatus(302);

        $address->refresh();
        $this->assertEquals('新联系人', $address->contact_name);

        $this->actingAs($user)
            ->delete('/user_addresses/' . $address->id)
            ->assertStatus(200);

        $this->assertNull(UserAddress::query()->find($address->id));
    }

    public function test_favorite_and_cart_flow_works()
    {
        $user = $this->createUser(true);
        list($product, $sku) = $this->createProductWithSku('A', 88.00, 50);

        $this->actingAs($user)
            ->post('/products/' . $product->id . '/favorite')
            ->assertStatus(200);

        $this->actingAs($user)
            ->get('/products/favorites')
            ->assertStatus(200)
            ->assertSee($product->title);

        $this->actingAs($user)
            ->post('/cart', [
                'sku_id' => $sku->id,
                'amount' => 2,
            ])
            ->assertStatus(200);

        $this->actingAs($user)
            ->get('/cart/summary')
            ->assertStatus(200)
            ->assertJsonFragment(['count' => 2]);

        $this->actingAs($user)
            ->patch('/cart/' . $sku->id, [
                'sku_id' => $sku->id,
                'amount' => 3,
            ])
            ->assertStatus(200);

        $this->actingAs($user)
            ->delete('/cart/' . $sku->id)
            ->assertStatus(200);

        $this->assertNull(DB::table('cart_items')->where('user_id', $user->id)->where('product_sku_id', $sku->id)->first());
    }

    public function test_standard_order_creation_calculates_fee_details_and_stock()
    {
        $this->setSiteMode('A');

        $user = $this->createUser(true);
        $address = $this->createAddress($user);
        list($product, $sku) = $this->createProductWithSku('A', 100.00, 20);

        $response = $this->actingAs($user)
            ->post('/orders', [
                'address_id' => $address->id,
                'remark' => '标准下单测试',
                'items' => [
                    ['sku_id' => $sku->id, 'amount' => 2],
                ],
            ]);

        $response->assertStatus(200)->assertJsonStructure(['id']);

        $orderId = data_get($response->json(), 'id');
        $order = Order::query()->find($orderId);

        $this->assertNotNull($order);
        $this->assertEquals(1976.00, (float) $order->total_amount);
        $this->assertEquals(200.00, (float) data_get($order->extra, 'fee_details.base_amount'));
        $this->assertEquals(26.00, (float) data_get($order->extra, 'fee_details.service_fee'));
        $this->assertEquals(300.00, (float) data_get($order->extra, 'fee_details.packaging_fee'));
        $this->assertEquals(1450.00, (float) data_get($order->extra, 'fee_details.ems_shipping_fee'));

        $sku->refresh();
        $this->assertEquals(18, (int) $sku->stock);
    }

    public function test_order_info_update_and_swap_item_in_a_mode()
    {
        $this->setSiteMode('A');

        $user = $this->createUser(true);
        list($product1, $sku1) = $this->createProductWithSku('A', 120.00, 20, ['title' => '原商品']);
        $swapSkuCode = 'ALT-SKU-' . strtoupper(substr(uniqid(), -8));
        list($product2, $sku2) = $this->createProductWithSku('A', 120.00, 20, ['title' => '替换商品'], ['title' => $swapSkuCode]);

        $order = $this->createOrder($user, $sku1, 2, true, Order::SHIP_STATUS_PENDING);
        $item = $order->items()->first();

        $this->actingAs($user)
            ->patch('/orders/' . $order->id . '/info', [
                'address' => '上海市黄浦区南京东路100号',
                'zip' => '200001',
                'contact_name' => '测试收件人',
                'contact_phone' => '13900139000',
                'remark' => '请工作日配送',
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['msg' => '订单信息已更新']);

        $this->actingAs($user)
            ->patch('/orders/' . $order->id . '/items/' . $item->id . '/swap', [
                'sku_code' => $swapSkuCode,
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['msg' => '商品已调换']);

        $item->refresh();
        $sku1->refresh();
        $sku2->refresh();

        $this->assertEquals((int) $sku2->id, (int) $item->product_sku_id);
        $this->assertEquals(22, (int) $sku1->stock);
        $this->assertEquals(18, (int) $sku2->stock);
    }

    public function test_received_refund_and_review_flow()
    {
        $user = $this->createUser(true);
        list($product, $sku) = $this->createProductWithSku('A', 90.00, 30);

        $orderDelivered = $this->createOrder($user, $sku, 1, true, Order::SHIP_STATUS_DELIVERED);
        $this->actingAs($user)
            ->post('/orders/' . $orderDelivered->id . '/received')
            ->assertStatus(200);
        $orderDelivered->refresh();
        $this->assertEquals(Order::SHIP_STATUS_RECEIVED, $orderDelivered->ship_status);

        $orderRefund = $this->createOrder($user, $sku, 1, true, Order::SHIP_STATUS_PENDING, [
            'acceptance' => ['status' => Order::ACCEPTANCE_STATUS_PENDING],
        ]);
        $this->actingAs($user)
            ->post('/orders/' . $orderRefund->id . '/apply_refund', ['reason' => '行程变更，申请退款'])
            ->assertStatus(200);
        $orderRefund->refresh();
        $this->assertEquals(Order::REFUND_STATUS_APPLIED, $orderRefund->refund_status);

        $orderReview = $this->createOrder($user, $sku, 1, true, Order::SHIP_STATUS_PENDING);
        $reviewItem = $orderReview->items()->first();

        $this->actingAs($user)
            ->get('/orders/' . $orderReview->id . '/review')
            ->assertStatus(200);

        $this->actingAs($user)
            ->post('/orders/' . $orderReview->id . '/review', [
                'reviews' => [
                    [
                        'id' => $reviewItem->id,
                        'rating' => 5,
                        'review' => '非常满意，物流很快，包装完整。',
                    ],
                ],
            ])
            ->assertStatus(302);

        $orderReview->refresh();
        $reviewItem->refresh();
        $this->assertTrue((bool) $orderReview->reviewed);
        $this->assertEquals(5, (int) $reviewItem->rating);
    }

    public function test_fulfillment_photo_access_control()
    {
        $owner = $this->createUser(true);
        $other = $this->createUser(true, ['email' => 'other_' . uniqid() . '@example.com']);
        list($product, $sku) = $this->createProductWithSku('A', 100.00, 10);

        $order = $this->createOrder($owner, $sku, 1, true, Order::SHIP_STATUS_PENDING);
        Storage::disk('private')->put('orders/' . $order->no . '/photo.jpg', 'fake-image-content');
        $order->update(['fulfillment_photo' => 'orders/' . $order->no . '/photo.jpg']);

        $this->actingAs($owner)
            ->get('/orders/' . $order->no . '/fulfillment-photo')
            ->assertStatus(200);

        $this->actingAs($other)
            ->get('/orders/' . $order->no . '/fulfillment-photo')
            ->assertStatus(403);
    }

    public function test_support_feedback_submission_and_limit_policy()
    {
        $user = $this->createUser(true);
        list($product, $sku) = $this->createProductWithSku('A', 76.00, 10);
        $order = $this->createOrder($user, $sku, 1, true, Order::SHIP_STATUS_PENDING);

        $this->actingAs($user)
            ->get('/support/feedbacks/create?order_no=' . urlencode($order->no) . '&locked=1')
            ->assertStatus(200);

        $this->actingAs($user)
            ->post('/support/feedbacks', [
                'order_no' => $order->no,
                'question_type' => 'ORDER_DELIVERY',
                'message' => '这里是一条用于提交的客服问题描述，长度超过十个字符。',
                'locked_order_no' => '1',
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('support_feedbacks', [
            'user_id' => $user->id,
            'order_no' => $order->no,
        ]);

        for ($i = 0; $i < 3; $i++) {
            SupportFeedback::query()->create([
                'user_id' => $user->id,
                'order_no' => $order->no,
                'contact_name' => '测试用户',
                'contact_phone' => '13800138000',
                'question_type' => 'OTHER',
                'message' => 'pending-' . $i . '-问题描述足够长',
                'images' => [],
                'status' => SupportFeedback::STATUS_PENDING_REVIEW,
            ]);
        }

        $this->actingAs($user)
            ->postJson('/support/feedbacks', [
                'order_no' => $order->no,
                'question_type' => 'OTHER',
                'message' => '再次提交触发限流，这段文字长度已经满足要求。',
            ])
            ->assertStatus(400)
            ->assertJsonFragment(['msg' => '当前仍有4条问题待处理，请等待客服回复后再继续提交。']);
    }

    public function test_b_mode_procurement_publish_checkout_and_accept_flow()
    {
        $this->setSiteMode('B');
        $this->assertTrue(is_site_mode_b());
        $this->withServerVariables(['SERVER_PORT' => 80, 'HTTP_HOST' => '127.0.0.1']);

        $buyer = $this->createUser(true, ['email' => 'buyer_' . uniqid() . '@example.com']);
        $courier = $this->createUser(true, ['email' => 'courier_' . uniqid() . '@example.com']);

        $category = $this->createCategory('B', ['name' => 'B站数码']);
        $this->createAddress($buyer);

        $postResponse = $this->actingAs($buyer)
            ->post('/procurement/store', [
                'item_name' => '任天堂限定手柄',
                'category_id' => $category->id,
                'budget_amount' => 28000,
                'order_narrative' => '希望九成新以上，盒子完整，尽快发货。',
            ]);

        $postResponse->assertStatus(302);

        $storeErrors = [];
        if (method_exists($postResponse->baseResponse, 'getSession')) {
            $errorBag = $postResponse->baseResponse->getSession()->get('errors');
            if ($errorBag) {
                $storeErrors = $errorBag->all();
            }
        }

        $procurementOrder = ProcurementOrder::query()
            ->where('user_id', $buyer->id)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull(
            $procurementOrder,
            'procurement store location=' . (string) $postResponse->headers->get('Location') . ', errors=' . json_encode($storeErrors, JSON_UNESCAPED_UNICODE)
        );

        $this->actingAs($buyer)
            ->get('/procurement/checkout?procurement_order_id=' . $procurementOrder->id . '&native_request=1')
            ->assertStatus(200);

        $this->actingAs($buyer)
            ->get('/procurement/detail?procurement_order_id=' . $procurementOrder->id . '&item_name=' . urlencode($procurementOrder->item_name) . '&budget_amount=' . $procurementOrder->budget_amount . '&native_request=1')
            ->assertStatus(200);

        CourierApplication::query()->create([
            'user_id' => $courier->id,
            'real_name' => '代购员A',
            'phone' => '13800138000',
            'id_card_number' => '11010519491231002X',
            'flight_ticket_path' => 'courier_applications/flight_tickets/a.jpg',
            'id_card_photo_path' => 'courier_applications/id_cards/a.jpg',
            'status' => CourierApplication::STATUS_APPROVED,
            'admin_note' => '审核通过',
        ]);

        $this->actingAs($courier)
            ->get('/procurement/accept?procurement_order_id=' . $procurementOrder->id)
            ->assertStatus(302);

        $procurementOrder->refresh();
        $this->assertEquals((int) $courier->id, (int) $procurementOrder->accepted_by);
        $this->assertEquals(ProcurementOrder::STATUS_ACCEPTED, (int) $procurementOrder->proxy_status);
    }

    public function test_courier_application_submission_works_in_b_mode()
    {
        $this->setSiteMode('B');
        $this->assertTrue(is_site_mode_b());
        $this->withServerVariables(['SERVER_PORT' => 80, 'HTTP_HOST' => '127.0.0.1']);

        $user = $this->createUser(true);

        $applyResponse = $this->actingAs($user)
            ->post('/procurement/apply', [
                'real_name' => '代购申请人',
                'phone' => '13800138000',
                'id_card_number' => '11010519491231002X',
                'flight_ticket' => UploadedFile::fake()->image('ticket.jpg'),
                'id_card_photo' => UploadedFile::fake()->image('id.jpg'),
            ]);

        $applyResponse->assertStatus(302);

        $applyErrors = [];
        if (method_exists($applyResponse->baseResponse, 'getSession')) {
            $errorBag = $applyResponse->baseResponse->getSession()->get('errors');
            if ($errorBag) {
                $applyErrors = $errorBag->all();
            }
        }

        $createdApplication = CourierApplication::query()
            ->where('user_id', $user->id)
            ->where('status', CourierApplication::STATUS_PENDING)
            ->first();

        $this->assertNotNull(
            $createdApplication,
            'procurement apply location=' . (string) $applyResponse->headers->get('Location') . ', errors=' . json_encode($applyErrors, JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_payment_return_ticket_pages_and_status()
    {
        $user = $this->createUser(true);
        list($product, $sku) = $this->createProductWithSku('A', 156.00, 12);
        $order = $this->createOrder($user, $sku, 1, true, Order::SHIP_STATUS_PENDING);

        $ticket = app(PaymentReturnTicketService::class)->make([
            'order_no' => $order->no,
            'origin' => 'B',
            'nonce' => bin2hex(random_bytes(8)),
            'iat' => time(),
            'exp' => time() + 300,
            'return_path' => '/payment/return',
        ]);

        $this->actingAs($user)
            ->get('/payment/return?ticket=' . urlencode($ticket))
            ->assertStatus(200)
            ->assertSee($order->no);

        $this->actingAs($user)
            ->get('/payment/return/status?ticket=' . urlencode($ticket))
            ->assertStatus(200)
            ->assertJsonFragment(['order_no' => $order->no]);

        $this->actingAs($user)
            ->get('/payment/return?ticket=invalid-ticket')
            ->assertStatus(403);
    }

    public function test_api_create_shadow_order_signature_and_idempotency()
    {
        $payload = [
            'merchant_id' => 'site-a',
            'order_no' => 'A-ORDER-' . uniqid(),
            'amount_minor' => 12345,
            'currency' => 'JPY',
            'channel' => 'alipay',
            'source_site' => 'A',
            'return_path' => '/orders',
            'ts' => time(),
            'nonce' => 'nonce-' . uniqid(),
        ];

        $bodyForHash = $payload;
        ksort($bodyForHash);
        $payload['body_sha256'] = hash('sha256', json_encode($bodyForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $canonical = implode('|', [
            $payload['merchant_id'],
            $payload['order_no'],
            $payload['amount_minor'],
            $payload['ts'],
            $payload['nonce'],
            $payload['body_sha256'],
        ]);
        $sign = base64_encode(hash_hmac('sha256', $canonical, (string) config('site.shadow_order_sign_secret'), true));

        $first = $this->postJson('/api/v1/create-shadow-order', array_merge($payload, ['sign' => $sign]), [
            'X-Sign' => $sign,
        ]);

        $first->assertStatus(201)
            ->assertJsonStructure(['shadow_no', 'status']);

        $second = $this->postJson('/api/v1/create-shadow-order', array_merge($payload, ['sign' => $sign]), [
            'X-Sign' => $sign,
        ]);

        $second->assertStatus(200)
            ->assertJsonFragment(['status' => 'pending']);
    }

    public function test_api_payment_webhook_paid_updates_order()
    {
        Redis::shouldReceive('setnx')->andReturn(true);
        Redis::shouldReceive('expire')->andReturn(true);

        $user = $this->createUser(true);
        list($product, $sku) = $this->createProductWithSku('A', 200.00, 10);
        $order = $this->createOrder($user, $sku, 1, false, Order::SHIP_STATUS_PENDING);

        $payload = [
            'merchant_id' => 'site-a',
            'order_no' => $order->no,
            'payment_no' => 'PAY-' . uniqid(),
            'amount_minor' => (int) round(((float) $order->total_amount) * 100),
            'status' => 'paid',
            'paid_at' => now()->toDateTimeString(),
            'ts' => time(),
            'nonce' => 'webhook-' . uniqid(),
        ];

        $bodyForHash = $payload;
        ksort($bodyForHash);
        $payload['body_sha256'] = hash('sha256', json_encode($bodyForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $canonical = implode('|', [
            $payload['merchant_id'],
            $payload['order_no'],
            $payload['payment_no'],
            $payload['amount_minor'],
            $payload['status'],
            $payload['paid_at'],
            $payload['ts'],
            $payload['nonce'],
            $payload['body_sha256'],
        ]);
        $sign = base64_encode(hash_hmac('sha256', $canonical, (string) config('site.shadow_order_sign_secret'), true));

        $this->postJson('/api/v1/payment/webhook/paid', array_merge($payload, ['sign' => $sign]), [
            'X-Sign' => $sign,
        ])->assertStatus(200)
            ->assertJsonFragment(['status' => 'paid']);

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertEquals('relay_webhook', $order->payment_method);
    }

    public function test_api_shadow_order_paid_webhook_updates_order()
    {
        $user = $this->createUser(true);
        list($product, $sku) = $this->createProductWithSku('A', 188.00, 10);
        $order = $this->createOrder($user, $sku, 1, false, Order::SHIP_STATUS_PENDING);

        $paidAt = now()->toDateTimeString();
        $timestamp = time();
        $totalAmount = number_format((float) $order->total_amount, 2, '.', '');
        $raw = implode('|', [$order->no, $totalAmount, $paidAt, 'paid', $timestamp]);
        $sign = hash_hmac('sha256', $raw, (string) config('site.shadow_order_sign_secret'));

        $this->postJson('/api/v1/shadow-order/paid', [
            'order_no' => $order->no,
            'total_amount' => $totalAmount,
            'paid_at' => $paidAt,
            'status' => 'paid',
            'timestamp' => $timestamp,
            'sign' => $sign,
        ])->assertStatus(200)
            ->assertJsonFragment(['status' => 'paid']);

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertEquals('shadow_webhook', $order->payment_method);
    }

    public function test_admin_user_and_order_actions_work()
    {
        $admin = $this->ensureAdminUser();
        $user = $this->createUser(true, ['email' => 'target_' . uniqid() . '@example.com']);
        list($product, $sku) = $this->createProductWithSku('A', 99.00, 20);
        $order = $this->createOrder($user, $sku, 1, true, Order::SHIP_STATUS_PENDING);

        $this->actingAs($admin, 'admin')
            ->get('/admin/users')
            ->assertStatus(200);

        $this->actingAs($admin, 'admin')
            ->post('/admin/users/' . $user->id . '/ban')
            ->assertStatus(302);
        $user->refresh();
        $this->assertFalse((bool) $user->is_enabled);

        $this->actingAs($admin, 'admin')
            ->post('/admin/users/' . $user->id . '/unban')
            ->assertStatus(302);
        $user->refresh();
        $this->assertTrue((bool) $user->is_enabled);

        $beforeVersion = (int) $user->session_version;
        $this->actingAs($admin, 'admin')
            ->post('/admin/users/' . $user->id . '/reset-session')
            ->assertStatus(302);
        $user->refresh();
        $this->assertEquals($beforeVersion + 1, (int) $user->session_version);

        $this->actingAs($admin, 'admin')
            ->post('/admin/orders/' . $order->id . '/acceptance', ['status' => Order::ACCEPTANCE_STATUS_ACCEPTED])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/orders/acceptance/batch', [
                'ids' => [$order->id],
                'status' => Order::ACCEPTANCE_STATUS_PENDING,
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true]);

        $this->actingAs($admin, 'admin')
            ->post('/admin/orders/' . $order->id . '/ship', [
                'express_company' => 'EMS',
                'express_no' => 'TRACK' . uniqid(),
            ])
            ->assertStatus(302);

        $order->refresh();
        $this->assertEquals(Order::SHIP_STATUS_DELIVERED, $order->ship_status);
        $this->assertNotEmpty($order->tracking_no);

        $this->actingAs($admin, 'admin')
            ->post('/admin/orders/' . $order->id . '/fulfillment', [
                'fulfillment_photo' => UploadedFile::fake()->image('fulfill.jpg'),
            ])
            ->assertStatus(302);

        $order->refresh();
        $this->assertNotEmpty($order->fulfillment_photo);
    }

    public function test_admin_support_feedback_and_procurement_review_flow()
    {
        $admin = $this->ensureAdminUser();
        $user = $this->createUser(true);
        list($product, $sku) = $this->createProductWithSku('B', 300.00, 10, ['is_from_native_procurement' => true]);
        $order = $this->createOrder($user, $sku, 1, true, Order::SHIP_STATUS_PENDING);

        $feedback = SupportFeedback::query()->create([
            'user_id' => $user->id,
            'order_no' => $order->no,
            'contact_name' => '测试用户',
            'contact_phone' => '13800138000',
            'question_type' => 'PAYMENT',
            'message' => '测试客服工单：支付后状态未更新，请协助处理。',
            'images' => [],
            'status' => SupportFeedback::STATUS_PENDING_REVIEW,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/support-feedbacks')
            ->assertStatus(200);

        $this->actingAs($admin, 'admin')
            ->put('/admin/support-feedbacks/' . $feedback->id, [
                'status' => SupportFeedback::STATUS_OFFICIALLY_RESOLVED,
                'admin_reply' => '已核实并同步状态，问题已解决。',
            ])
            ->assertStatus(302);

        $feedback->refresh();
        $this->assertEquals(SupportFeedback::STATUS_OFFICIALLY_RESOLVED, $feedback->status);
        $this->assertNotEmpty($feedback->admin_reply);

        $procurementOrder = ProcurementOrder::query()->create([
            'user_id' => $user->id,
            'item_name' => '测试原生求购单',
            'item_image' => '',
            'buyer_nickname' => '测试买家',
            'proxy_status' => ProcurementOrder::STATUS_PENDING,
            'order_narrative' => '用于后台审核流测试',
            'budget_amount' => 35000,
            'review_status' => ProcurementOrder::REVIEW_STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/procurement-orders/' . $procurementOrder->id . '/review')
            ->assertStatus(200);

        $this->actingAs($admin, 'admin')
            ->post('/admin/procurement-orders/' . $procurementOrder->id . '/submit-review', [
                'approved' => 1,
                'comment' => '审核通过',
            ])
            ->assertStatus(302);

        $procurementOrder->refresh();
        $this->assertEquals(ProcurementOrder::REVIEW_STATUS_APPROVED, (int) $procurementOrder->review_status);

        $this->actingAs($admin, 'admin')
            ->get('/admin/procurement-orders/' . $procurementOrder->id . '/quick-accept')
            ->assertStatus(302);

        $procurementOrder->refresh();
        $this->assertEquals(ProcurementOrder::STATUS_ACCEPTED, (int) $procurementOrder->proxy_status);
    }

    public function test_admin_site_and_payment_settings_update_routes()
    {
        $admin = $this->ensureAdminUser();

        $this->actingAs($admin, 'admin')
            ->get('/admin/site-settings/logo')
            ->assertStatus(200);

        $this->actingAs($admin, 'admin')
            ->post('/admin/site-settings/logo', [
                'brand_text_zh' => '岚山烟务所测试',
                'brand_text_en' => 'ARASHIYAMA TEST',
                'disable_email_verification_for_testing' => 1,
                'active_site_mode' => 'A',
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('site_settings', [
            'key' => 'header_brand_text_zh',
            'value' => '岚山烟务所测试',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/payment_settings')
            ->assertStatus(200);

        $this->actingAs($admin, 'admin')
            ->post('/admin/payment_settings', [
                'alipay_qr' => UploadedFile::fake()->image('alipay-qr.jpg'),
                'wechat_qr' => UploadedFile::fake()->image('wechat-qr.jpg'),
            ])
            ->assertStatus(302);

        $setting = PaymentSetting::query()->first();
        $this->assertNotNull($setting);
        $this->assertNotEmpty($setting->alipay_qr);
        $this->assertNotEmpty($setting->wechat_qr);
    }

    public function test_payment_entry_and_notify_paths_with_mocked_gateways()
    {
        $this->setSiteMode('B');
        $this->assertTrue(is_site_mode_b());
        $this->withServerVariables(['SERVER_PORT' => 80, 'HTTP_HOST' => '127.0.0.1']);

        $user = $this->createUser(true);
        list($product, $sku) = $this->createProductWithSku('B', 168.00, 10, ['is_from_native_procurement' => true]);
        $order = $this->createOrder($user, $sku, 1, false, Order::SHIP_STATUS_PENDING);

        $fakeAlipay = new class {
            public $notifyPayload;

            public function web($payload)
            {
                return response('ALIPAY_WEB_OK', 200);
            }

            public function verify()
            {
                return (object) [
                    'trade_status' => 'TRADE_SUCCESS',
                    'out_trade_no' => (string) data_get($this->notifyPayload, 'out_trade_no', ''),
                    'trade_no' => (string) data_get($this->notifyPayload, 'trade_no', ''),
                ];
            }

            public function success()
            {
                return 'success';
            }

            public function refund($payload)
            {
                return (object) ['sub_code' => null];
            }
        };

        $fakeWechat = new class {
            public $notifyPayload;

            public function scan($payload)
            {
                return (object) ['code_url' => 'https://example.com/qrcode'];
            }

            public function verify($arg1 = null, $arg2 = null)
            {
                if (is_array($this->notifyPayload)) {
                    return (object) $this->notifyPayload;
                }

                return (object) [
                    'out_trade_no' => '',
                    'transaction_id' => '',
                ];
            }

            public function success()
            {
                return 'success';
            }

            public function refund($payload)
            {
                return [];
            }
        };

        app()->instance('alipay', $fakeAlipay);
        app()->instance('wechat_pay', $fakeWechat);

        $alipayEntry = $this->followingRedirects()->actingAs($user)
            ->get('/payment/' . $order->id . '/alipay');

        $this->assertSame(200, $alipayEntry->getStatusCode());
        $alipayEntry->assertSee('ALIPAY_WEB_OK');

        $wechatEntry = $this->followingRedirects()->actingAs($user)
            ->get('/payment/' . $order->id . '/wechat');

        $this->assertSame(
            200,
            $wechatEntry->getStatusCode(),
            'wechat entry status=' . $wechatEntry->getStatusCode() . ', location=' . (string) $wechatEntry->headers->get('Location')
        );

        $orderForAlipayNotify = $this->createOrder($user, $sku, 1, false, Order::SHIP_STATUS_PENDING);
        $fakeAlipay->notifyPayload = [
            'out_trade_no' => $orderForAlipayNotify->no,
            'trade_no' => 'ALI-' . uniqid(),
        ];

        $this->post('/payment/alipay/notify', [])
            ->assertStatus(200)
            ->assertSee('success');

        $orderForAlipayNotify->refresh();
        $this->assertNotNull($orderForAlipayNotify->paid_at);
        $this->assertEquals('alipay', $orderForAlipayNotify->payment_method);

        $orderForWechatNotify = $this->createOrder($user, $sku, 1, false, Order::SHIP_STATUS_PENDING);
        $fakeWechat->notifyPayload = [
            'out_trade_no' => $orderForWechatNotify->no,
            'transaction_id' => 'WX-' . uniqid(),
        ];

        $this->post('/payment/wechat/notify', [])
            ->assertStatus(200)
            ->assertSee('success');

        $orderForWechatNotify->refresh();
        $this->assertNotNull($orderForWechatNotify->paid_at);
        $this->assertEquals('wechat', $orderForWechatNotify->payment_method);
    }

    protected function ensureSiteSettings()
    {
        SiteSetting::query()->firstOrCreate(['key' => 'active_site_mode'], ['value' => 'A']);
        SiteSetting::query()->firstOrCreate(['key' => 'disable_email_verification_for_testing'], ['value' => '0']);
        SiteSetting::query()->firstOrCreate(['key' => 'header_brand_text_zh'], ['value' => '岚山烟务所']);
        SiteSetting::query()->firstOrCreate(['key' => 'header_brand_text_en'], ['value' => 'ARASHIYAMA TOBACCO SHOP']);
        SiteSetting::query()->firstOrCreate(['key' => 'header_logo'], ['value' => '']);
    }

    protected function setSiteMode($mode)
    {
        $mode = strtoupper((string) $mode) === 'B' ? 'B' : 'A';

        putenv('SITE_MODE=' . $mode);
        $_ENV['SITE_MODE'] = $mode;
        $_SERVER['SITE_MODE'] = $mode;

        SiteSetting::query()->updateOrCreate([
            'key' => 'active_site_mode',
        ], [
            'value' => $mode,
        ]);

        config(['site.mode' => $mode]);
        Cache::forget('site.active_mode');
    }

    protected function createUser($emailVerified = true, array $overrides = [])
    {
        $name = data_get($overrides, 'name', '用户' . uniqid());
        $email = data_get($overrides, 'email', 'user_' . uniqid() . '@example.com');

        $data = [
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('secret123'),
            'email_verified' => $emailVerified ? 1 : 0,
            'is_enabled' => 1,
            'session_version' => 0,
        ];

        foreach ($overrides as $key => $value) {
            $data[$key] = $value;
        }

        return User::query()->create($data);
    }

    protected function createAddress(User $user, array $overrides = [])
    {
        $data = [
            'province' => '北京市',
            'city' => '北京市',
            'district' => '朝阳区',
            'address' => '建国路88号',
            'zip' => 100022,
            'contact_name' => '收件人',
            'contact_phone' => '13800138000',
            'id_card' => '11010519491231002X',
            'is_default' => 1,
            'last_used_at' => now(),
        ];

        foreach ($overrides as $key => $value) {
            $data[$key] = $value;
        }

        return $user->addresses()->create($data);
    }

    protected function createCategory($siteMode = 'A', array $overrides = [])
    {
        $data = [
            'name' => '分类-' . uniqid(),
            'is_directory' => 0,
            'parent_id' => null,
            'level' => 0,
        ];

        if (Schema::hasColumn('categories', 'site_mode')) {
            $data['site_mode'] = strtoupper((string) $siteMode) === 'B' ? 'B' : 'A';
        }

        foreach ($overrides as $key => $value) {
            $data[$key] = $value;
        }

        return Category::query()->create($data);
    }

    protected function createProductWithSku($siteMode = 'A', $price = 100.00, $stock = 10, array $productOverrides = [], array $skuOverrides = [])
    {
        $category = $this->createCategory($siteMode);

        $productData = [
            'title' => data_get($productOverrides, 'title', '商品-' . uniqid()),
            'description' => data_get($productOverrides, 'description', '用于自动化回归测试的商品'),
            'image' => data_get($productOverrides, 'image', 'images/test-product.jpg'),
            'on_sale' => data_get($productOverrides, 'on_sale', 1),
            'rating' => 5,
            'sold_count' => 0,
            'review_count' => 0,
            'price' => $price,
            'category_id' => $category->id,
        ];

        if (Schema::hasColumn('products', 'is_from_native_procurement')) {
            $productData['is_from_native_procurement'] = data_get(
                $productOverrides,
                'is_from_native_procurement',
                strtoupper((string) $siteMode) === 'B'
            ) ? 1 : 0;
            $productData['procurement_order_id'] = data_get($productOverrides, 'procurement_order_id', null);
        }

        $product = Product::query()->create($productData);

        $skuData = [
            'title' => data_get($skuOverrides, 'title', 'SKU-' . strtoupper(substr(uniqid(), -8))),
            'description' => data_get($skuOverrides, 'description', '测试SKU'),
            'price' => $price,
            'stock' => $stock,
            'shipping_weight_grams' => data_get($skuOverrides, 'shipping_weight_grams', 30),
            'limit_qty' => data_get($skuOverrides, 'limit_qty', 0),
            'item_type' => data_get($skuOverrides, 'item_type', 'cigarette'),
            'unit_sticks' => data_get($skuOverrides, 'unit_sticks', 20),
            'unit_weight' => data_get($skuOverrides, 'unit_weight', 0),
        ];

        $sku = $product->skus()->create($skuData);
        $product->update(['price' => $price]);

        return [$product, $sku, $category];
    }

    protected function createOrder(User $user, ProductSku $sku, $amount = 1, $paid = true, $shipStatus = Order::SHIP_STATUS_PENDING, array $extra = [])
    {
        $address = $this->createAddress($user, ['is_default' => 1]);

        $baseAmount = round(((float) $sku->price) * (int) $amount, 2);
        $serviceFee = round($baseAmount * 0.13, 2);
        $packagingFee = 300.00;
        $shippingFee = 1750.00;

        $order = new Order([
            'address' => [
                'address' => $address->full_address,
                'zip' => $address->zip,
                'contact_name' => $address->contact_name,
                'contact_phone' => $address->contact_phone,
                'id_card' => $address->id_card,
            ],
            'total_amount' => round($baseAmount + $serviceFee + $packagingFee + $shippingFee, 2),
            'remark' => '测试订单',
            'paid_at' => $paid ? Carbon::now() : null,
            'payment_method' => $paid ? 'alipay' : null,
            'payment_no' => $paid ? ('PAYNO-' . uniqid()) : null,
            'refund_status' => Order::REFUND_STATUS_PENDING,
            'closed' => 0,
            'reviewed' => 0,
            'ship_status' => $shipStatus,
            'ship_data' => $shipStatus === Order::SHIP_STATUS_PENDING ? null : [
                'express_company' => 'EMS',
                'express_no' => 'T' . uniqid(),
            ],
            'extra' => array_merge([
                'fee_details' => [
                    'base_amount' => $baseAmount,
                    'service_fee' => $serviceFee,
                    'packaging_fee' => $packagingFee,
                    'ems_shipping_fee' => $shippingFee,
                ],
            ], $extra),
        ]);

        $order->user()->associate($user);
        $order->save();

        $order->items()->create([
            'product_id' => $sku->product_id,
            'product_sku_id' => $sku->id,
            'amount' => $amount,
            'price' => $sku->price,
            'rating' => null,
            'review' => null,
            'reviewed_at' => null,
        ]);

        return $order->fresh(['items.productSku', 'items.product']);
    }

    protected function ensureAdminUser()
    {
        $admin = Administrator::query()->first();
        if (!$admin) {
            $admin = Administrator::query()->create([
                'username' => 'admin_' . uniqid(),
                'password' => Hash::make('admin12345'),
                'name' => '系统管理员',
            ]);
        }

        $roleExists = DB::table('admin_roles')->where('id', 1)->exists();
        if (!$roleExists) {
            DB::table('admin_roles')->insert([
                'id' => 1,
                'name' => 'Administrator',
                'slug' => 'administrator',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $pivotExists = DB::table('admin_role_users')
            ->where('role_id', 1)
            ->where('user_id', $admin->id)
            ->exists();

        if (!$pivotExists) {
            DB::table('admin_role_users')->insert([
                'role_id' => 1,
                'user_id' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $admin;
    }
}
