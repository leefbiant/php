<?php
namespace App\Util;

class Common {
  static public $enums  = [
    'CIRCLE_USER_PASS'  => 1, // 通过加入圈子
    'CIRCLE_USER_BLACK'  => 2, // 禁言
    'CIRCLE_USER_KICK'  => 3, // 踢出
    'CIRCLE_USER_CANCEL_BLACK'  => 4, // 取消禁言
    'CIRCLE_TRANSFRER'  => 5, // 取消禁言
  ];

  static public $NOTYFY_TYPE  = [
    'CIRCLE_NOTIFY_MSG'  => 1, // 通知
    'CIRCLE_NOTIFY_PARISE'  => 2, // 点赞
  ];
}
