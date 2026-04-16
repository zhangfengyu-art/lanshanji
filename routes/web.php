<?php

Route::redirect('/', '/products')->name('root');
Route::get('products', 'ProductsController@index')->name('products.index');
Route::view('help/order-flow', 'pages.order_flow')->name('pages.order_flow');
Route::view('help/change-exchange-return', 'pages.change_exchange_return')->name('pages.change_exchange_return');
Route::view('help/faq', 'pages.faq')->name('pages.faq');
Route::get('procurement/detail', 'ProductsController@procurementDetail')->name('procurement.detail');
Route::view('b/help/faq-guidelines', 'b_mode.pages.faq_guidelines')->name('b_mode.faq_guidelines');

Auth::routes();

Route::group(['middleware' => 'auth'], function() {
    Route::get('/email_verify_notice', 'PagesController@emailVerifyNotice')->name('email_verify_notice');
    Route::get('/email_verification/verify', 'EmailVerificationController@verify')->name('email_verification.verify');
    Route::get('/email_verification/send', 'EmailVerificationController@send')->name('email_verification.send');
    Route::group(['middleware' => 'email_verified'], function() {
        Route::get('procurement/create', 'ProductsController@procurementCreate')->name('procurement.create');
        Route::get('procurement/apply', 'CourierApplicationController@create')->name('procurement.apply');
        Route::post('procurement/apply', 'CourierApplicationController@store')->name('procurement.apply.store');
        Route::post('procurement/store', 'ProductsController@procurementStore')->name('procurement.store');
        Route::get('procurement/checkout', 'ProductsController@procurementCheckout')->name('procurement.checkout');
        Route::get('procurement/agreement', 'ProductsController@procurementAgreement')->name('procurement.agreement');
        Route::get('procurement/accept', 'ProductsController@procurementAccept')->name('procurement.accept.get');
        Route::post('procurement/accept', 'ProductsController@procurementAccept')->name('procurement.accept');
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
        Route::get('cart/summary', 'CartController@summary')->name('cart.summary');
        Route::get('cart', 'CartController@index')->name('cart.index');
        Route::patch('cart/{sku}', 'CartController@update')->name('cart.update');
        Route::delete('cart/{sku}', 'CartController@remove')->name('cart.remove');
        Route::post('orders', 'OrdersController@store')->name('orders.store');
        Route::get('orders', 'OrdersController@index')->name('orders.index');
        Route::get('orders/{order}', 'OrdersController@show')->name('orders.show');
        Route::get('orders/{order_no}/fulfillment-photo', 'OrdersController@showFulfillmentPhoto')->name('order.photo.fulfillment');
        Route::patch('orders/{order}/info', 'OrdersController@updateInfo')->name('orders.update_info');
        Route::patch('orders/{order}/items/{order_item}/swap', 'OrdersController@swapItem')->name('orders.swap_item');
        Route::post('orders/{order}/received', 'OrdersController@received')->name('orders.received');
        Route::get('payment/{order}/alipay', 'PaymentController@payByAlipay')->name('payment.alipay');
        Route::get('payment/alipay/return', 'PaymentController@alipayReturn')->name('payment.alipay.return');
        Route::get('payment/{order}/wechat', 'PaymentController@payByWechat')->name('payment.wechat');
        Route::get('orders/{order}/review', 'OrdersController@review')->name('orders.review.show');
        Route::post('orders/{order}/review', 'OrdersController@sendReview')->name('orders.review.store');
        Route::post('orders/{order}/apply_refund', 'OrdersController@applyRefund')->name('orders.apply_refund');
        Route::get('payment/return', 'PaymentReturnController@show')->middleware('verify.payment.ticket')->name('payment.return');
        Route::get('payment/return/status', 'PaymentReturnController@status')->middleware('verify.payment.ticket')->name('payment.return.status');
        Route::get('coupon_codes/{code}', 'CouponCodesController@show')->name('coupon_codes.show');
        Route::get('support/feedbacks/replies', 'SupportFeedbacksController@replies')->name('support.feedbacks.replies');
        Route::get('support/feedbacks/create', 'SupportFeedbacksController@create')->name('support.feedbacks.create');
        Route::post('support/feedbacks', 'SupportFeedbacksController@store')->name('support.feedbacks.store');
        Route::get('support/feedbacks/{support_feedback}/detail', 'SupportFeedbacksController@replyDetail')->name('support.feedbacks.reply_detail');
    });
});

Route::get('products/{product}', 'ProductsController@show')->name('products.show');
Route::get('relay/shadow-orders/{shadow_no}/alipay', 'ShadowPaymentsController@payByAlipay')->name('relay.shadow_orders.alipay');
Route::get('relay/shadow-orders/{shadow_no}/wechat', 'ShadowPaymentsController@payByWechat')->name('relay.shadow_orders.wechat');
Route::post('payment/alipay/notify', 'PaymentController@alipayNotify')->name('payment.alipay.notify');
Route::post('payment/wechat/notify', 'PaymentController@wechatNotify')->name('payment.wechat.notify');
Route::post('payment/wechat/refund_notify', 'PaymentController@wechatRefundNotify')->name('payment.wechat.refund_notify');
