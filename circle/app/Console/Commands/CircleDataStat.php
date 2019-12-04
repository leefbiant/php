<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CircleDataStat extends Command {

  protected $name = 'circle_data:stat';
  protected $description = 'stat';

  public function handle()
  {
    Log::info("CircleDataStat start..");
    // for 活跃人数
    $del_day = date("Ymd",strtotime("-1 day"));
    $today = date("Ymd",time());
    $redis_key = "cirlce_avtice_" . $today;
    $circle_list = Redis::hKeys($redis_key);
    $circle_active = [];
    foreach($circle_list as $node) {
      $val = explode("#", $node);
      if (is_array($val) && count($val) == 2) {
        $circle = $val[0];
        $num = 1;
        Log::info("circle:" . $circle);
        if (isset($circle_active[$circle])) {
          $num = $circle_active[$circle] + 1; 
        }
        $circle_active[$circle] = $num;
      }
    }
    $this->UpdateCircleActive($circle_active, $today);

    // for 成员数量
    $this->UpdateCircleMember($today);
     
    // for 估值
    $this->UpdateCircleValuation();

    // 删除前一天的数据
    $del_active_ok = "cirlce_avtice_" . $del_day;
    $val = Redis::exists($del_active_ok);
    if ($val) {
      Redis::del($del_active_ok);
    }

    // 删除前一天的数据
    $del_member_ok = "cirlce_menber_" . $del_day;
    $val = Redis::exists($del_member_ok);
    if ($val) {
      Redis::del($del_member_ok);
    }
  }

  public function UpdateCircleActive($circle_active, $date) {
    $affected  = 0;
    try {
      foreach ($circle_active as $key => $val) {
        DB::connection('mysql_circle')->insert('insert ignore into circle_data
          (circel_id, circle_date, created_at, updated_at)
          value(?,?,?,?)', [$key, $date, date("Y-m-d H:i:s",time()),
            date("Y-m-d H:i:s",time())]);

        $sql = "update circle_data set active = " . (int)$val . " where circel_id = " . $key
          . " and circle_date = " . $date . " limit 1";
        Log::info("UpdateCircleActive sql:" . $sql);
        $affected  = DB::connection('mysql_circle')->update($sql);
      }
    } catch (\Exception $e) {
      Log::error("UpdateCircleActive Exception-----" . $e->getMessage());
      return -1;
    }
    return 0;
  }

  public function UpdateCircleMember($date) {
    $affected  = 0;
    try {
      $result = DB::connection('mysql_circle')->select('select circel_id, count(circel_id) as count
        from circle_user where user_status = 1 group by circel_id');
      foreach($result as $node) {
        DB::connection('mysql_circle')->insert('insert ignore into circle_data
          (circel_id, circle_date, created_at, updated_at)
          value(?,?,?,?)', [$node->circel_id, $date, date("Y-m-d H:i:s",time()),
            date("Y-m-d H:i:s",time())]);

        $sql = "update circle_data set member = " . (int)$node->count . " where circel_id = " . $node->circel_id
          . " and circle_date = " . $date . " limit 1";
        Log::info("UpdateCircleMember sql:" . $sql);
        $affected  = DB::connection('mysql_circle')->update($sql);
      }
    } catch (\Exception $e) {
      Log::error("UpdateCircleMember Exception-----" . $e->getMessage());
      return -1;
    }
    return 0;
  }

  public function UpdateCircleValuation() {
    $live_activity_list = [];
    try {
      // 后台设置值
      $res = [];
      $result = DB::connection('mysql_circle')->select("select id, activity from circle_info where status = 0");
      foreach($result as $node) {
        $res[$node->id] = $node->activity;
      }
      $live_activity_list = $res;
      
      // 圈子成员
      $result = DB::connection('mysql_circle')->select("select circel_id, member from circle_data where circle_date = :circle_date",
        ['circle_date' => date("Y-m-d",time())]); 
      foreach($result as $node) {
        if (!isset($res[$node->circel_id])) continue;
        $res[$node->circel_id] += $node->member * 50;
      }

      // 活跃数据
      $sql = "select circel_id, sum(active) as active, sum(master_post_num) as master_post_num, sum(master_reply_num) as master_reply_num, sum(master_praise_num) as master_praise_num, 
        sum(post_num) as post_num, sum(reply_num) as reply_num, sum(praise_num) as praise_num
        from circle_data where circle_date >= '" . date("Y-m-d",strtotime("-6 day")) . "' group by circel_id";
      $result = DB::connection('mysql_circle')->select($sql);
      foreach($result as $node) {
        if (!isset($res[$node->circel_id])) continue;
        $res[$node->circel_id] += 50 * $node->active + 150 * $node->master_post_num
          + 30 * $node->master_reply_num
          + 15 * $node->master_reply_num
          + 10 * $node->post_num
          + 3  * $node->reply_num
          + 2  * $node->praise_num;
      }

      foreach($res as $key => $val) {
        $sql = "update circle_info set valuation = " . (int)$val . " where id = " . $key . " limit 1";
        Log::info("UpdateCircleValuation sql:" . $sql);
        $affected  = DB::connection('mysql_circle')->update($sql);
      }
    } catch (\Exception $e) {
      Log::error("UpdateCircleValuation Exception-----" . $e->getMessage());
      return -1;
    }

    try {
      foreach($live_activity_list as $key => &$val) {
        $val = 0;
      }
      $sql = "select circel_id, master_post_num, master_reply_num, master_praise_num, 
        post_num, reply_num, praise_num
        from circle_data where circle_date = '" . date("Y-m-d",strtotime("-1 day")) . "'";
      $result = DB::connection('mysql_circle')->select($sql);
      
      foreach($result as $node) {
        if (!isset($live_activity_list[$node->circel_id])) continue;
        $live_activity_list[$node->circel_id] +=  10 * $node->master_post_num
          + 5 * $node->master_reply_num
          + 3 * $node->master_praise_num
          + 3 * $node->post_num
          + 2 * $node->reply_num
          + 1 * $node->praise_num;
      }

      foreach($live_activity_list as $key => $val) {
        $sql = "update circle_info set liveactivity = " . (int)$val . " where id = " . $key . " limit 1";
        Log::info("UpdateCircleValuation sql:" . $sql);
        $affected  = DB::connection('mysql_circle')->update($sql);
      }
    } catch (\Exception $e) {
      Log::error("UpdateCircleValuation Exception-----" . $e->getMessage());
      return -1;
    }
    return 0;
  }
}
