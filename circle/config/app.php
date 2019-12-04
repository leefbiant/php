<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY', 'SomeRandomString!!!'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */
    'locale' => env('APP_LOCALE', 'en'),
    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'image_host' => env('APP_ENV') == 'production' ? 'https://static.bbex.ren' : 'https://static.icostreet.cn',
    'bbex_user_info_interface' => env('APP_ENV') == 'local' ? 'http://192.168.50.65:8300/user/batchGetUserInfo' : 'http://proxy1:16666/user/batchGetUserInfo',
    'bbex_real_user_info_interface' => env('APP_ENV') == 'local' ? 'http://192.168.50.65:8300/user/batchGetRealUserInfo' : 'http://proxy1:16666/user/batchGetRealUserInfo',

    'log'             => 'daily',
    'log_max_files'   => 21,
    'log_level'       => env('APP_LOG_LEVEL', 'debug'),

    'push_url'        => [
      'url' => env('APP_ENV') == 'local' ? 'http://47.91.233.75:3000/1h3479ewxq/push/admin/send' : 'http://proxy1:16666/push/admin/send',
      ],  
    'circle_check'        => [
      'text' => env('APP_ENV') == 'local' ? 'http://192.168.50.65:8500/thridparty_api/ContentCheck' : 'http://proxy1:16666/thridparty_api/ContentCheck',
      'image' => env('APP_ENV') == 'local' ? 'http://192.168.50.65:8500/thridparty_api/ImageCheck' : 'http://proxy1:16666/thridparty_api/ImageCheck',
      ],  
    'shared_url'  => [
      'circle' => env('APP_ENV') == 'production' ? 'http://static.bbex.ren/activity_web/bbex/dist/circle-detail.html' : 'http://static.bbex.io/activity_web/bbex/dist/circle-detail.html',
      ],  
     'wx_pay'        => [
       'unifiedOrder' => env('APP_ENV') == 'local' ? 'http://192.168.50.65:8700/wxpay/AppPayUnifiedOrder' : 'http://proxy1:16666/wxpay/AppPayUnifiedOrder',
       'JsunifiedOrder' => env('APP_ENV') == 'local' ? 'http://192.168.50.65:8700/wxpay/WxPayJsUnifiedOrder' : 'http://proxy1:16666/wxpay/WxPayJsUnifiedOrder',
       'orderQuery' => env('APP_ENV') == 'local' ? 'http://192.168.50.65:8700/wxpay/AppPayUrderquery' : 'http://proxy1:16666/wxpay/AppPayUrderquery',
       'QueryOrderWithdraw' => env('APP_ENV') == 'local' ? 'http://192.168.50.65:8700/wxpay/QueryTransferinfo' : 'http://proxy1:16666/wxpay/QueryTransferinfo',
       'circle_info_desc' => "1.付费后，你可以使用付款账号进去【%s】,有效期：一年；\n2. 虚拟商品原则上不予退款，如有争议，请联系圈主；\n3. 本圈子由圈主自行创建，付费前请确认风险。",
       'placeholder' => "有效期：用户的加入日期往后计算1年",
       'placeholder1' => "注:结算周期为8天，即用户付费后，8天后才能提现",
       'placeholder2' => "每天最多提现1次，最低提现10元，最高提现2000元\n若当前有提现申请在审核中，则不能再次提现\n如需更改额度或其他问题，请联系微信客服：bbex01",
       'liquidation_time' => env('APP_ENV') == 'production' ? 691200 : 300,
       'Withdraw' => env('APP_ENV') == 'local' ? 'http://192.168.50.65:8700/wxpay/AppPaymentUser' : 'http://proxy1:16666/wxpay/AppPaymentUser',
       'withdraw_amount' => env('APP_ENV') == 'production' ? 10.0 : 1.0,
     ],
     'circle' => [
       'default_contetn' => '圈子「%s」创建成功。<br/>两份武林秘籍请大大查收：<br/><a href="%s">《圈子运营推广技巧：怎样打造个人IP，影响力up？》</a><br/><a href="%s">《圈子使用常见问题》</a><br/><a href="%s"> 更多运营技巧可加入圈子「草根大V成长学院」，点击此处加入</a>',
       'default_url1' => 'bbex://webView?url=' . urlencode("https://shimo.im/docs/NBKMUWFkCZUpehb3"), 
       'default_url2' => 'bbex://webView?url=' . urlencode("https://shimo.im/docs/E7GoW94HyQYfbwhn"),
       'default_url3' => 'bbex://circleDetail?circleId=' . (env('APP_ENV') == 'production' ? 224 : 1),
    ],
     'push_notify'  => env('APP_ENV') == 'production' ? [107672] : [108311],
     'sms_notify_num'  => [13128954294, 18681144866],
     'sms_notify_addr' => 'http://proxy1:16666/sms/sendsms',
];
