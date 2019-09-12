<?php

namespace App\Lib;
use Illuminate\Support\Facades\Log;

class UploadPart
{   
  protected static $url;
  protected static $delimiter;
  protected static $instance;

  public function __construct() {
    static::$url = 'http://files.note.so/v1/file_server';
    static::$delimiter = uniqid();
  }

  public function putPart($param) {
    $post_data = static::buildData($param);
    $curl = curl_init(static::$url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
      "Content-Type: multipart/form-data; boundary=" . static::$delimiter,
      "Content-Length: " . strlen($post_data)
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    $info = json_decode($response, true);
    if (!is_array($info['Msg']) && $info['Msg'] == $param['filesize']) {
      $param['offset'] = $param['filesize'];
      $param['upload'] = '';
      return $this->putPart($param);
    }
    return $response;
  }

  private static function buildData($param){
    $data = '';
    $eol = "\r\n";
    $upload = $param['upload'];
    unset($param['upload']);

    foreach ($param as $name => $content) {
      $data .= "--" . static::$delimiter . "\r\n"
        . 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n"
        . $content . "\r\n";
    }
    $data .= "--" . static::$delimiter . $eol
      . 'Content-Disposition: form-data; name="upload"; filename="' . $param['filename'] . '"' . "\r\n"
      . 'Content-Type:application/octet-stream'."\r\n\r\n";

    $data .= $upload . "\r\n";
    $data .= "--" . static::$delimiter . "--\r\n";
    return $data;
  }

  public static function getInstance() {
    if(!static::$instance){
      static::$instance = new static();
    }
    return static::$instance;
  }
}

