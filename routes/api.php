<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/v1/create-shadow-order', 'Api\\V1\\ShadowOrderController@store')
    ->middleware(['throttle:20,1', 'verify.payment.signature']);

Route::post('/v1/shadow-order/paid', 'Api\\V1\\ShadowOrderWebhookController@paid')
    ->middleware('throttle:30,1');

Route::post('/v1/payment/webhook/paid', 'Api\\V1\\PaymentWebhookController@paid')
    ->middleware(['throttle:30,1', 'verify.payment.webhook.signature']);
