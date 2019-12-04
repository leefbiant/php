<?php

namespace App\Libs;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 *  用户相关接口请求
 */
class User extends Base
{
    public $client;

    protected $error = null;

    protected function __construct()
    {
        $this->client = (new Client());
    }

    /**
     * 设置错误信息
     * @param string $msg [description]
     */
    protected function setError($msg = '')
    {
        $this->error = $msg;
        Log::error($msg);
        return false;
    }

    /**
     * 获取错误信息
     * @return [type] [description]
     */
    public function getError()
    {
        return $this->error;
    }


    /**
     * 确认用户secureCode 是否合法
     * @param  [type] $uid        [description]
     * @param  [type] $secureCode [description]
     * @return [type]             [description]
     */
    public function checkUserSecureCode($uid, $secureCode)
    {
        // Log::info(config('services.user'));
        $url = config('services.user.' . env('APP_ENV')) . 'login/checkSecureCode?PFServer=bbex&uid=' . $uid . '&secureCode=' . $secureCode;
        try {
            $client = $this->client->get($url);
            $res    = json_decode($client->getBody()->getContents(), true);
            // Log::info("checkUserSecureCode uid:" . $uid . " res:" , $res);
            if ($res && $res['code'] == 0) {
                return isset($res['data']) ? $res['data'] : $res;
            }
        } catch (\Exception $e) {
            $this->setError('url: ' . $url . "\n[" . __METHOD__ . '] error: ' . $e->getMessage());
        }
        return false;
    }
}
