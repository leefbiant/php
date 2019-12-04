<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Log;
use Illuminate\Support\Facades\DB;
use App\Util\CircleUtil;
use App\Util\DbConnect;

class CircleSettleAccount extends Command {

  protected $name = 'circle:settle';
  protected $description = 'settle';

  public function handle()
  {
    date_default_timezone_set('Asia/Shanghai');
    //已生成订单查询,补单
    $order_list = $this->GetUndoneOrder();
    foreach($order_list as $node) {
      $ret = CircleUtil::QueryOrderPayStatus($node['order_id']);
      if (0 != $ret) continue;
      $ret = CircleUtil::UpdateWxPayStatus($node['order_id']);
      if ($ret != 0) continue;
      CircleUtil::JoinPayCircle($node['circel_id'], $node['user_id']);
      CircleUtil::AddPayNotify($node['circel_id'], $node['owner'],
        $node['prepayid'], 7);
    }

    /////////////////////////////////////// 清算
    $hour = date('H');
    Log::info("start CircleSettleAccount");
    if ($hour < 8) return;
    // 获取8天前支付成功后数据
    // 清算 
    $list = $this->GetPayOrder();
    foreach($list as $key => $order_list) {
      foreach($order_list as $order) {
        $this->QueryUserBalance($key);
        $ret = $this->SettleAccount($order);
        if (0 != $ret) break;
      }
    }

    /////////////////////
    $order_list = $this->GetWithdrawPay();
    foreach($order_list as $order) {
      $res = CircleUtil::QueryOrderWithdraw($order['order_id']);
      if (!$res) continue;
      Log::info("QueryOrderWithdraw order_id:" . $order['order_id'] . " res:" . json_encode($res));
      if ($res['return_code'] == 'SUCCESS') {
        if ($order['status'] == 1) {
          if ($res['result_code'] == 'SUCCESS') $this->DoCheckWithdrawPay($order);
          else $this->DoCheckWithdrawPay($order, 1); 
        } else if ($order['status'] == 2){
          if ($res['result_code'] == 'SUCCESS') $this->DoCheckWithdrawPay($order, 1);
          else $this->DoCheckWithdrawPay($order);
        }
      }
    }
  }

