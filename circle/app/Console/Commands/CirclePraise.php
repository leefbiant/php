<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Util\CircleUtil;

class CirclePraise extends Command {

  protected $name = 'content:praise';
  protected $description = 'praise';

  public function handle()
  {
    date_default_timezone_set('Asia/Shanghai');
    $hour = date('H');
    if ($hour > 1 && $hour < 8) return;

    $content_list = $this->GetPost();
    foreach($content_list as $key => $node) {
      $vpraise = $node['virtual_praise'];
      $praise = max($node['members'], $node['commentCount']);

      if ($praise <= 0) continue;
      $max_praise = rand(0, $praise);
      if ($vpraise >= $max_praise) continue;
      Log::info("content id:" . $key . " vpraise:" . $vpraise . " praise:" . $max_praise);

      $uid = $this->GetVirtualUser($node['circel_id']);
      if (0 == $uid) continue;
      $param = [
        'postId' => $key,
        'circle_content_id' => $key,
        'user_id' => $node['user_id'],
        'uid' => $uid,
        'circel_id' => $node['circel_id'],
      ];
      // Log::info("VirtualParise param:" . json_encode($param));
      CircleUtil::VirtualParise($param);
    }

    $content_list = $this->GetComment();
    foreach($content_list as $key => $node) {
      $vpraise = $node['virtual_praise'];
      $praise = $node['members'];

      if ($praise <= 0) continue;
      $max_praise = rand(0, $praise);
      if ($vpraise >= $max_praise) continue;
      Log::info("content id:" . $key . " vpraise:" . $vpraise . " praise:" . $max_praise);

      $uid = $this->GetVirtualUser($node['circel_id']);
      if (0 == $uid) continue;
      $param = [
        'postId' => $node['post_id'],
        'circle_content_id' => $key,
        'user_id' => $node['user_id'],
        'uid' => $uid,
        'circel_id' => $node['circel_id'],
      ];
      // Log::info("VirtualParise param:" . json_encode($param));
      CircleUtil::VirtualParise($param);
    }
  }

  public function GetPost() {
    $sql = sprintf("select id, circel_id, user_id, virtual_praise from circle_content
      where status = 0 and post_id = 0 and UNIX_TIMESTAMP(now()) - unix_timestamp(created_at) < 86400
      and UNIX_TIMESTAMP(now()) - unix_timestamp(created_at) > 300 and circel_id in (
        select id from circle_info where status = 0)
      ORDER BY RAND() LIMIT 10");
    $result = DB::connection('mysql_circle')->select($sql); 
    if (!$result) return [];
    $content_list = [];
    $circel_id_list = [];
    foreach($result as $node) {
      $content_list[$node->id] = [
        'id' => $node->id,
        'user_id' => $node->user_id,
        'circel_id' => $node->circel_id,
        'virtual_praise' => $node->virtual_praise,
      ];
      $content_list[$node->id]['commentCount'] = CircleUtil::GetCommentNum2($node->circel_id, $node->id);
      $circel_id_list[] = $node->circel_id;
    }

    // 获取人数
    $circel_id_list =  implode(",", $circel_id_list);
    $sql = sprintf("select circel_id, count(1) as count from circle_user
      where circel_id in (%s) and user_status > 0 group by circel_id", 
      $circel_id_list);
    // Log::info("list:" . $circel_id_list . " | sql:" . $sql);
    $result = DB::connection('mysql_circle')->select($sql);
    $circle_id_list = [];
    foreach($result as $node) {  
      $circle_id_list[$node->circel_id] = (int)($node->count / 100);
    }
    foreach($content_list as $key => &$val) {
      $val['members'] = $circle_id_list[$val['circel_id']];
    }
    return $content_list;
  }

  public function GetVirtualUser($circle_id) {
    $sql = sprintf("select user_id from circle_user where circel_id = %d and user_status > 0 and user_type = 1 ORDER BY RAND() LIMIT 1", $circle_id);
    // Log::info("sql:" . $sql);
    $result = DB::connection('mysql_circle')->select($sql); 
    if ($result) return $result[0]->user_id;
    return 0;
  }

  public function GetComment() {
    $sql = sprintf("select id, circel_id, user_id, post_id, virtual_praise from circle_content  
      where status = 0 and post_id in (select id from circle_content where post_id = 0)
      and UNIX_TIMESTAMP(now()) - unix_timestamp(created_at) < 86400
      ORDER BY RAND() LIMIT 10");
    $result = DB::connection('mysql_circle')->select($sql); 

    if (!$result) return [];
    $content_list = [];
    $circel_id_list = [];
    foreach($result as $node) {
      $content_list[$node->id] = [
        'id' => $node->id,
        'user_id' => $node->user_id,
        'circel_id' => $node->circel_id,
        'post_id' => $node->post_id,
        'virtual_praise' => $node->virtual_praise,
      ];
      $circel_id_list[] = $node->circel_id;
    }

    $circel_id_list =  implode(",", $circel_id_list);
    $sql = sprintf("select circel_id, count(1) as count from circle_user
      where circel_id in (%s) and user_status > 0 group by circel_id", 
      $circel_id_list);
    $result = DB::connection('mysql_circle')->select($sql);
    $circle_id_list = [];
    foreach($result as $node) {  
      $circle_id_list[$node->circel_id] = (int)($node->count / 300);
    }
    foreach($content_list as $key => &$val) {
      $val['members'] = $circle_id_list[$val['circel_id']];
    }
    return $content_list;
  }
}
