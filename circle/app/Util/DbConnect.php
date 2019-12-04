<?php
namespace App\Util;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DbConnect
{
  private static $_instance = array();
  public function __construct()
  {
  }

  public static function getInstance()
  {
    if(is_null(self::$_instance) || !isset(self::$_instance['mysql_circle'])) {
      self::$_instance['mysql_circle'] = DB::connection('mysql_circle');
    }
    return self::$_instance['mysql_circle'];
  }

  public static function getInstanceByName($name) {
    if(is_null(self::$_instance) || !isset(self::$_instance[$name])) {
      self::$_instance[$name] = DB::connection($name);
    }
    return self::$_instance[$name];
  }
}

