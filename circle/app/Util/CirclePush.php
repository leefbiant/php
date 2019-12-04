<?php

namespace App\Util;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class CirclePush {
  private $push_url;
  private static $_instance;
  public function __construct() {
    $this->push_url = config('app.push_url.url');
    Log::info('Push url:' . $this->push_url);
  }

  public static function getInstance() {
    if(is_null(self::$_instance)) {
      self::$_instance = new CirclePush();
    }
    return self::$_instance;
  }

  public function Pushmsg(array $parameter){
    try {
      if (empty($parameter['content'])) {
        Log::error('parameter error:' , $parameter);
        return -2;
      }
      $push_data = [
        'title' => $parameter['title'],
        'content' => $parameter['content'],
        'setAll' => $parameter['setall'],
        'tags' => $parameter['tags'],
        'scheme' => $parameter['scheme'],
        'extras' => $parameter['extras'],
        ];
      Log::info('send push msg:' , $push_data);
      $res = (new Client())->post($this->push_url, 
        [
          'form_params' => $push_data,
        ]);
      $res = json_decode($res->getBody()->getContents(), true);
      if ($res['code'] == 0) {
        return 0;
      }
      Log::error('psuh msg error :' , $res);
      return -3;
    }  catch (\Exception $e) {
      Log::error('Pushmsg Exception:' . $e->getMessage());
      return -1;
    }
  }
};

