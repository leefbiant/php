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
$router->group(['prefix' => 'contract_pay'], function () use ($router){
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

    $router->get('SendCashReadEnvelopes', 'WxPayController@SendCashReadEnvelopes');
    $router->post('SendCashReadEnvelopes', 'WxPayController@SendCashReadEnvelopes');

    $router->get('GetCertficates', 'WxPayController@GetCertficates');
    $router->post('GetCertficates', 'WxPayController@GetCertficates');

    $router->get('UploadFileTest', 'WxPayController@UploadFileTest');
    $router->post('UploadFileTest', 'WxPayController@UploadFileTest');

    $router->get('getEncryptTest', 'WxPayController@getEncryptTest');
    $router->post('getEncryptTest', 'WxPayController@getEncryptTest');

    $router->get('UploadFile', 'WxPayController@UploadFile');
    $router->post('UploadFile', 'WxPayController@UploadFile');

});