  // 获取未完成订单
  public function GetUndoneOrder() {
    $sql = sprintf("select owner, circel_id, user_id, order_id, prepayid from wx_pay_order
      where order_type = 0 and status = 0
      and UNIX_TIMESTAMP(now()) - unix_timestamp(created_at) < 7200");
    $list = [];
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    foreach ($result as $node) {
      $list[] = [
        'owner' => $node->owner,
        'circel_id' => $node->circel_id,
        'user_id' => $node->user_id,
        'order_id' => $node->order_id,
        'prepayid' => $node->prepayid,
      ];
    }
    return $list;
  }

  public function QueryUserBalance($uid) {
    $sql = sprintf("select uid, balance from user_wallet where uid = %d", $uid);
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql); 
    if (!$result) {
      DbConnect::getInstanceByName('mysql_wx_pay')->insert('INSERT into user_wallet
        (uid, created_at, updated_at)
        value(?,?,?)', [$uid, date("Y-m-d H:i:s",time()), date("Y-m-d H:i:s",time())]);
      return ;
    }
    foreach($result as $node) {
      Log::info("before SettleAccount user :" . $uid . " balance :" . $node->balance);
    }
  }

  public function GetPayOrder() {
    $sql = sprintf("select id, owner, actual_amount, order_id, prepayid from wx_pay_order
      where order_type = 0 and status = 1 and biling_status = 0
      and UNIX_TIMESTAMP(now()) - unix_timestamp(created_at) > %d", config('app.wx_pay.liquidation_time'));
    Log::info("sql:" . $sql);
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    if (!$result) return [];
    $list = [];
    foreach($result as $node) {
      $list[$node->owner][] = [
        'id' => $node->id,
        'owner' => $node->owner,
        'actual_amount' => $node->actual_amount,
        'order_id' => $node->order_id,
        'prepayid' => $node->prepayid,
      ];
    }
    return $list;
  }

  public function SettleAccount($order) {
    Log::info("start SettleAccount order:" . json_encode($order));
    try {
      DbConnect::getInstanceByName('mysql_wx_pay')->beginTransaction();
      $sql = sprintf("update wx_pay_order set biling_status = 1 where id = %d
        and owner = %d and order_id = '%s' and prepayid = '%s'
        and order_type = 0 and status = 1 and biling_status = 0 limit 1",
        $order['id'], $order['owner'], $order['order_id'], $order['prepayid']);

      Log::info("SettleAccount sql:" . $sql);
      $affected = DbConnect::getInstanceByName('mysql_wx_pay')->update($sql);
      if ($affected != 1) {
        Log::error("sql:" . $sql . " affected null");
        DbConnect::getInstanceByName('mysql_wx_pay')->rollback();
        return -1;
      }

      $sql = sprintf("update user_wallet set balance = balance + %f where uid = %d",
        $order['actual_amount'], $order['owner']);
      Log::info("SettleAccount sql:" . $sql);
      $affected = DbConnect::getInstanceByName('mysql_wx_pay')->update($sql);
      if ($affected != 1) {
        Log::error("sql:" . $sql . " affected null");
        DbConnect::getInstanceByName('mysql_wx_pay')->rollback();
        return -1;
      }

      // 记录日志
      DbConnect::getInstanceByName('mysql_wx_pay')->insert("insert into wx_pay_log 
        (uid, amount, type, order_id, prepayid, updated_at, created_at)
        value(?, ?, 0, ?, ?, ?, ?)", [$order['owner'], $order['actual_amount'],
          $order['order_id'], $order['prepayid'], date("Y-m-d H:i:s",time()), date("Y-m-d H:i:s",time())]);

      DbConnect::getInstanceByName('mysql_wx_pay')->commit();
      return 0;
    }catch(\Exception $e){
      Log::error("SettleAccount Exception-----" . $e->getMessage());
      DbConnect::getInstanceByName('mysql_wx_pay')->rollback();
      return -1;
    }
    return -1;
  }

  public function GetWithdrawPay() {
    // 超过24小时为处理直接设置为超时
    $sql = sprintf("select id from wx_pay_order
      where order_type = 1 and status = 0 
      and UNIX_TIMESTAMP(now()) - unix_timestamp(created_at) > 86400");
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    foreach($result as $node) {
      $id = $node->id;
      $sql = sprintf("update wx_pay_order set status = 2, err_msg = '处理超时'
        where id = %d and order_type = 1 and status = 0 limit 1", $node->id);
      Log::info("Withdraw time out update sql:" . $sql);
      DbConnect::getInstanceByName('mysql_wx_pay')->update($sql); 
    }

    $sql = sprintf("select id, user_id, amount, order_id, prepayid, status
      from wx_pay_order where order_type = 1 and has_check = 0 and status > 0");
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    $list = [];
    foreach($result as $node) {
      $list[]= [
        'id' => $node->id,
        'user_id' => $node->user_id,
        'amount' => $node->amount,
        'order_id' => $node->order_id,
        'prepayid' => $node->prepayid,
        'status' => $node->status,
      ];
    }
    return $list;
  }

  public function DoCheckWithdrawPay($order, $exception = 0) {
    if ($exception) {
      $sql = sprintf("update wx_pay_order set has_check = 1, exception = 1 
        where id = %d and order_type = 1 and status = %d limit 1", $order['id'], $order['status']);
    } else {
      $sql = sprintf("update wx_pay_order set has_check = 1 
        where id = %d and order_type = 1 and status = %d limit 1", $order['id'], $order['status']);
    }
    Log::info("DoCheckWithdrawPay: " . $sql);
    DbConnect::getInstanceByName('mysql_wx_pay')->update($sql);
    if ($exception) {
      $content = "提现订单[" . $order['order_id'] . "]异常，请检查";
      CircleUtil::SendSms($content);
    }
  }
}
