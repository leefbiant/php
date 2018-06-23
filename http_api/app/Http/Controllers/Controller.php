<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function getParams($key = null, $default = null)
    {
      if ($key === null) {
        return request()->all();
      }
      return request($key, $default);
    }

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
      return response()->json($res);
    }

    public function error($msg = 'error', $code = 1, array $data = [])
    {
      $res = [
        'code' => $code,
        'msg'  => $msg,
      ];
      if ($data) {
        $res['data'] = $data;
      }
      return response()->json($res);
    }

}
