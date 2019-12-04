<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Util\CircleUtil;
use App\Util\ErrCode;
use App\Util\Common;

class ServiceCircleControllers extends Controller {

  public function __construct()
  {
    date_default_timezone_set('Asia/Shanghai');
    if (env('APP_DEBUG')) {
      $Params = $this->getParams();
      Log::info("req:" . $this->getUrl() . " args:" , $Params ? $Params : []);
    }
  }

  // 获取圈子信息
  public function ServiceGetCircle() {
    $Params = $this->getParams();
    Log::info("req:" , $this->getParams());
    $circleId = (int) $this->getParams('circleId');

    $circle_info = CircleUtil::GetCircleInfo($circleId);
    if (!$circle_info) {
       Log::error("DelContent circle not exist :" . $circleId);
       return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    unset($circle_info['circleId']);
    unset($circle_info['owner']);
    unset($circle_info['publishPermission']);
    unset($circle_info['scheme']);
    return $this->success([$circle_info]);
  }

  // 加入圈子
  public function ServicejoinCircle() {
    $Params = $this->getParams();
    Log::info("req:" , $this->getParams());
    $uid = (int) $this->getParams('uid');
    $circleId = (int) $this->getParams('circleId');

    $circle_info = CircleUtil::GetCircleInfo($circleId);
    if (!$circle_info) {
       Log::error("DelContent circle not exist :" . $circleId);
       return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    // 付费圈子 不能直接加入
    if ($circle_info['pay'] == 1) return $this->success();

    $status = $circle_info['join_switch'] ? 0 : 1;
    CircleUtil::VirMemJoinCircle($uid, $circleId, $status);
    return $this->success();
  }

  public function HomeRecommend() {
    Log::info("HomeRecommend req:" , $this->getParams());
    $uid = (int) $this->getParams('uid');
    $pageno = $this->getParams('pageno', 0);
    $recommend_list = CircleUtil::HomeRecommend($uid, $pageno);
    return $this->success($recommend_list);
  }

  public function ServiceDelCircle() {
    Log::info("HomeRecommend req:" , $this->getParams());
    $circle_id = (int) $this->getParams('circle_id');
    CircleUtil::DelCircle($circle_id);
    return $this->success();
  }

  public function GetUserCreateCircle() {
    Log::info("GetUserCreateCircle req:" , $this->getParams());
    $uid = (int) $this->getParams('uid', 0);
    $circle_list = CircleUtil::GetUserCreateCircle($uid);
    return $this->success($circle_list);
  }

  // 系统删除帖子
  public function SysDelContent() {
    Log::info("SysDelContent req:" , $this->getParams());
    $content_id = (int)$this->getParams('content_id', 0);
    $circle_content = CircleUtil::QueryCircleContent($content_id);
    if (!$circle_content) {
      Log::info("SysDelContent not find id:" . $content_id);
      return $this->success();
    }
    return $this->success();
  }

  // 人气圈子
  public function ActivityTopCircle() {
    $top_circle = CircleUtil::GetTopCircle();
    if (!$top_circle) return $this->success();
    $circle_list = [];
    foreach($top_circle as $key => $val) {
      $circle_list[] = $key;
    }
    $clrcle_info_list = CircleUtil::BatchGetCircleInfo($circle_list);
    $top_circle_res = [];
    $index = 0;
    foreach($top_circle as $key => $val) {
      $top_circle_res[] = [
        'id' => $key,
        'name' => $clrcle_info_list[$key]['name'],
        'members' => $val,
        'uid' => $clrcle_info_list[$key]['owner'],
        'rank' => ++$index,
      ];
    }
    return $this->success($top_circle_res);
  } 

  // 圈子精华帖
  public function GetEssEnce() {
    $list = CircleUtil::GetEssEnce(0, 3);
    if ($list && !empty($list['list'])) {
      $circle_list = [];
      foreach($list['list'] as $node) {
        $circle_list[] = $node['circel_id'];    
      }
      $clrcle_info_list = CircleUtil::BatchGetCircleInfo($circle_list);
      foreach($list['list'] as &$node) {
        $node['name'] = $clrcle_info_list[$node['circel_id']]['name'];
      }
    }
    return $this->success($list);
  } 

  // 获取用户创建人气最高的圈子信息
  public function GetUserTopCircle() {
    $uid = (int) $this->getParams('uid', 0);
    $circle_list = CircleUtil::GetCircleRank();
    if (!$circle_list) return $this->success();

    foreach($circle_list as $key => $val) {
      if ($val['uid'] == $uid) {
        $clrcle_info = CircleUtil::BatchGetCircleInfo([$key]);
        return $this->success([
          'id' => $key,
          'uid' => $val['uid'],
          'name' => $clrcle_info[$key]['name'],
          'members' => $val['members'],
          'rank' => $val['rank'],
        ]);
      }
    }
    return $this->success();
  } 

  // OA发帖后通知
  public function OAPublishNotify() { 
    $uid = (int)$this->getParams('uid', 0);
    $circleId = (int)$this->getParams('circleid', 0);
    $content = $this->getParams('content', 0);
    // 帖子ID
    $id = (int)$this->getParams('id', 0);

    $circle_user_list = CircleUtil::GetCircleUserId($circleId);
    $circle_info = CircleUtil::GetCircleInfo($circleId);

    if ($id && ($uid == $circle_info['owner'])) {
      if ($circle_user_list) {
        $circle_user_list = array_diff($circle_user_list, [$uid]);
        if ($circle_user_list) {
          $user_arry = CircleUtil::GetUserInfo([$uid]);
          $record = [
            'type' => 1,
            'title' => "",
            'content' => "[". $user_arry[$uid]['name'] . "]:" . mb_substr($content, 0, 40, 'utf-8'),
            'tags' => $circle_user_list,
            'scheme' => "bbex://postDetail?postId=" . $id,
            'extras' => json_encode(['type' => 1]),
          ];
          CircleUtil::WritePush($record);
        }
      }
    }

    if ($id) {
      CircleUtil::UpdateCircleLastPostTime($circleId);
      CircleUtil::DataStatPost($circleId, $uid == $circle_info['owner']);
    }
    return $this->success();
  }

  // 主贴评论通知
  public function OACommentNotify() { 
    $uid = (int)$this->getParams('uid', 0);
    $circleId = (int)$this->getParams('circleid', 0);
    $content = $this->getParams('content', 0);
    // 帖子ID
    $postId = (int)$this->getParams('post_id', 0);
    $reply_id = (int)$this->getParams('reply_id', 0);
    $reply_uid = (int)$this->getParams('reply_uid', 0);
    $id = (int)$this->getParams('id', 0);

    $circle_info = CircleUtil::GetCircleInfo($circleId);
    $source_content_id = $reply_id == 0 ? $postId : $reply_id;
    $content_info = CircleUtil::QueryCircleContent($source_content_id);
    // 添加通知
    if ($content_info && $content_info['user_id'] != $uid) {
      CircleUtil::AddNotify($circleId, $postId, $source_content_id, $content_info['user_id'],
        $id, $uid);
    }
    // 添加push
    if ($reply_id && $reply_uid) {
      CircleUtil::MakeReplyPush($uid, $reply_id, $reply_uid, $content);
    } else {
      CircleUtil::MakeCommentPush($uid, $postId, $content);
    }
    CircleUtil::UpdateCircleLastPostTime($circleId);
    CircleUtil::DataStatPost($circleId, $uid == $circle_info['owner']);
    return $this->success();
  }

  // 减少圈子用户
  public function ServiceModifyCircleUser() {
    $circleId = (int) $this->getParams('circleId');
    $user_num = (int) $this->getParams('user_num');
    $circle_info = CircleUtil::GetCircleInfo($circleId);
    if (!$circle_info) {
      Log::error("DelContent circle not exist :" . $circleId);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    if ($user_num <= 0) return $this->success();
    $circle_user_list = CircleUtil::GetCircleVirUser($circleId);

    if ($circle_user_list && count($circle_user_list) > $user_num) {
      $target_num = count($circle_user_list) - $user_num;
      foreach($circle_user_list as $uid) {
        if ($uid == $circle_info['owner']) continue;
        if ($target_num-- <= 0) break;
        CircleUtil::QuitCircle($circleId, $uid);
      }
    }
    return $this->success();
  }

  // 减少圈子用户
  public function ServicePayNotify() {
    $out_trade_no = $this->getParams('out_trade_no');

    $order_detail = CircleUtil::GetPayOrdedrBybbexOrder($out_trade_no);
    if (!$order_detail) {
      Log::info("ServicePayNotify order_id not exist");
      return $this->error();
    }
    if ($order_detail['status'] == 1) {
      return $this->success();
    }

    $ret = CircleUtil::QueryOrderPayStatus($order_detail['order_id']);
    if (0 != $ret) {
      if (-1 == $ret) {
        return $this->error($code = ErrCode::$enums['ERR_WX_PAY_ORDER_NOT_EXIST']);
      }
      if (-2 == $ret) {
        return $this->error($code = ErrCode::$enums['ERR_WX_PAY_ORDER_NOT_PAY']);
      }
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_SYS_ERRROR']);
    }

    $ret = CircleUtil::UpdateWxPayStatus($order_detail['order_id']);
    if ($ret != 0) {
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_SYS_ERRROR']);
    }
    Log::info("ServicePayNotify sucess");
    CircleUtil::JoinPayCircle($order_detail['circel_id'], $order_detail['user_id']);
    // 圈主收到用户支付通知
    CircleUtil::AddPayNotify($order_detail['circel_id'], $order_detail['owner'],
      $order_detail['prepayid'], 7);
    return $this->success();
  }

  public function ServiceUserWithdraw() {
    $order_id = $this->getParams('order_id');
    $uid = (int)$this->getParams('uid');
    $order = CircleUtil::QueryUserUnDoWithdrawOrder($uid, $order_id);
    if (!$order) {
      return $this->error();
    }

    $user_real_list = CircleUtil::GetRealUserInfo([$uid]);
    if (!$user_real_list) {
      return $this->error();
    }

    $user_info = $user_real_list[$uid];
    if (!$user_info['real_name'] || !$user_info['id_number'] ||
      !$user_info['openid']) {
        return $this->error();
      }

    $walletInfo = CircleUtil::GetUserWalletInfo($uid);
    $order = $order[$order_id];
    if ($walletInfo['balance'] < $order['amount']) {
      Log::info("withdraw uid:" . $uid . " balance:" . $walletInfo['balance'] 
        . " withdraw amount:" . $order['amount']);
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_NOT_ENOUGHT_BALANCE']);
    }
    // 重点 
    $res = CircleUtil::DOUserWithdraw($user_info, $order);
    return $this->success($res);
  } 

  // 圈子H5分享页
  public function ServiceH5SharedCircle() {
    $circleId  = (int) $this->getParams('circleId');
    if (!$circleId) return $this->success();

    $circle_info = CircleUtil::GetCircleUserStat(0, $circleId);
    if ($circle_info) {
      $circle_user_list = CircleUtil::GetCircleUserId($circleId);
      $members = empty($circle_user_list) ? 0 : count($circle_user_list);
      if ($members > 1000) {
        $members = round($members / 1000, 2) . "K";
      }
      $circle_info['members'] = $members;

      $uid = $circle_info['owner'];
      $user_info = CircleUtil::GetUserInfo([$circle_info['owner']]);
      $user_info = [
        'name' => $user_info[$uid]['name'],
        'uid' => $uid,
        'icon' => $user_info[$uid]['avatar'],
        'scheme' => $user_info[$uid]['scheme'],
      ];
      $circle_info['user_info'] = $user_info;
    }
    $res = CircleUtil::GetCircleSimpleContent($circleId, 3);
    return [
      'circle_info' => $circle_info,
      'content_list' => $res,
    ];
  }

  public function RejectWithdraw() {
    $order_id = $this->getParams('order_id');
    $uid = (int)$this->getParams('uid');

    $order = CircleUtil::QueryUserUnDoWithdrawOrder($uid, $order_id);
    if (!$order) {
      return $this->error();
    }
    CircleUtil::RejectWithdraw($uid, $order_id);
  }
}
