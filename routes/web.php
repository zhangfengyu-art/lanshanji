<?php

Route::get('favicon.ico', function () {
    $storagePath = site_favicon_absolute_path();
    if ($storagePath) {
        return response()->file($storagePath, ['Content-Type' => 'image/png']);
    }

    $icoPath = public_path('favicon.ico');
    if (is_file($icoPath) && filesize($icoPath) > 0) {
        return response()->file($icoPath, ['Content-Type' => 'image/png']);
    }

    abort(404);
});

Route::redirect('/', '/products')->name('root');
Route::get('products', 'ProductsController@index')->name('products.index');
Route::get('procurement/detail', 'ProductsController@procurementDetail')->name('procurement.detail');
Route::get('procurement/{procurementOrder}', 'ProcurementOrdersController@show')
    ->where('procurementOrder', '[0-9]+')
    ->name('procurement.show');

Auth::routes();

Route::group(['middleware' => 'auth'], function() {
    Route::get('/email_verify_notice', 'PagesController@emailVerifyNotice')->name('email_verify_notice');
    Route::get('/email_verification/verify', 'EmailVerificationController@verify')->name('email_verification.verify');
    Route::get('/email_verification/send', 'EmailVerificationController@send')->name('email_verification.send');
    Route::group(['middleware' => 'email_verified'], function() {
        Route::get('procurement/create', 'ProductsController@procurementCreate')->name('procurement.create');
        Route::post('procurement/store', 'ProductsController@procurementStore')->name('procurement.store');
        Route::get('procurement/checkout', 'ProductsController@procurementCheckout')->name('procurement.checkout');
        Route::get('procurement/agreement', 'ProductsController@procurementAgreement')->name('procurement.agreement');
        Route::post('procurement/{procurementOrder}/accept', 'ProcurementOrdersController@accept')->name('procurement.accept');
        Route::get('procurement/qualification/create', 'ProxyQualificationController@create')->name('procurement.qualification.create');
        Route::post('procurement/qualification', 'ProxyQualificationController@store')->name('procurement.qualification.store');
        Route::get('procurement/qualification/status', 'ProxyQualificationController@status')->name('procurement.qualification.status');
        Route::get('user_addresses', 'UserAddressesController@index')->name('user_addresses.index');
        Route::get('user_addresses/create', 'UserAddressesController@create')->name('user_addresses.create');
        Route::post('user_addresses', 'UserAddressesController@store')->name('user_addresses.store');
        Route::get('user_addresses/{user_address}', 'UserAddressesController@edit')->name('user_addresses.edit');
        Route::put('user_addresses/{user_address}', 'UserAddressesController@update')->name('user_addresses.update');
        Route::delete('user_addresses/{user_address}', 'UserAddressesController@destroy')->name('user_addresses.destroy');
        Route::post('products/{product}/favorite', 'ProductsController@favor')->name('products.favor');
        Route::delete('products/{product}/favorite', 'ProductsController@disfavor')->name('products.disfavor');
        Route::get('products/favorites', 'ProductsController@favorites')->name('products.favorites');
        Route::post('cart', 'CartController@add')->name('cart.add');
        Route::post('cart/quote', 'CartController@quote')->name('cart.quote');
        Route::get('cart/summary', 'CartController@summary')->name('cart.summary');
        Route::get('cart', 'CartController@index')->name('cart.index');
        Route::patch('cart/{sku}', 'CartController@update')->name('cart.update');
        Route::delete('cart/{sku}', 'CartController@remove')->name('cart.remove');
        Route::post('orders', 'OrdersController@store')->name('orders.store');
        Route::get('orders', 'OrdersController@index')->name('orders.index');
        Route::get('orders/{order}', 'OrdersController@show')->name('orders.show');
        Route::get('orders/{order_no}/fulfillment-photo', 'OrdersController@showFulfillmentPhoto')->name('order.photo.fulfillment');
        Route::get('orders/{order_no}/shopping-receipt', 'OrdersController@showShoppingReceipt')->name('order.receipt.download');
        Route::post('orders/{order}/received', 'OrdersController@received')->name('orders.received');
        Route::get('payment/{order}/alipay', 'PaymentController@payByAlipay')->name('payment.alipay');
        Route::get('payment/{order}/alipay/launch', 'PaymentController@launchAlipay')->name('payment.alipay.launch');
        Route::get('payment/alipay/return', 'PaymentController@alipayReturn')->name('payment.alipay.return');
        Route::get('payment/{order}/wechat', 'PaymentController@payByWechat')->name('payment.wechat');
        Route::get('payment/{order}/wechat/qr', 'PaymentController@wechatQrImage')->name('payment.wechat.qr');
        Route::get('orders/{order}/review', 'OrdersController@review')->name('orders.review.show');
        Route::post('orders/{order}/review', 'OrdersController@sendReview')->name('orders.review.store');
        Route::post('orders/{order}/apply_refund', 'OrdersController@applyRefund')->name('orders.apply_refund')->middleware('throttle:10,1');
        Route::get('orders/{order}/change-address', 'OrdersController@editAddress')->name('orders.change_address');
        Route::put('orders/{order}/address', 'OrdersController@updateAddress')->name('orders.update_address');
        Route::get('coupon_codes/{code}', 'CouponCodesController@show')->name('coupon_codes.show');
        Route::get('support/feedbacks', 'SupportFeedbacksController@index')->name('support.feedbacks.index');
        Route::get('support/feedbacks/create', 'SupportFeedbacksController@create')->name('support.feedbacks.create');
        Route::post('support/feedbacks', 'SupportFeedbacksController@store')->name('support.feedbacks.store');
        Route::get('support/feedbacks/replies', 'SupportFeedbacksController@replies')->name('support.feedbacks.replies');
    });
});

Route::get('payment/cross/{order}', 'PaymentController@crossPay')->name('payment.cross');
Route::post('payment/cross-refund/{order}', 'PaymentController@crossRefund')->name('payment.cross_refund');

Route::get('pages/order-flow', 'PagesController@orderFlow')->name('pages.order_flow');
Route::get('pages/order-flow.html', 'PagesController@orderFlow');
Route::get('pages/change-exchange-return', 'PagesController@changeExchangeReturn')->name('pages.change_exchange_return');
Route::get('pages/change-exchange-return.html', 'PagesController@changeExchangeReturn');
Route::get('pages/faq', 'PagesController@faq')->name('pages.faq');
Route::get('pages/faq.html', 'PagesController@faq');

Route::get('products/{product}', 'ProductsController@show')->name('products.show');
Route::post('payment/alipay/notify', 'PaymentController@alipayNotify')->name('payment.alipay.notify');
Route::post('payment/wechat/notify', 'PaymentController@wechatNotify')->name('payment.wechat.notify');
Route::post('payment/wechat/refund_notify', 'PaymentController@wechatRefundNotify')->name('payment.wechat.refund_notify');

// 旧后台路径 /admin 一律 404（真实入口见 ADMIN_ROUTE_PREFIX）
Route::any('admin', function () {
    abort(404);
});
Route::any('admin/{any}', function () {
    abort(404);
})->where('any', '.*');
