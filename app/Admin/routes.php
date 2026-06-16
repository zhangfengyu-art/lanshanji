<?php

use Illuminate\Routing\Router;

$adminPrefix = config('admin.route.prefix');
$adminMiddleware = config('admin.route.middleware');

Route::group([
    'prefix' => $adminPrefix,
    'namespace' => 'App\Admin\Controllers',
    'middleware' => $adminMiddleware,
], function (Router $router) {
    $router->get('auth/login', 'AuthController@getLogin');
    $router->post('auth/login', 'AuthController@postLogin');
    $router->get('auth/logout', 'AuthController@getLogout');
    $router->get('auth/setting', 'AuthController@getSetting');
    $router->put('auth/setting', 'AuthController@putSetting');
});

Route::group([
    'prefix' => $adminPrefix,
    'namespace' => 'Encore\Admin\Controllers',
    'middleware' => $adminMiddleware,
], function (Router $router) {
    $router->group([], function (Router $router) {
        $router->resource('auth/users', 'UserController');
        $router->resource('auth/roles', 'RoleController');
        $router->resource('auth/permissions', 'PermissionController');
        $router->resource('auth/menu', 'MenuController', ['except' => ['create']]);
        $router->resource('auth/logs', 'LogController', ['only' => ['index', 'destroy']]);
    });
});

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {
    $router->get('/', 'HomeController@index');

    $router->group(['middleware' => 'super.admin'], function ($router) {
        $router->get('super-console', 'SuperAdminConsoleController@index');
        $router->get('super-console/roles/create', 'SuperAdminConsoleController@createRole');
        $router->post('super-console/roles', 'SuperAdminConsoleController@storeRole');
        $router->get('super-console/roles/{id}/edit', 'SuperAdminConsoleController@editRole');
        $router->put('super-console/roles/{id}', 'SuperAdminConsoleController@updateRole');
        $router->delete('super-console/roles/{id}', 'SuperAdminConsoleController@destroyRole');

        $router->get('super-console/create', 'SuperAdminConsoleController@create');
        $router->post('super-console', 'SuperAdminConsoleController@store');
        $router->get('super-console/{id}/edit', 'SuperAdminConsoleController@edit')->where('id', '[0-9]+');
        $router->put('super-console/{id}', 'SuperAdminConsoleController@update')->where('id', '[0-9]+');
        $router->delete('super-console/{id}', 'SuperAdminConsoleController@destroy')->where('id', '[0-9]+');
    });
    $router->get('users', 'UsersController@index');
    $router->get('users/{id}', 'UsersController@show')->name('admin.users.show');
    $router->post('users/{id}/ban', 'UsersController@ban')->name('admin.users.ban');
    $router->post('users/{id}/unban', 'UsersController@unban')->name('admin.users.unban');
    $router->post('users/{id}/reset-session', 'UsersController@resetSession')->name('admin.users.reset_session');
    $router->post('users/batch/ban', 'UserBatchController@ban');
    $router->post('users/batch/unban', 'UserBatchController@unban');
    $router->post('users/batch/reset-session', 'UserBatchController@resetSession');
    $router->post('users/batch/verify-email', 'UserBatchController@verifyEmail');

    $router->post('support-feedbacks/batch/mark-handled', 'SupportFeedbackBatchController@markHandled');
    $router->post('support-feedbacks/batch/mark-pending', 'SupportFeedbackBatchController@markPending');
    $router->post('support-feedbacks/batch/reply', 'SupportFeedbackBatchController@reply');

    $router->get('support-feedbacks', 'SupportFeedbacksController@index')->name('admin.support_feedbacks.index');
    $router->get('support-feedbacks/export', 'SupportFeedbacksController@export')->name('admin.support_feedbacks.export');
    $router->get('support-feedbacks/{id}/edit', 'SupportFeedbacksController@edit')->name('admin.support_feedbacks.edit');
    $router->put('support-feedbacks/{id}', 'SupportFeedbacksController@update')->name('admin.support_feedbacks.update');
    $router->get('products', 'ProductsController@index');
    $router->get('products/create', 'ProductsController@create');
    $router->post('products', 'ProductsController@store');
    $router->get('products/{id}/edit', 'ProductsController@edit');
    $router->put('products/{id}', 'ProductsController@update');
    $router->delete('products/{id}', 'ProductsController@destroy');
    $router->post('products/batch/category', 'ProductBatchController@setCategory');
    $router->post('products/batch/shipping-mode', 'ProductBatchController@setShippingMode');
    $router->post('products/batch/tobacco-type', 'ProductBatchController@setTobaccoType');
    $router->post('products/batch/sale-status', 'ProductBatchController@setSaleStatus');
    $router->post('products/batch/on-sale', 'ProductBatchController@setOnSale');
    $router->post('products/batch/logistics', 'ProductBatchController@setLogistics');
    $router->post('products/batch/purchase-limit', 'ProductBatchController@setPurchaseLimit');
    $router->post('products/batch/inherit-category', 'ProductBatchController@inheritCategoryDefaults');
    $router->post('products/batch/adjust-price', 'ProductBatchController@adjustPrice');
    $router->get('products/export-incomplete-logistics', 'ProductBatchController@exportIncompleteLogistics')->name('admin.products.export_incomplete_logistics');
    $router->get('products/import-template', 'ProductsController@downloadImportTemplate')->name('admin.products.import_template');
    $router->post('products/import-csv', 'ProductsController@importCsv')->name('admin.products.import_csv');
    $router->post('products/import-zip', 'ProductsController@importZip')->name('admin.products.import_zip');
    $router->get('orders', 'OrdersController@index')->name('admin.orders.index');
    $router->get('orders/export', 'OrdersController@export')->name('admin.orders.export');
    $router->post('orders/batch/start-processing', 'OrdersController@batchStartProcessing');
    $router->get('orders/{order}', 'OrdersController@show')->name('admin.orders.show');
    $router->get('orders/{order}/fulfillment-photo', 'OrdersController@showFulfillmentPhoto')->name('admin.orders.fulfillment_photo');
    $router->post('orders/{order}/start-processing', 'OrdersController@startProcessing')->name('admin.orders.start_processing');
    $router->post('orders/{order}/lock', 'OrdersController@lockOrder')->name('admin.orders.lock');
    $router->post('orders/{order}/unlock', 'OrdersController@unlockOrder')->name('admin.orders.unlock');
    $router->post('orders/{order}/ship', 'OrdersController@ship')->name('admin.orders.ship');
    $router->post('orders/{order}/fulfillment-photo', 'OrdersController@uploadFulfillmentPhoto')->name('admin.orders.fulfillment_photo.upload');
    $router->delete('orders/{order}/fulfillment-photo', 'OrdersController@deleteFulfillmentPhoto')->name('admin.orders.fulfillment_photo.delete');
    $router->get('orders/{order}/shopping-receipt', 'OrdersController@showShoppingReceipt')->name('admin.orders.shopping_receipt');
    $router->post('orders/{order}/shopping-receipt', 'OrdersController@uploadShoppingReceipt')->name('admin.orders.shopping_receipt.upload');
    $router->delete('orders/{order}/shopping-receipt', 'OrdersController@deleteShoppingReceipt')->name('admin.orders.shopping_receipt.delete');
    $router->post('orders/{order}/refund', 'OrdersController@handleRefund')->name('admin.orders.handle_refund');
    $router->post('orders/{order}/mark-logistics-warehouse', 'OrdersController@markLogisticsWarehouse')->name('admin.orders.mark_logistics_warehouse');
    $router->get('coupon_codes', 'CouponCodesController@index');
    $router->post('coupon_codes', 'CouponCodesController@store');
    $router->get('coupon_codes/create', 'CouponCodesController@create');
    $router->get('coupon_codes/{id}/edit', 'CouponCodesController@edit');
    $router->put('coupon_codes/{id}', 'CouponCodesController@update');
    $router->delete('coupon_codes/{id}', 'CouponCodesController@destroy');
    $router->post('coupon_codes/batch/enabled', 'CouponBatchController@setEnabled');
    $router->post('coupon_codes/batch/add-total', 'CouponBatchController@addTotal');
    $router->post('coupon_codes/batch/extend-expiry', 'CouponBatchController@extendExpiry');

    $router->get('categories', 'CategoriesController@index');
    $router->post('categories', 'CategoriesController@store');
    $router->get('categories/create', 'CategoriesController@create');
    $router->get('categories/{id}/edit', 'CategoriesController@edit');
    $router->put('categories/{id}', 'CategoriesController@update');
    $router->delete('categories/{id}', 'CategoriesController@destroy');
    $router->post('categories/batch/shipping-mode', 'CategoryBatchController@setShippingMode');
    $router->post('categories/batch/directory', 'CategoryBatchController@setDirectory');
    $router->post('categories/batch/move-parent', 'CategoryBatchController@moveParent');

    $router->get('site-settings/logo', 'SiteSettingsController@editLogo')->name('admin.site_settings.logo.edit');
    $router->post('site-settings/logo', 'SiteSettingsController@updateLogo')->name('admin.site_settings.logo.update');

    $router->get('procurement-orders', 'ProcurementOrdersController@index')->name('admin.procurement_orders.index');
    $router->get('procurement-orders/create', 'ProcurementOrdersController@create')->name('admin.procurement_orders.create');
    $router->post('procurement-orders', 'ProcurementOrdersController@store')->name('admin.procurement_orders.store');
    $router->get('procurement-orders/{id}/edit', 'ProcurementOrdersController@edit')->name('admin.procurement_orders.edit');
    $router->put('procurement-orders/{id}', 'ProcurementOrdersController@update')->name('admin.procurement_orders.update');
    $router->delete('procurement-orders/{id}', 'ProcurementOrdersController@destroy')->name('admin.procurement_orders.destroy');
    $router->get('procurement-orders/{id}/quick-accept', 'ProcurementOrdersController@quickAccept')->name('admin.procurement_orders.quick_accept');

    $router->resource('procurement-reference-items', 'ProcurementReferenceItemsController');

    $router->get('proxy-qualifications', 'ProxyQualificationsController@index')->name('admin.proxy_qualifications.index');
    $router->get('proxy-qualifications/{id}', 'ProxyQualificationsController@show')->name('admin.proxy_qualifications.show');
    $router->post('proxy-qualifications/{id}/approve', 'ProxyQualificationsController@approve')->name('admin.proxy_qualifications.approve');
    $router->post('proxy-qualifications/{id}/reject', 'ProxyQualificationsController@reject')->name('admin.proxy_qualifications.reject');
});