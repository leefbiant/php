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

    'image_host' => env('APP_ENV') == 'production' ? 'https://static.bbex.io' : 'https://static.icostreet.cn',
    'bbex_user_info_interface' => env('APP_ENV') == 'local' ? 'http://192.168.50.66:8300/user/batchGetUserInfo' : 'http://proxy1:16666/user/batchGetUserInfo',

    'log'             => 'daily',
    'log_max_files'   => 21,
    'log_level'       => env('APP_LOG_LEVEL', 'debug'),
    'upload_dir'      => __DIR__.  "/../storage/upload/",

    'push_url'        => [
      'url' => env('APP_ENV') == 'local' ? 'http://47.91.233.75:3000/1h3479ewxq/push/admin/send' : 'http://proxy1:16666/push/admin/send',
      ],  

      'unifiedOrder_url' => env('APP_ENV') == 'production' ? 'https://api.mch.weixin.qq.com/pay/unifiedorder' : 'https://api.mch.weixin.qq.com/pay/unifiedorder',
      'orderQuery_url' => env('APP_ENV') == 'production' ? 'https://api.mch.weixin.qq.com/pay/orderquery' : 'https://api.mch.weixin.qq.com/pay/orderquery',
      'circle_notify' => env('APP_ENV') == 'local' ? 'http://127.0.0.1:8400/circle/ServicePayNotify' : 'http://proxy1:16666/circle/ServicePayNotify',

      'app_id' => env('APP_ENV') == 'production' ? 'wxac6e4cd49665dba1' : 'wxac6e4cd49665dba1',
];
