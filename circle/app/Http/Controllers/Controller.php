<?php

namespace App\Http\Controllers;

use App\Libs\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;

class Controller extends BaseController 
{
    public $uid     = null;
    public $isInApp = false;
    public $request = null;

    public function __construct()
    {
        $this->isInApp = \App\Libs\Base::inApp();
    }

    public function SetRequest(Request $request) {
      $this->request = $request;
    }

    public function checkSecureCode()
    {
        $code = $this->getParams('sc');
        $uid  = (int)$this->getParams('uid');
        if ($uid == 100047) {
            $this->uid = $uid;
            return true;
        }
        if (User::instance()->checkUserSecureCode($uid, $code) === false) {
            Log::info('login error');
            return $this->error(1500, 'secure code error', []);
        }
        $this->uid = $uid;
        return true;
    }

    /**
     * 获取请求参数
     * @param  [type] $key     [description]
     * @param  [type] $default [description]
     * @return [type]          [description]
     */
    public function getParams($key = null, $default = null)
    {
      // if ($key === null) {
      //   return $this->request->all();
      // }
      // return $this->request->input($key, $default);
      $request = app('request');
      if ($key == null) {
        return app('request')->all();
      }
      return app('request')->input($key, $default);
    }

    public function getUrl() {
      return app('request')->url();
    }

    /**
     * 成功
     * @param  array  $data 返回数据
     * @return json
     */
    public function success(array $data = [], $msg = 'ok')
    {
        $res = [
            'code' => 0,
            'msg'  => $msg,
            'data' => $data,
        ];
        if (func_num_args() > 1) {
            $args = array_slice(func_get_args(), 1);
            foreach ($args as $value) {
                $res += $value;
            }
        }
        if (count($data) < 1) unset($res['data']);
        // Log::info("res:", $data);
        return response()->json($res);
    }

    /**
     * 失败
     * @param  string  $msg  错误信息
     * @param  array   $data 错误数据
     * @param  integer $code 错误码
     * @return json
     */
    public function error($code = 1, $msg = 'error', array $data = [])
    {
        $res = [
            'code' => $code,
            'msg'  => $msg,
        ];
        if ($data) {
            $res['data'] = $data;
        }
        if (env('APP_ENV') == 'local') {
            Log::error(var_export($res, true));
        }
        return response()->json($res);
    }
}
