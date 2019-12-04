<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Log;
use Illuminate\Support\Facades\DB;
use App\Util\CirclePush as Push;
use Illuminate\Support\Facades\Redis;

class PushTask extends Command {

  protected $name = 'push:task';
  protected $description = 'push';
  public function handle()
  {
    $push_task_key = "bbex_circle_push_task";
    $val = Redis::Setnx($push_task_key, 1);
    if (0 == $val)return;
    Redis::Expire($push_task_key, 300);
    

    $push_list = PushTask::GetPushMsg();
    Log::debug("PushTask run start push count:" . count($push_list));
    foreach($push_list as $node) {
      $tags_array = json_decode($node['tags'], true);
      if (count($tags_array) > 20) {
        $tags_chunk = array_chunk($tags_array, 20);
        foreach($tags_chunk as $tags_node) {
          $node['tags'] = json_encode($tags_node);
          Push::getInstance()->Pushmsg($node);
        }
      } else {
        Push::getInstance()->Pushmsg($node);
      }
    }
    PushTask::UpdatePushMsg(); 
    Redis::del($push_task_key);
  }
  static public function GetPushMsg() {
    $obj = DB::connection('mysql_circle')->select('select id, title, content, setall, tags, scheme, extras
      from circle_push_msg
      where UNIX_TIMESTAMP(now()) - UNIX_TIMESTAMP(created_at) < 3600 and status = 0 order by id asc');
    $push_list = [];
    foreach($obj as $node) {
      $push_data = [ 
        'id' => $node->id,
        'title' => $node->title,
        'content' => $node->content,
        'setall' => $node->setall,
        'tags' => $node->tags,
        'scheme' => $node->scheme,
        'extras' => $node->extras,
      ]; 
      $push_list[] = $push_data;
    }
    return $push_list;
  }

  static public function UpdatePushMsg() {
    $affected  = 0;
    try {
      $affected  = DB::connection('mysql_circle')->update('update circle_push_msg set status = 1');
      Log::info("UpdatePushMsg update num:" . $affected);
    } catch (\Exception $e) {
      Log::error("BeReviewCircleUser Exception-----" . $e->getMessage());
    }
  }
}
