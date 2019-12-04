<?php
namespace App\Libs;

use Illuminate\Support\Facades\Log;

/**
 * 基类
 */
class Base
{
    protected $error = null;

    /**
     * 获取单例实例
     *
     * @return \Api\Base
     */
    public static function instance($params = [])
    {
        static $instance = null;

        if ($instance === null) {
            $class    = get_called_class();
            $instance = new $class($params);
        }

        return $instance;
    }

    /**
     * 是否在APP中
     * @return [type] [description]
     */
    public static function inApp()
    {
        $inApp = isset($_SERVER['HTTP_USER_AGENT'])
            && (stristr($_SERVER['HTTP_USER_AGENT'], 'BBEX') !== false
            || stristr($_SERVER['HTTP_USER_AGENT'], 'BBex') !== false
            || stristr($_SERVER['HTTP_USER_AGENT'], 'bbex') !== false);

        return $inApp;
    }

    /**
     * 格式化钱包地址
     * @param  [type] $addr [description]
     * @return [type]       [description]
     */
    public static function formatAddress($addr)
    {
        $s = strtolower($addr);
        if (substr($s, 0, 2) != '0x') {
            $s = '0x' . $s;
        }

        $addr_regex = '/^0x[0-9a-f]{40}$/';

        if (preg_match($addr_regex, $s)) {
            return $s;
        }

        return null;
    }

    /**
     * 格式化时间
     * @param  int  $times 时间秒数
     * @return string
     */
    public static function fmtDays($times)
    {
        $h = 3600;
        if ($times < $h) {
            return '1h';
        }
        $hours = ceil($times / $h);
        if ($hours >= 24) {
            $days = intval($hours / 24);
            return $days . 'D';
        } else {
            return $hours . 'h';
        }
    }

    /**
     * 获取AppScheme
     * @param  [type] $param [description]
     * @param  [type] $type  [description]
     * @return [type]        [description]
     */
    public static function getAppScheme($param, $type = 'webView')
    {
        if (!$param) {
            return $param;
        }
        $url = '';
        switch ($type) {
            case 'webView':
                $url = 'bbex://webView?url=' . urlencode($param);
                break;
            case 'projectList':
                $paramStr['status'] = isset($param['status']) ? $param['status'] : 0;
                $paramStr['type']   = isset($param['type']) ? $param['type'] : 'newest';
                isset($param['tags']) && ($paramStr['tags'] = json_encode($param['tags']));
                $url = 'bbex://projectList?' . http_build_query($paramStr);
                break;
            case 'projectDetail':
                if (!is_array($param)) {
                    $param = ['pid' => (int) $param, 'index' => 0];
                } else {
                    $param['pid']   = isset($param['pid']) ? (int) $param['pid'] : 0;
                    $param['index'] = isset($param['index']) ? (int) $param['index'] : 0;
                }

                $url = 'bbex://projectDetail?' . http_build_query($param);
                break;
            case 'readPDF':
                $url   = isset($param['url']) ? $param['url'] : '';
                $title = isset($param['title']) ? $param['title'] : '';
                $url   = 'bbex://readPDF?url=' . urlencode($url) . '&title=' . rawurlencode($title);
                break;
            default:
                # code...
                break;
        }
        return $url;
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
     * 转换场数据为
     * @param  [type] $number [description]
     * @return [type]         [description]
     */
    public static function thousandNumber($number)
    {
        if ($number < 1000) {
            return $number;
        }
        $number = round($number / 1000, 1);
        $arr    = explode('.', $number);
        if (isset($arr[1]) && (intval($arr[1]) == 0)) {
            $number = $arr[0];
        }
        return $number . 'K';
    }
}
