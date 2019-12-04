<?php
namespace App\Util;

class ErrCode {
  static public $enums  = 
    [
      'ERR_PARARMER' => 2300,
      'ERR_EXIST_CIRCLE' => 2301,
      'ERR_CREATE_CIRCLE' => 2302,
      'ERR_OUTPACE_CIRCLE' => 2303,
      'ERR_NOT_EXIST' => 2304,
      'ERR_EDIT_CIRCLE' => 2305,
      'ERR_NO_PERMISSION' => 2306,
      'ERR_USER_NOT_EXIST' => 2307,
      'ERR_CIRCLE_CONTENT_NOT_EXIST' => 2308,
      'ERR_CIRCLE_BANNEN' => 2309,
      'ERR_CIRCLE_HAS_MENBER' => 2310,
      'ERR_WX_PAY_TOO_MANY_TIMES' => 2500,
      'ERR_WX_PAY_SYS_ERRROR' => 2501,
      'ERR_WX_PAY_ORDER_NOT_EXIST' => 2502,
      'ERR_WX_PAY_ORDER_NOT_PAY' => 2503,
      'ERR_WX_PAY_HAS_JOIN_CIRCLE_USER' => 2504,
      'ERR_WX_PAY_HAS_WITHDIAW_ORDER' => 2505,
      'ERR_WX_PAY_NOT_ENOUGHT_BALANCE' => 2506,
      'ERR_WX_PAY_NOT_VERIFIED' => 2507,
      'ERR_WX_PAY_NOT_BIND_WX' => 2508,
      'ERR_CIRCLE_DONOT_TRANSFER' => 2311,
      'ERR_OTHER_OUTPACE_CIRCLE' => 2312,
      'ERR_WX_PAY_WITHDRAW_ERR_MSG' => 2509,
    ];
};
