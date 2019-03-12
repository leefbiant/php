<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();

});
$router->group(['prefix' => 'wxpay'], function () use ($router){
    $router->get('AppPayUnifiedOrder', 'WxPayController@AppPayUnifiedOrder');
    $router->post('AppPayUnifiedOrder', 'WxPayController@AppPayUnifiedOrder');

    $router->get('AppPayUrderquery', 'WxPayController@AppPayUrderquery');
    $router->post('AppPayUrderquery', 'WxPayController@AppPayUrderquery');

    $router->get('AppPaymentUser', 'WxPayController@AppPaymentUser');
    $router->post('AppPaymentUser', 'WxPayController@AppPaymentUser');

    $router->get('AppDownloadBill', 'WxPayController@downloadBill');
    $router->post('AppDownloadBill', 'WxPayController@downloadBill');

    $router->get('AppDownloadFundFlow', 'WxPayController@downloadFundFlow');
    $router->post('AppDownloadFundFlow', 'WxPayController@downloadFundFlow');

    $router->get('AppDownloadFundFlow', 'WxPayController@downloadFundFlow');
    $router->post('notify', 'WxPayController@NotifyHandle');

    $router->get('QueryTransferinfo', 'WxPayController@QueryTransferinfo');
    $router->post('QueryTransferinfo', 'WxPayController@QueryTransferinfo');

    $router->get('WxPayJsUnifiedOrder', 'WxPayController@WxPayJsUnifiedOrder');
    $router->post('WxPayJsUnifiedOrder', 'WxPayController@WxPayJsUnifiedOrder');
});
