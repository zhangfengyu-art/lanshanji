<?php

use Illuminate\Routing\Router;

Admin::registerAuthRoutes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {
    $router->get('/', 'HomeController@index');
    $router->get('users', 'UsersController@index');
    $router->get('users/{id}', 'UsersController@show')->name('admin.users.show');
    $router->post('users/{id}/ban', 'UsersController@ban')->name('admin.users.ban');
    $router->post('users/{id}/unban', 'UsersController@unban')->name('admin.users.unban');
    $router->post('users/{id}/reset-session', 'UsersController@resetSession')->name('admin.users.reset_session');
    $router->get('products', 'ProductsController@index');
    $router->get('products/create', 'ProductsController@create');
    $router->post('products', 'ProductsController@store');
    $router->get('products/{id}/edit', 'ProductsController@edit');
    $router->put('products/{id}', 'ProductsController@update');
    $router->delete('products/{id}', 'ProductsController@destroy');
    $router->post('products/{id}/quick-dispatch', 'ProductsController@quickUpdateDispatch');
    $router->post('products/{id}/quick-logistics', 'ProductsController@quickUpdateLogistics');
    $router->post('products/batch-set-category', 'ProductsController@batchSetCategory');
    $router->get('orders', 'OrdersController@index')->name('admin.orders.index');
    $router->get('orders/export_today', 'OrdersController@exportTodayOrders')->name('admin.orders.export_today');
    $router->get('orders/{order}', 'OrdersController@show')->name('admin.orders.show');
    $router->post('orders/{order}/fulfillment', 'OrdersController@updateFulfillment')->name('admin.orders.update_fulfillment');
    $router->post('orders/{order}/acceptance', 'OrdersController@markAcceptance')->name('admin.orders.mark_acceptance');
    $router->post('orders/acceptance/batch', 'OrdersController@batchMarkAcceptance')->name('admin.orders.batch_mark_acceptance');
    $router->post('orders/{order}/ship', 'OrdersController@ship')->name('admin.orders.ship');
    $router->post('orders/{order}/refund', 'OrdersController@handleRefund')->name('admin.orders.handle_refund');
    $router->get('coupon_codes', 'CouponCodesController@index');
    $router->post('coupon_codes', 'CouponCodesController@store');
    $router->get('coupon_codes/create', 'CouponCodesController@create');
    $router->get('coupon_codes/{id}/edit', 'CouponCodesController@edit');
    $router->put('coupon_codes/{id}', 'CouponCodesController@update');
    $router->delete('coupon_codes/{id}', 'CouponCodesController@destroy');

    $router->get('categories', 'CategoriesController@index');
    $router->post('categories', 'CategoriesController@store');
    $router->get('categories/create', 'CategoriesController@create');
    $router->get('categories/{id}/edit', 'CategoriesController@edit');
    $router->put('categories/{id}', 'CategoriesController@update');
    $router->delete('categories/{id}', 'CategoriesController@destroy');

    $router->get('site-settings/logo', 'SiteSettingsController@editLogo')->name('admin.site_settings.logo.edit');
    $router->post('site-settings/logo', 'SiteSettingsController@updateLogo')->name('admin.site_settings.logo.update');

    $router->get('payment_settings', 'PaymentSettingsController@edit')->name('admin.payment_settings.edit');
    $router->match(['put', 'post'], 'payment_settings', 'PaymentSettingsController@update')->name('admin.payment_settings.update');

    $router->resource('support-feedbacks', 'SupportFeedbacksController', ['only' => ['index', 'show', 'edit', 'update']]);

    $router->get('procurement-orders', 'ProcurementOrdersController@index')->name('admin.procurement_orders.index');
    $router->get('procurement-orders/create', 'ProcurementOrdersController@create')->name('admin.procurement_orders.create');
    $router->post('procurement-orders', 'ProcurementOrdersController@store')->name('admin.procurement_orders.store');
    $router->get('procurement-orders/{id}/edit', 'ProcurementOrdersController@edit')->name('admin.procurement_orders.edit');
    $router->put('procurement-orders/{id}', 'ProcurementOrdersController@update')->name('admin.procurement_orders.update');
    $router->get('procurement-orders/{id}/quick-accept', 'ProcurementOrdersController@quickAccept')->name('admin.procurement_orders.quick_accept');
    $router->get('procurement-orders/{id}/review', 'ProcurementOrdersController@review')->name('admin.procurement_orders.review');
    $router->post('procurement-orders/{id}/submit-review', 'ProcurementOrdersController@submitReview')->name('admin.procurement_orders.submit_review');

    $router->get('procurement-reference-items/import', 'ProcurementReferenceItemsController@importForm')->name('admin.procurement_reference_items.import_form');
    $router->post('procurement-reference-items/import', 'ProcurementReferenceItemsController@importStore')->name('admin.procurement_reference_items.import_store');
    $router->resource('procurement-reference-items', 'ProcurementReferenceItemsController');

    $router->get('courier-applications', 'CourierApplicationsController@index')->name('admin.courier_applications.index');
    $router->get('courier-applications/{id}', 'CourierApplicationsController@show')->name('admin.courier_applications.show');
    $router->get('courier-applications/{id}/edit', 'CourierApplicationsController@edit')->name('admin.courier_applications.edit');
    $router->put('courier-applications/{id}', 'CourierApplicationsController@update')->name('admin.courier_applications.update');
});