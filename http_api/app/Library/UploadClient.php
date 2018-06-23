<?php
namespace App\Library;
use Illuminate\Support\Facades\Log;
/**
 * 文件上传客户端
 */
class UploadClient 
{
  protected $error = null;

  protected $url = ''; // 上传地址

  protected $port = ''; // 上传端口

  public function __construct($config = [])
  {
    $this->url  = config('services.upload.host');
    $this->port = config('services.upload.port');

    isset($config['url']) && ($this->url = $config['url']);
    isset($config['port']) && ($this->port = $config['port']);
  }

  /**
   * 设置配置文件
   * @param [type] $config [description]
   */
  public function setConfig($config)
  {
    isset($config['url']) && ($this->url = $config['url']);
    isset($config['port']) && ($this->port = $config['port']);
    return $this;
  }

  /**
   * 上传单个文件
   * @param  file $file    上传的文件
   * @param  string $path  自定义目录名
   * @param  string $cnf   自定义文件名称
   * @return [type]                 [description]
   */
  public function one($file, $path = '', $cnf = '')
  {
    if (!is_file($file)) {
      return $this->setError('file is not exists');
    }
    $field = [
      'fname' => json_encode(['fn']),
        'path'  => json_encode([$path]),
        'cnf'   => json_encode([$cnf]),
        'fn'    => self::fileStream($file),
      ];

    return $this->upload($field);
  }

  /**
   * 多个文件上传
   * @param  array $fileArr
   * [
   *   [
   *     'file' => '上传的文件',
   *     'path' => '指定目录', (可为空)
   *     'cnf'  => '自定义文件名称'(可为空)
   *   ],
   *   [],
   *   .....
   * ]
   * @return [type]          [description]
   */
  public function multiple($fileArr)
  {
    if (empty($fileArr)) {
      return $this->setError('no file upload');
    }
    $field = $fnames = $paths = $cnfs = [];
    foreach ($fileArr as $key => $value) {
      $fn = 'fn' . $key;
      if (!is_file($value['file'])) {
        return $this->setError('file: ' . $value['file'] . ' not exists.');
      }
      $fnames[]   = $fn;
      $paths[]    = isset($value['path']) ? $value['path'] : '';
      $cnfs[]     = isset($value['cnf']) ? $value['cnf'] : '';
      $field[$fn] = self::fileStream($value['file']);
    }
    $field['fname'] = json_encode($fnames);
    $field['path']  = json_encode($paths);
    $field['cnf']   = json_encode($cnfs);
    if (empty($field)) {
      return $this->setError('no file exists.');
    }
    return $this->upload($field);
  }

  /**
   * 上传文件
   * @param  [type] $field [description]
   * @return [type]        [description]
   */
  protected function upload($field)
  {
    try {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_PORT, $this->port);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      if (strlen($this->url) > 5 && strtolower(substr($this->url, 0, 5)) == 'https') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      }
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $field);
      $response = curl_exec($ch);
      if (curl_errno($ch)) {
        Log::error("error code:" . curl_error($ch));
        return $this->setError('error_code: ' . curl_error($ch));
      } else {
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (200 !== $httpStatusCode) {
          return $this->setError('HTTP REQUEST ERROR: RESPONSE STATUS ' . $httpStatusCode . '; RESPONSE INFO: ' . $response);
        }
      }
    } catch (\Exception $e) {
      Log::error("Exception-----" . $err->getMessage());
      return $this->setError($e->getMessage());
    }
    curl_close($ch);
    $response = json_decode($response, true);
    Log::info("error  response:", $response);
    if (!$response || !isset($response['data']) || !isset($response['data']['fn'])) {
      Log::error("error  response");
      return $this->setError('data parsing error.');
    }
    return $response['data']['fn'];
  }

  /**
   * 获取文件上传方式
   * @param  [type] $file [description]
   * @return [type]       [description]
   */
  public static function fileStream($file)
  {
    if (class_exists('\CURLFile')) {
      return (new \CURLFile($file, mime_content_type($file)));
    }
    return '@' . $file . ';type=' . mime_content_type($file);
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
   * 设置错误信息
   * @param string $msg [description]
   */
  protected function setError($msg = '')
  {
    $this->error = $msg;
    return false;
  }
}
