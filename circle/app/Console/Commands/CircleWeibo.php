<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Util\CircleUtil;
use GuzzleHttp\Client as Client;

require_once __DIR__ . "/../../Util/simple_html_dom.php";

class CircleWeibo extends Command {

  protected $name = 'content:weibo';
  protected $description = 'weibo';

  protected $base_url = "https://m.weibo.cn";

  public function handle()
  {
    date_default_timezone_set('Asia/Shanghai');
    $base_url = "https://m.weibo.cn/api/container/getIndex?containerid=107603";
    $circle_wb_id = $this->GetCirlceMsgId();

    foreach($circle_wb_id as $key =>$cirlce_node) {
      $url = $base_url . $cirlce_node['wb_id'];
      $res = (new Client())->request('GET', $url);
      $res = json_decode($res->getBody()->getContents(), true);
      if (isset($res['data']) && !empty($res['data']['cards'])) {
        $msg_list = $res['data']['cards'];
        foreach($msg_list as $node) {
          if (!isset($node['mblog'])) continue;
          if (!isset($node['mblog']['text'])) continue;

          // 转发的过滤
          $mblog = $node['mblog'];
          if (isset($mblog['retweeted_status'])) continue;

          $id = $node['mblog']['id'];
          if ($cirlce_node['msg_id'] && $cirlce_node['msg_id'] >= $id) continue;
          $text = $node['mblog']['text'];
          $url = $this->GetContentUrl($text);
          $text = strip_tags($text);
          if ($url) $text = $text . $this->link_urldecode($url);

          // 过滤转发
          if (strpos(" " . $text, '转发微博')) {
            continue;
          }

          // 视频连接
          $media_url = $this->GetMediaUrl($node['mblog']);
          if ($media_url) {
            // 视频略过
            continue;
          }

          $image_list = $this->GetPics($node['mblog']);
          if ($image_list) {
            $image_list = json_encode($image_list);
          } else {
            $image_list = "";
          }

          // 文本或者图片为空
          if (!$image_list && !trim($text, " ")) continue;

          log::info("id:" . $id . " msg:" . $text);
          CircleUtil::PublishWbContent($cirlce_node['uid'], $key, $text, $image_list, $id);
          CircleUtil::UpdateCircleLastPostTime($key);
          // if (!$cirlce_node['msg_id']) break;
        }
      }
    }
  }

  public function GetCirlceMsgId() {
    $sql = sprintf("select id, owner, wb_id from circle_info where wb_id != '' and status = 0");
    $result = DB::connection('mysql_circle')->select($sql);
    if (!$result) return [];
    $circle_id_list = [];
    foreach($result as $node) {
      $circle_id_list[$node->id] = [
        'uid' => $node->owner,
        'wb_id' => $node->wb_id,
        'msg_id' => 0,
      ];
    }

    $sql = sprintf("select circel_id, max(third_msg_id) as msg_id from circle_content
      where circel_id in (select id from circle_info where wb_id != '' and status = 0)
      and status = 0
      group by circel_id");

    $result = DB::connection('mysql_circle')->select($sql);
    foreach($result as $node) {
      $circle_id_list[$node->circel_id]['msg_id'] = $node->msg_id;
    }
    // Log::info("circle_id_list:" . json_encode($circle_id_list));
    return $circle_id_list;
  }

  public function GetMediaUrl($node) {
    if (!isset($node['page_info'])) return "";
    if (!isset($node['page_info']['type'])) return "";
    if ($node['page_info']['type'] != 'video') return "";
    if (!isset($node['page_info']['media_info']['stream_url'])) return "";
    return $node['page_info']['media_info']['stream_url'];
  }

  public function GetContentUrl($content) {
    $html = str_get_html($content);
    $a = $html->find('a', 0);
    if ($a && $a->href)  {
      $url = $a->href;
      if (0 != strcmp("http", substr($url, 0, 4))) {
        $url = $this->base_url . $url;
      }
      return $url;
    }
    return null;
  }
  public function GetPics($node) {
    if (!isset($node['pics'])) return "";
    $image_list = [];
    foreach($node['pics'] as $node) {
      list($width, $height, $type, $attr) = getimagesize($node['url']);
      $image_list[] = [
        'originalUrl' => $node['url']['large']['url'],
        'thumbUrl' => $node['url'],
        'width' => $width,
        'height' => $height,
      ];
    }
    return $image_list;
  }

  public function parseurl($url=""){
    $url = rawurlencode($url);
    $a = array("%3A", "%2F", "%40");
    $b = array(":", "/", "@");
    $url = str_replace($a, $b, $url);
    return $url;
  }

  public function link_urldecode($url) {
    $uri = '';
    $cs = unpack('C*', $url);
    $len = count($cs);
    for ($i=1; $i<=$len; $i++) {
      $uri .= $cs[$i] > 127 ? '%'.strtoupper(dechex($cs[$i])) : $url{$i-1};
    }
    return $uri;
  }

}
