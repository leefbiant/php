<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Util\CircleUtil;
use App\Util\ErrCode;
use App\Util\Common;
use App\Util\DbConnect;
use GuzzleHttp\Client as Client;

class CircleControllers extends Controller {

  public function __construct()
  {
    date_default_timezone_set('Asia/Shanghai');
    if (env('APP_DEBUG')) {
      $Params = $this->getParams();
      Log::info("req:" . $this->getUrl() . " args:" , $Params ? $Params : []);
    }
  }

  public function EchoTest() {
    $headers = app('request')->headers->all();
     // dd($headers);
     dd($headers['useragent']);
    return $this->success(["EchoTest"]);
  }

  public function circleList() {
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 10); // 默认 25
    $uid = (int) $this->getParams('uid');

    if ($limit > 10) $limit = 10;

    if ($uid) {
      $circel_id_list = CircleUtil::GetUserjoinCircleId($uid);
      // 用户已经进圈子，返回消息流
      if ($circel_id_list) return $this->GetCircleTimeline($uid, $circel_id_list, $page, $limit, false);
    }
    $num = $limit + 1;

    $page = $limit * $page;
    $result = DbConnect::getInstance()->select('select id, name, summary, image, recommend from circle_info
      where status = 0 and recommend > 0
      order by weights desc, recommend desc, id desc limit :page, :limit', ['page' => $page, 'limit' => $num]);

    $list = [];
    $max_recommend = 3;
    $index = 0;
    foreach($result as $node) {
      if (++$index > $limit) break;
      $image = $node->image;
      if (0 != strcmp("http", substr($image, 0, 4))) {
        $image = config('app.image_host') . $image;
      }

      $tmp = [
         'name' => $node->name,
         'desc' => $node->summary,
         'icon' => $image,
         'circleId' => $node->id,
         'scheme' => "bbex://circleDetail?circleId=" . $node->id,
         'selected' => $max_recommend > 0 ? (bool)$node->recommend : false,
       ];
      if ($node->recommend) {
        $max_recommend--;
      }
      $list[] = $tmp;
    }
    $hasMore = $index > $limit;
    return $this->success([
     'hasMore' => $hasMore,
       'rcList'  => $list,
     ]);
  }

  public function GetCircleTimeline($uid, $circel_id_list, $page, $limit) {
    $num = $limit + 1;

    $circel_id_list =  implode(",", $circel_id_list);
    $page = $limit * $page;
    $sql = "select id, circel_id, user_id, content, coretext, attachment, top, essence, unix_timestamp(created_at) as ts
      from circle_content where circel_id in (" . $circel_id_list . ") and post_id = 0 and status = 0
      order by created_at desc limit " . $page . "," . $num;
    $circel_content_list = DbConnect::getInstance()->select($sql);

    $user_id_list = [];
    $circle_list = [];
    foreach($circel_content_list as $node) {
      $user_id_list[] = $node->user_id;
      $circle_list[] = $node->circel_id;
    }
    $user_id_list = array_unique($user_id_list);
    $circle_list = array_unique($circle_list);

    $circle_info_list = [];
    foreach($circle_list as $node) {
      $circleInfo = CircleUtil::GetCircleInfo($node);
      if ($circleInfo) {
        $circleInfo['joined'] = true;
        $circle_info_list[$node] = $circleInfo;
      }
    }

    $list = [];
    $index = 0;
    foreach($circel_content_list as $node) {
      $t1 = microtime(true);
      if (!isset($circle_info_list[$node->circel_id])) continue;
      if (++$index > $limit) break;

      // for postInfo
      $commentCount = CircleUtil::GetCommentNum2($node->circel_id, $node->id);
      $parise = CircleUtil::GetPariseNum($node->id, $uid);

      $tags = [];
      if ($node->top) {
        $tags[] = [
          'name' => '置顶',
        ];
      }
      if ($node->essence) {
        $tags[] = [
          'name' => '精华',
        ];
      }
      $attachment = [];
      if ($node->attachment) {
        $pictures_array = json_decode($node->attachment, true);
        foreach($pictures_array as &$pic_obj) {
          if (0 != strcmp("http", substr($pic_obj['thumbUrl'], 0, 4))) {
            $pic_obj['thumbUrl'] = config('app.image_host') . $pic_obj['thumbUrl'];
          }
          if (0 != strcmp("http", substr($pic_obj['originalUrl'], 0, 4))) {
            $pic_obj['originalUrl'] = config('app.image_host') . $pic_obj['originalUrl'];
          }
          $attachment[] = $pic_obj;
        }
      }

      // comments
      $comments = [];
      $comments = CircleUtil::GetCommentWithoutUser($node->id, 3);

      $res_data = [
        'circleId' => $node->circel_id,
        'postId' => $node->id,
        'content' => $node->content,
        'coretext' => $node->coretext,
        'ts' => $node->ts,
        'pariseCount' => $parise['pariseCount'],
        'isParised' => $parise['isParised'],
        'commentCount' => $commentCount,
        'uid' => $node->user_id,
        'tags' => $tags,
        'attachments' => $attachment,
        'comments' => $comments,
        "scheme" => "bbex://postDetail?postId=" . $node->id,
        "shareInfo" => [
          'url' => config('app.shared_url.circle') . "?circleId=" . $node->circel_id,
        ],
      ];
      $list[] = $res_data;
      $t2 = microtime(true);
      // Log::info("GetCircleTimeline time cost:" . (($t2-$t1)*1000));
    }

    foreach($list as &$node) {
      $circleInfo = $circle_info_list[$node['circleId']];
      $node['circleInfo'] = $circleInfo;
      $node['shareInfo']['title'] = $circleInfo['name'];
      $node['shareInfo']['desc'] = $circleInfo['desc'];
      $node['shareInfo']['desc'] = $circleInfo['icon'];
      if (!empty($node['comments'])) {
        foreach($node['comments'] as $iter) {
          $user_id_list[] = $iter['uid'];
          if (isset($iter['replyObjectInfo'])) {
            $user_id_list[] = $iter['replyObjectInfo']['uid'];
          }
        }
      }
      CircleUtil::CircleContentFilter($node);
    }
    $user_id_list = array_unique($user_id_list);
    $user_info_list = CircleUtil::GetUserInfo($user_id_list);

    foreach($list as &$node) {
      $node['userInfo'] = [
        'name' => $user_info_list[$node['uid']]['name'],
        'uid' => $node['uid'],
        'icon' => $user_info_list[$node['uid']]['avatar'],
        'scheme' => $user_info_list[$node['uid']]['scheme'],
      ];
      if (!empty($node['comments'])) {
        foreach($node['comments'] as &$iter) {
          $iter['name'] = $user_info_list[$iter['uid']]['name'];
          if (!empty($iter['replyObjectInfo'])) {
            $iter['replyObjectInfo']['name'] = $user_info_list[$iter['replyObjectInfo']['uid']]['name'];
          }
        }
      }
    }

    $hasMore = $index > $limit;

    return $this->success([
      'hasMore' => $hasMore,
      'cmList'  => $list,
    ]);
  }

  // 创建圈子
  public function CreateCircle() {
    $uid = (int) $this->getParams('uid');
    $name = $this->getParams('name');
    $logo = $this->getParams('logo');
    $desc = $this->getParams('desc', "");
    $fee = (float)$this->getParams('fee', 0.0);
    // 简介最多60
    if (mb_strlen($desc,"utf-8") > 60) $desc = mb_substr($desc, 0, 60, 'utf-8');

    if (!$uid || !$name || !$desc) {
      Log::info("CreateCircle err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $cirlce_id = CircleUtil::GetCircleByName($name);
    if ($cirlce_id) {
      Log::info("CreateCircle circle has exist:" . $name);
      return $this->error($code = ErrCode::$enums['ERR_EXIST_CIRCLE']);
    }
    // 检查用户创建的圈子个数
    $circle_list = CircleUtil::GetUserCreateCirle($uid);
    if ($circle_list && count($circle_list) >= 6) {
      Log::info("CreateCircle circle has exist:" . $name);
      return $this->error($code = ErrCode::$enums['ERR_OUTPACE_CIRCLE']);
    }

    $circle_id = CircleUtil::CreateCircle($uid, $name, $logo, $desc, $fee);
    if (!$circle_id) {
      Log::info("CreateCircle circle failed circle_name:" . $name);
      return $this->error($code = ErrCode::$enums['ERR_EXIST_CIRCLE']);
    }
    $content = config('app.circle.default_contetn');
    $content = sprintf($content, $name, 
      config('app.circle.default_url1'),
      config('app.circle.default_url2'),
      config('app.circle.default_url3')
    );
    CircleUtil::PublishCoretext($uid, $circle_id, $content);
    return $this->success(['circleId' => $circle_id]);
  }

  // 编辑圈子
  public function EditCircleInfo() {
    $uid = (int) $this->getParams('uid');
    $name = $this->getParams('name');
    $logo = $this->getParams('logo', "");
    $desc = $this->getParams('desc');
    $cirlce_id = (int)$this->getParams('circleId');

    if (!$uid || !$name || !$desc || !$cirlce_id) {
      Log::info("EditCircleInfo err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $cirlce = CircleUtil::GetCircleById($cirlce_id);
    if (!$cirlce) {
      Log::info("CreateCircle circle has exist:" . $name);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }

    if ($cirlce['owner'] != $uid) {
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }

    $affected = CircleUtil::EditCircle($cirlce_id, $name, $logo, $desc);
    if (!$affected) {
      Log::info("EditCircle circle failed circle_name:" . $name);
      return $this->error($code = ErrCode::$enums['ERR_EDIT_CIRCLE']);
    }
    return $this->success();
  }

  // 退出圈子
  public function QuitCircle() {
    $uid = (int) $this->getParams('uid');
    $cirlce_id = (int)$this->getParams('circleId');

    if (!$uid || !$cirlce_id) {
      Log::info("QuitCircle err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $cirlce = CircleUtil::GetCircleById($cirlce_id);
    if (!$cirlce) {
      Log::info("QuitCircle circle has exist:" . $cirlce_id);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }

    // 群主退圈 删除圈子
    if ($cirlce['owner'] == $uid) {
      $circle_user_list = CircleUtil::GetCircleUserId($cirlce_id);
      if ($circle_user_list && count($circle_user_list) > 1) {
        // 圈子含有其他成员不能退圈
        return $this->error($code = ErrCode::$enums['ERR_CIRCLE_HAS_MENBER']);
      }
      CircleUtil::DelCircle($cirlce_id);
    }

    CircleUtil::QuitCircle($cirlce_id, $uid);
    return $this->success();
  }

  // 设置圈子权限
  public function SetCirclePermission() {
    $uid = (int) $this->getParams('uid');
    $cirlce_id = (int)$this->getParams('circleId');
    $level = (int)$this->getParams('level', 0);

    if (!$uid || !$cirlce_id) {
      Log::info("SetCirclePermission err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $cirlce = CircleUtil::GetCircleById($cirlce_id);
    if (!$cirlce) {
      Log::info("SetCirclePermission circle has not exist:" . $cirlce_id);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }

    if ($cirlce['owner'] != $uid) {
      return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
    }

    CircleUtil::SetCirclePermission($cirlce_id, $uid, $level);
    return $this->success();
  }

  // 查看圈子成员
  public function QueryCircleMembers() {
    $uid = (int) $this->getParams('uid');
    $cirlce_id = (int)$this->getParams('circleId');
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 500); // 默认 25
    $uid = (int) $this->getParams('uid');


    if (!$uid || !$cirlce_id) {
      Log::info("QueryCircleMembers err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $cirlce = CircleUtil::GetCircleById($cirlce_id);
    if (!$cirlce) {
      Log::info("QueryCircleMembers circle has not exist:" . $cirlce_id);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }

    $user_list = CircleUtil::QueryCircleMembers($cirlce_id, $cirlce['owner'], $uid, $page, $limit);
    if ($uid == $cirlce['owner']) {
      // 标记新用户已经被查看
      CircleUtil::BeReviewCircleUser($cirlce_id);
    }
    return $this->success($user_list);
  }

  // 批量加入圈子
  public function UserJoinCircle() {
    $uid = (int) $this->getParams('uid');
    $cirlces = $this->getParams('circles');
    $cirlces = json_decode($cirlces, true);

    if (!$uid || !$cirlces) {
      Log::info("UserJoinCircle err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $cirlce_list = [];
    foreach($cirlces as $cirlce_id) {
      $cirlce = CircleUtil::GetCircleById($cirlce_id);
      if (!$cirlce) {
        Log::info("UserJoinCircle circle has not exist:" . $cirlce_id);
        return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
      }
      $cirlce_list[] = $cirlce;
    }
    foreach($cirlce_list as $node) {
      if ($node['pay'] == 1) continue; // 付费圈子
      $status = $node['join_switch'] ? 0 : 1;
      CircleUtil::IgnoreJoin($node['id'], $uid, $status);
    }
    return $this->success();
  }

  // 用户加入圈子列表
  public function UserJoinCircleList() {
    $uid = (int) $this->getParams('uid');
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 10); // 默认 25
    $join_circle_list = $this->getParams('jsonStr'); // 默认 25

    if (!$uid) {
      Log::info("UserJoinCircleList err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $circel_id_list = CircleUtil::GetUserjoinCircleId($uid);

    $circle_unread_list = [];
    if ($join_circle_list) {
      $join_circle_list = json_decode($join_circle_list, true);
      foreach($join_circle_list as $key => $value) {
        $circle_unread_list[(int)$key] = $value;
      }
    }

    $cirlce_info_list = CircleUtil::BatchGetCircleInfo($circel_id_list);

    $list = [];
    $hasMore = false;
    $index = 0;
    foreach($circel_id_list as $circel_id) {
      if ($index < $page * $limit) continue;
      if ($index >= $page * $limit + $limit) {
        $hasMore = true;
        break;
      }
      $circleInfo = $cirlce_info_list[$circel_id];
      if (!$circleInfo) continue;
      $circleInfo['lord'] = $uid == $circleInfo['owner'];
      unset($circleInfo['owner']);

      if (isset($circle_unread_list[$circel_id])) {
        $circleInfo['unreadCount'] = CircleUtil::GetCircleUnReadCount($circel_id, $circle_unread_list[$circel_id]);
      } else {
        $circleInfo['unreadCount'] = 0;
      }

      $list[] = $circleInfo;
      $index += 1;
    }
    return $this->success([
      'hasMore' => $hasMore,
      'list' => $list,
      ]
    );
  }

  public function UserJoinCircleList2() {
    $uid = (int) $this->getParams('uid');
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 10); // 默认 25
    $join_circle_list = $this->getParams('jsonStr'); // 默认 25

    if (!$uid) {
      Log::info("UserJoinCircleList err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $circel_id_list = CircleUtil::GetUserjoinCircleId($uid);

    $circle_unread_list = [];
    if ($join_circle_list) {
      $join_circle_list = json_decode($join_circle_list, true);
      foreach($join_circle_list as $key => $value) {
        $circle_unread_list[(int)$key] = $value;
      }
    }
    $cirlce_info_list = CircleUtil::BatchGetCircleInfo($circel_id_list);
    $list = [];
    foreach($circel_id_list as $circel_id) {
      $circleInfo = $cirlce_info_list[$circel_id];
      $circleInfo['lord'] = $uid == $circleInfo['owner'];
      unset($circleInfo['owner']);

      if (isset($circle_unread_list[$circel_id])) {
        $circleInfo['unreadCount'] = CircleUtil::GetCircleUnReadCount($circel_id, $circle_unread_list[$circel_id]);
      } else {
        $circleInfo['unreadCount'] = 0;
      }
      $circleInfo['rank'] = 999;
      $list[$circel_id] = $circleInfo;
    }
    $user_sort_list = CircleUtil::GetsortCircleList($uid);
    $res_sort_list = [];
    if ($user_sort_list) {
      $user_sort_list = array_map(function($v){
        return (int)$v;
      }, $user_sort_list);

      $index = 1;
      foreach($user_sort_list as $circel_id) {
        if(isset($list[$circel_id])) {
          $list[$circel_id]['rank'] = $index++;
        }
      }
    }
    foreach($list as $key => $node) {
      $res_sort_list[] = $node;
    }
    if ($res_sort_list) {
      usort($res_sort_list, function($a, $b){
        return $a['rank'] > $b['rank'];
      });
    }
    return $this->success([
      'hasMore' => false,
      'list' => $res_sort_list,
      ]
    );
  }



  // 圈子详情
  public function CircleDetail() {
    $uid = (int) $this->getParams('uid', 0);
    $circleId  = (int) $this->getParams('circleId', 0);    // 0 开始

    if (!$circleId) {
      Log::info("CircleDetail err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    $circleInfo = CircleUtil::GetCircleUserStat($uid, $circleId);
    if (!$circleInfo)  {
      Log::info("circleId not exist:" . $circleId);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    // $circleInfo['lord'] = $uid == $circleInfo['owner'] ? true : false;

    $uid_list = [$circleInfo['owner']];
    $user_info_list = CircleUtil::GetUserInfo($uid_list);
    $circleInfo['lordName'] = $user_info_list[$circleInfo['owner']]['name'];
    $circleInfo['lordPic'] = $user_info_list[$circleInfo['owner']]['avatar'];

    // 圈子成员
    $circle_user_list = CircleUtil::GetCircleUserId($circleId);
    $members = empty($circle_user_list) ? 0 : count($circle_user_list);
    if ($members > 1000) {
      $members = round($members / 1000, 2) . "K";
    }
    $circleInfo['members'] = $members;
    if ($uid == $circleInfo['owner']) {
      $new_members = CircleUtil::GetCircleUserId($circleId, 1);
      $circleInfo['newcomer'] = count($new_members);
    }
    unset($circleInfo['owner']);

    // 加入权限
    $circleInfo['joinCirclePermission'] = $circleInfo['join_switch'];
    unset($circleInfo['join_switch']);

    // 精华帖数量
    $fineCount = CircleUtil::GetCircleFineCount($circleId);
    if (!$fineCount) $fineCount = 0;
    $circleInfo['fineCount'] = $fineCount;

    // 分享页
    $shared_url = config('app.shared_url.circle') . "?circleId=" . $circleId;
    $shareInfo = [
      'title' => $circleInfo['name'],
      'desc' => $circleInfo['desc'],
      'imageUrl' => $circleInfo['icon'],
      'url' => $shared_url,
    ];

    return $this->success([
      'head' => $circleInfo,
      'shareInfo' => $shareInfo,
      ]
    );
  }

  // 圈子成员权限管理
  public function SetCircleUserPermission() {
    $uid = (int) $this->getParams('uid');
    $circleId = (int) $this->getParams('circleId');
    $memberId = (int) $this->getParams('memberId');
    $operate = (int) $this->getParams('operate');

    if (!$uid || !$circleId || !$memberId || !$operate) {
      Log::info("SetCircleUserPermission err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    $circleInfo = CircleUtil::GetCircleInfo($circleId);
    if (!$circleInfo) {
      Log::info("circle not EXIST:" . $circleId);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    // 圈主不是请求用户
    if ($circleInfo['owner'] != $uid) {
      Log::info("circle not EXIST:" . $circleId);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }

    $circle_user = CircleUtil::QueryCircleExistUser($circleId, $memberId);
    if (!$circle_user) {
      Log::info("cirlce:" . $circleId . " not EXIST user:" . $memberId);
      return $this->error($code = ErrCode::$enums['ERR_USER_NOT_EXIST']);
    }

    $user_premission = 1;
    if(1 == $operate) {
      $user_premission =  Common::$enums['CIRCLE_USER_BLACK'];
    } else if (2 == $operate) {
      $user_premission =  Common::$enums['CIRCLE_USER_KICK'];
    } else if (3 == $operate) {
      $user_premission =  Common::$enums['CIRCLE_USER_CANCEL_BLACK'];
    } else if (4 == $operate) {
      // 转让圈子
      if (1 == $circleInfo['attr']) {
        return $this->error($code = ErrCode::$enums['ERR_CIRCLE_DONOT_TRANSFER']);
      }
      // 检查超过创建圈子上限
      $circle_list = CircleUtil::GetUserCreateCirle($memberId);
      if ($circle_list && count($circle_list) >= 6) {
        Log::info("SetCircleUserPermission uid :" . $memberId . " operate:" . 
          $operate . " create circle num:" . count($circle_list));
        return $this->error($code = ErrCode::$enums['ERR_OTHER_OUTPACE_CIRCLE']);
      }
      $user_premission =  Common::$enums['CIRCLE_TRANSFRER'];
    } else {
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    CircleUtil::SetCircleUserPermission($circleId, $memberId, $user_premission);
    return $this->success([
      ]
    );
  }

  // 帖子点赞
  public function CircleCotentParise() {
    $uid = (int) $this->getParams('uid');
    $postId = (int) $this->getParams('postId');
    $commentId = (int) $this->getParams('commentId');

    if (!$uid || (!$postId && !$commentId)) {
      Log::info("CircleCotentParise err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    $circle_content_id = $commentId ? $commentId : $postId;
    if (!$circle_content_id) {
      Log::info("CircleCotentParise circle_content not exist :" . $circle_content_id);
      return $this->error($code = ErrCode::$enums['ERR_CIRCLE_CONTENT_NOT_EXIST']);
    }

    $circle_content = CircleUtil::QueryCircleContent($circle_content_id);
    if (!$circle_content) {
      Log::info("CircleCotentParise circle_content not exist :" . $circle_content_id);
      return $this->error($code = ErrCode::$enums['ERR_CIRCLE_CONTENT_NOT_EXIST']);
    }

    $id = CircleUtil::CircleCotentParise($postId, $circle_content_id, $circle_content['user_id'], $uid);
    // if ($id && $uid != $circle_content['user_id']) {
    //   CircleUtil::MakePraisePush($uid, $circle_content_id, $postId);
    // }
    if ($id) {
      $circle_info = CircleUtil::GetCircleInfo($circle_content['circel_id']);
      CircleUtil::DataStatPraise($circle_content['circel_id'], $uid == $circle_info['owner']);
    }
    return $this->success([
      ]
    );
  }

  // 未读消息数
  public function CircleMsgCount() {
    $uid = (int) $this->getParams('uid');
    if (!$uid) {
      Log::info("CircleMsgCount err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    $mgs_count = CircleUtil::CircleMsgCount($uid);
    return $this->success($mgs_count);
  }

  // 个人消息列表
  public function CircleMsgList() {
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 25); // 默认 25

    $uid = (int) $this->getParams('uid');
    $type = $this->getParams('type', '');

    if (!$uid) {
      Log::info("CircleMsgList err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $type = $type == "" ? 1 : 2;
    $res = [];
    if (1 == $type) {
      $res = CircleUtil::GetNotifyMsg($uid, $page, $limit);
      CircleUtil::SetReadMsg($uid);
    } else {
      // for 点赞
      $res = CircleUtil::GetPraiseMsg($uid, $page, $limit);
      CircleUtil::SetReadPraise($uid);
    }
    return $this->success($res);
  }


  ///////////////////////////////////////////以下为帖子相关接口//////////////////////////////////////

  // 帖子详情
  public function CircleContentDetail() {
    $uid = (int) $this->getParams('uid');
    $postId = (int) $this->getParams('postId');

    $circle_content = CircleUtil::QueryCircleContent($postId);
    if (!$circle_content) {
      Log::info("CircleContentDetail circle_content not exist :" . $postId);
      return $this->error($code = ErrCode::$enums['ERR_CIRCLE_CONTENT_NOT_EXIST']);
    }
    // $cirlce_uid = CircleUtil::QueryCircleExistUser($circle_content['circel_id'], $uid);
    // if (!$cirlce_uid) {
    //   Log::info("CircleContentDetail user not exist :" . $postId);
    //   return $this->error($code = ErrCode::$enums['ERR_CIRCLE_CONTENT_NOT_EXIST']);
    // }

    $circleInfo = CircleUtil::GetCircleInfo($circle_content['circel_id'], $uid);
    // $circleInfo['lord'] = $uid == $circleInfo['owner'] ? true : false;
    if ($circleInfo) {
      unset($circleInfo['owner']);
    }
    if (!$circleInfo) {
      return $this->error($code = ErrCode::$enums['ERR_CIRCLE_CONTENT_NOT_EXIST']);
    }

    $circle_content_detail = CircleUtil::QueryCircleContentDetail($circle_content, $uid, $err_code);
    if (!$circle_content_detail) {
      Log::error("CircleContentDetail circle_content_detail not exist:" . $postId);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    $circle_content_detail['circleInfo'] = $circleInfo;
    $circle_content_detail['shareInfo'] = [
      'title' => $circle_content_detail['content'],
      'desc' => $circle_content_detail['content'],
      'imageUrl' => $circle_content_detail['userInfo']['icon'],
      'url' => config('app.shared_url.circle') . "?circleId=" . $circle_content['circel_id'],
    ];

    // 活跃统计
    CircleUtil::DataStatActive($uid, $circle_content['circel_id']);

    return $this->success([
      'head' => $circle_content_detail,
    ]);
  }

  // 圈子详情页帖子列表
  public function CircleDetailPostList() {
    $uid = (int) $this->getParams('uid');
    $circleId = (int) $this->getParams('circleId');
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 10); // 默认 25
    $sortType = $this->getParams('sortType', 0); // 默认 25

    $circie_detail = CircleUtil::GetCircleDetail($uid, [$circleId], $page, $limit, $sortType);

    return $this->success([
      'hasMore' => $circie_detail['hasMore'],
      'cmList'  => $circie_detail['cmList'],
    ]);
  }

  // 评论列表
  public function CommentList() {
    Log::info("req:" , $this->getParams());
    $uid = (int) $this->getParams('uid');
    $postId = (int) $this->getParams('postId');
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 25); // 默认 25

    if (!$uid || !$postId) {
      Log::info("CommentList err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    $circle_content = CircleUtil::QueryCircleContent($postId);
    if (!$circle_content) {
       Log::error("CommentList circle_content not exist :" . $postId);
       return $this->error($code = ErrCode::$enums['ERR_CIRCLE_CONTENT_NOT_EXIST']);
    }
    $comment_list = CircleUtil::CommentList($postId, $uid, $page, $limit);
    return $this->success($comment_list);
  }

  // 发帖接口
  public function PublishContent() {
    $uid = (int) $this->getParams('uid');
    $circleId = (int) $this->getParams('circleId');
    $content = $this->getParams('content', "");
    if (mb_strlen($content,"utf-8") > 800) $content = mb_substr($content, 0, 800, 'utf-8');
    $attachments = $this->getParams('attachments', '');

    if (!$uid || !$circleId) {
      Log::info("PublishContent err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    if (!$content && !$attachments) {
      Log::info("PublishContent content and attachments null");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    // 检查附件
    if ($attachments) {
      $attachments_array = json_decode($attachments, true);
      foreach($attachments_array as $node) {
        if(!isset($node['originalUrl']) ||!isset($node['thumbUrl'])
          ||!isset($node['width']) || !isset($node['height'])) {
            Log::info("attachments err param");
            return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
          }
      }
    }

    $circle_info = CircleUtil::GetCircleInfo($circleId);
    if (!$circle_info) {
      Log::error("PublishContent circle not exist :" . $circleId);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }

    if($uid != $circle_info['owner'] && $circle_info['publishPermission'] != 0) {
      // 检查圈子权限
      if ($circle_info['publishPermission'] != 0) {
        Log::error("PublishContent user has no permission:" . $uid);
        return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
      }
      // 检查用户权限
      $circel_id_list = CircleUtil::GetUserjoinCircleId($uid, $circleId);
      if (!$circel_id_list) {
        Log::error("Comment user has no permission:" . $uid);
        return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
      }
    }

    // 查询用户权限
    $circle_user_status = CircleUtil::GetCircleUserInfo($circleId, $uid);
    if (!$circle_user_status) {
      Log::error("GetCircleUserInfo not find");
      return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
    }

    // 进圈未批准
    if ($circle_user_status['user_status'] == 0) {
      Log::error("circle:" . $circleId . " uid:" . $uid . " has not join circle");
      return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
    }

    // 进圈未批准
    if ($circle_user_status['user_status'] == 2) {
      Log::error("circle:" . $circleId . " uid:" . $uid . " has Banned");
      return $this->error($code = ErrCode::$enums['ERR_CIRCLE_BANNEN']);
    }

    $id = CircleUtil::PublishContent($uid, $circleId, $content, $attachments);
    // 圈主发帖，全员push
    if ($id && ($uid == $circle_info['owner'])) {
      $circle_user_list = CircleUtil::GetCircleUserId($circleId);
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

    // 更新圈子最后发帖时间
    if ($id) {
      CircleUtil::UpdateCircleLastPostTime($circleId);
      CircleUtil::DataStatPost($circleId, $uid == $circle_info['owner']);
    }
    return $this->success(['post_id' => $id]);
  }

  // 评论回复
  public function Comment() {
    $uid = (int) $this->getParams('uid');
    $circleId = (int) $this->getParams('circleId');
    $postId = $this->getParams('postId', 0);
    $content = $this->getParams('content');
    if (mb_strlen($content,"utf-8") > 300) $content = mb_substr($content, 0, 300, 'utf-8');
    // for reply
    $commentId = (int) $this->getParams('commentId');
    $reply_uid = (int) $this->getParams('replyObjectUid', 0);
    $reply_id = (int) $this->getParams('replyObjectCommentId', 0);

    if (!$uid || !$circleId || !$postId || !$content) {
      Log::info("Comment err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    $circle_info = CircleUtil::GetCircleInfo($circleId);
    if (!$circle_info) {
       Log::error("Comment circle not exist :" . $circleId);
       return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    // 检查用户权限
    $circel_id_list = CircleUtil::GetUserjoinCircleId($uid, $circleId);
    if (!$circel_id_list) {
      Log::error("Comment user has no permission:" . $uid);
      return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
    }

    // 查询用户权限
    $circle_user_status = CircleUtil::GetCircleUserInfo($circleId, $uid);
    if (!$circle_user_status) {
      Log::error("GetCircleUserInfo not find");
      return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
    }

    // 进圈未批准
    if ($circle_user_status['user_status'] == 0) {
      Log::error("circle:" . $circleId . " uid:" . $uid . " has not join circle");
      return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
    }

    // 进圈未批准
    if ($circle_user_status['user_status'] == 2) {
      Log::error("circle:" . $circleId . " uid:" . $uid . " has Banned");
      return $this->error($code = ErrCode::$enums['ERR_CIRCLE_BANNEN']);
    }

    $content_id = $commentId == 0 ? $postId : $commentId; //
    $id = CircleUtil::Comment($uid, $circleId, $content_id, $content, $reply_uid, $commentId);
    if ($id) {
      // 添加回复通知
      $source_content_id = $reply_id == 0 ? $content_id : $reply_id;
      $content_info = CircleUtil::QueryCircleContent($source_content_id);
      if ($content_info && $content_info['user_id'] != $uid) {
        CircleUtil::AddNotify($circleId, $postId, $source_content_id, $content_info['user_id'], $id, $uid);
      }
    }
    // push 通知
    if ($id) {
      // 主贴评论
      if($commentId == 0 && $reply_uid == 0) {
        CircleUtil::MakeCommentPush($uid, $postId, $content);
      }
      // 评论
      else if ($commentId && $reply_id == 0) {
        CircleUtil::MakeCommentPush($uid, $commentId, $content);
      }
      // 回复
      else if ($reply_id && $commentId) {
        CircleUtil::MakeReplyPush($uid, $reply_id, $reply_uid, $content);
      }
    }

    // 更新圈子最后发帖时间
    if ($id) {
      CircleUtil::UpdateCircleLastPostTime($circleId);
      // 数据统计
      CircleUtil::DataStatComment($circleId, $uid == $circle_info['owner']);
    }
    return $this->success(['comment_id' => $id]);
  }

  // 删除帖子
  public function DelContent() {
    $uid = (int) $this->getParams('uid');
    $circleId = (int) $this->getParams('circleId');
    $commentId = (int) $this->getParams('commentId');
    $postId = (int) $this->getParams('postId');

    if (!$uid || !$circleId || (!$commentId && !$postId)) {
      Log::info("DelContent err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    $circle_info = CircleUtil::GetCircleInfo($circleId);
    if (!$circle_info) {
       Log::error("DelContent circle not exist :" . $circleId);
       return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    $content_id = $postId ? $postId : $commentId;

    $circle_content = [];
    if ($circle_info['owner'] != $uid) {
      // 检查用户权限
      $circle_content = CircleUtil::QueryCircleContent($content_id);
      if (!$circle_content) {
        Log::error("DelContent not exist:" . $content_id);
        return $this->error($code = ErrCode::$enums['ERR_CIRCLE_CONTENT_NOT_EXIST']);
      }
      if ($uid != $circle_content['user_id']) {
        return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
      }
    }
    if (CircleUtil::DelContent($content_id) > 0 && $circle_content) {
      CircleUtil::DelNotify($circle_content['circel_id'], $uid, $content_id);
    }
    return $this->success();
  }

  // 帖子置顶
  public function postPermission() {
    $uid = (int) $this->getParams('uid');
    $postId = (int) $this->getParams('postId');
    $topone = (int) $this->getParams('topone', -1);
    $addFine = (int) $this->getParams('addFine', -1);

    if (!$uid || !$postId) {
      Log::info("postPermission err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    $content_info = CircleUtil::QueryCircleContent($postId);
    if (!$content_info) {
      Log::info("postPermission contetn not exist");
      return $this->error($code = ErrCode::$enums['ERR_CIRCLE_CONTENT_NOT_EXIST']);
    }

    $circle_info = CircleUtil::GetCircleInfo($content_info['circel_id']);
    if (!$circle_info) {
      Log::error("postPermission circle not exist :" . $content_info['circel_id']);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    if ($uid != $circle_info['owner']) {
      Log::error("postPermission ERR_NO_PERMISSION user:" . $uid);
      return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
    }

    if ($topone != -1) {
      $ret = CircleUtil::ChangeContent($postId, $topone);
      if ($ret) {
        if ($uid != $content_info['user_id']) {
          CircleUtil::AddNotify($content_info['circel_id'], $postId, $postId, $content_info['user_id'], 0, 0, 2);
        }
      }
    }
    if ($addFine != -1) {
      $ret = CircleUtil::SetContentFine($postId, $addFine);
      if ($ret) {
        if ($uid != $content_info['user_id']) {
          CircleUtil::AddNotify($content_info['circel_id'], $postId, $postId, $content_info['user_id'], 0, 0, 3);
        }
      }
    }
    return $this->success();
  }

  // 圈子搜索
  public function searchCircle() {
    $uid = (int) $this->getParams('uid');
    $searchKey = $this->getParams('searchKey', "");
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 10); // 默认 25

    if (!$uid || !$searchKey) {
      Log::info("CircleDetail err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $res = CircleUtil::searchCircle($uid, $page, $limit, $searchKey);
    return $this->success($res);
  }

  // 帖子搜索
  public function searchPost() {
    $uid = (int) $this->getParams('uid');
    $searchKey = $this->getParams('searchKey', "");
    $circleId = $this->getParams('circleId');
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 10); // 默认 25

    if (!$uid || !$searchKey ||!$circleId) {
      Log::info("CircleDetail err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $res = CircleUtil::searchPost($uid, $circleId, $page, $limit, $searchKey);
    return $this->success($res);
  }

  // 圈子搜索
  public function hotCircleSearch() {
    $uid = (int) $this->getParams('uid');
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 4); // 默认 25

    // if (!$uid) {
    //   Log::info("CircleDetail err param");
    //   return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    // }
    $res = CircleUtil::hotCircleSearch($page, $limit);
    return $this->success($res);
  }

  // 圈子审核开关
  public function joinCirclePermission() {
    $uid = (int) $this->getParams('uid');
    $circleId  = (int) $this->getParams('circleId', 0);
    $joinLevel  = (int) $this->getParams('joinLevel', 0);

    if (!$uid || !$circleId) {
      Log::info("CircleDetail err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $circle_info = CircleUtil::GetCircleInfo($circleId);
    if (!$circle_info) {
      Log::error("joinCirclePermission circleId not exist:" . $circleId);
      return  $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    if ($circle_info['owner'] != $uid) {
      Log::error("joinCirclePermission owner:" . $circle_info['owner'] . " uid:" . $uid);
      return  $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
    }
    CircleUtil::joinCirclePermission($uid, $circleId, $joinLevel);
    return $this->success();
  }

  // 审核列表
  public function reviewList() {
    $uid = (int) $this->getParams('uid');
    if (!$uid) {
      Log::info("CircleDetail err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $res = CircleUtil::reviewList($uid);
    return $this->success($res);
  }

  // 审核列表
  public function review() {
    $uid = (int) $this->getParams('uid');
    $jsonStr =  $this->getParams('jsonStr');
    $operate =  $this->getParams('operate', 0);
    if (!$uid || !$jsonStr) {
      Log::info("review err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $jsonStr = json_decode($jsonStr, true);
    Log::info("review jsonStr:" , $jsonStr);

    // 检查圈子ID
    $circle_review_list = [];
    foreach($jsonStr as $node) {
      $circle_review_list[$node['circleId']][] = $node['uid'];
    }
    foreach($circle_review_list as $key => $value) {
      $circle_info = CircleUtil::GetCircleInfo($key);
      if (!$circle_info) {
        Log::info("circle not exiist");
        return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
      }
      if ($circle_info['owner'] != $uid) {
        return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
      }
    }
    CircleUtil::review($circle_review_list, $operate);
    return $this->success();
  }

  public function ProjectCircle() {
    $uid = (int) $this->getParams('uid');
    $projectid = $this->getParams('pid', 0);

    if (!$uid || !$projectid) {
      Log::info("ProjectCircle err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $res = CircleUtil::ProjectCircle($projectid);
    return $this->success($res);
  }

  public function circleUserInfo() {
    $uid = (int) $this->getParams('uid');
    $objId = (int) $this->getParams('objId');

    if (!$uid || !$objId) {
      Log::info("circleUserInfo err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $user_info_list = CircleUtil::GetUserInfo([$objId]);
    $data = [];
    if ($user_info_list) {
      $data = [
        'name' => $user_info_list[$objId]['name'],
        'icon' => $user_info_list[$objId]['avatar'],
        'uid' => $objId,
        'scheme' => $user_info_list[$objId]['scheme'],
      ];
    }
    return $this->success($data);
  }

  public function sortCircleList() {
    $uid = (int) $this->getParams('uid');
    $sortStr = $this->getParams('sortStr');

    if (!$uid || !$sortStr) {
      Log::info("sortCircleList err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $circle_list = json_decode($sortStr, true);
    $circle_list = array_map(function($v){
      return (int)$v;
    }, $circle_list);

    CircleUtil::sortCircleList($uid, $sortStr);
    return $this->success();
  }

  public function userPostList() {
    $uid = (int) $this->getParams('uid');
    $objId = $this->getParams('objId');
    $page  = (int) $this->getParams('pageNo', 0);
    $limit = (int) $this->getParams('pageSize', 10);

    if (!$uid || !$objId) {
      Log::info("sortCircleList err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $res = CircleUtil::GetUserPostList($objId, $page, $limit);
    return $this->success($res);
  }

  // 用户创建的圈子
  public function createCircleList() {
    $uid = (int) $this->getParams('uid');
    $objId = (int) $this->getParams('objId');

    if (!$uid) {
      Log::info("createCircleList err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $circel_list = CircleUtil::GetUserCreateCirle($objId);
    if (!$circel_list) return $this->success();
    foreach($circel_list as &$node) {
      $circle_user_list = CircleUtil::GetCircleUserId($node['id']);
      $members = empty($circle_user_list) ? 0 : count($circle_user_list);
      $node['members'] = $members;
    }
    usort($circel_list, function($a, $b){
      return $a['members'] < $b['members'];
    });
    foreach($circel_list as &$node) {
      if ($node['members'] > 1000) {
        $node['members'] = round($node['members'] / 1000, 2) . "K";
      }
    }
    return $this->success($circel_list);
  }

  // 设置圈子
  public function circleFeeSetting() {
    $uid = (int) $this->getParams('uid');
    $circleId = (int) $this->getParams('circleId');
    $fee = (float) $this->getParams('fee');

    if (!$uid || !$circleId || $fee < 0.0) {
      Log::info("circleFeeSetting err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    $cirlce = CircleUtil::GetCircleById($circleId);
    if (!$cirlce) {
      Log::info("circleFeeSetting circle has exist:" . $circleId);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }

    if ($cirlce['owner'] != $uid) {
      return $this->error($code = ErrCode::$enums['ERR_NO_PERMISSION']);
    }

    if (abs($cirlce['amount'] - 0.0) < 0.01 && abs($fee - 0.0) > 0.01 &&
      $cirlce['join_switch'] && CircleUtil::GetCircleViewNum($circleId) > 0) {
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_HAS_JOIN_CIRCLE_USER']);
    }

    //  产品让不限制
    // if (time() - $cirlce['modify_amount_time'] < 60) {
    //   Log::info("modify circle amount frequently");
    //   return $this->error($code = ErrCode::$enums['ERR_WX_PAY_TOO_MANY_TIMES']);
    // }

    $ret = CircleUtil::SetCirclePay($cirlce, $fee);
    if ($ret != 0) {
      Log::info("SetCirclePay failed");
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_SYS_ERRROR']);
    }
    return $this->success();
  }

  // 下单接口
  public function circleFee() {
    $uid = (int) $this->getParams('uid');
    $circleId = (int) $this->getParams('circleId');
    $code = $this->getParams('code', "");
    $openid = $this->getParams('openid', "");

    if (!$uid || !$circleId) {
      Log::info("circleFee err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }

    $cirlce = CircleUtil::GetCircleById($circleId);
    if (!$cirlce) {
      Log::info("circleFee circle has not exist:" . $circleId);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }

    if ($cirlce['pay'] != 1) {
      Log::error("cirlce id:" . $cirlce['id'] . " is free");
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    if (abs($cirlce['amount'] - 0.0) < 0.01) {
      Log::error("cirlce id:" . $cirlce['id'] . " amount is 0:" . $cirlce['amount']);
      return $this->error($code = ErrCode::$enums['ERR_NOT_EXIST']);
    }
    // 生成订单
    $order_id = CircleUtil::MkWxOrder($circleId, $cirlce['owner'],
      $uid, $cirlce['amount'], $code, $openid);
    if (!$order_id) {
      Log::info("MkWxOrder failed");
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_SYS_ERRROR']);
    }
    $res = CircleUtil::AppPayUnifiedOrder($order_id, $cirlce['amount'],
      $cirlce['name'], $code, $openid);
    if (!$res) {
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_SYS_ERRROR']);
    }
    Log::info("AppPayUnifiedOrder res:" . json_encode($res));
    return $this->success($res);
  }

  public function circleFeeUpdate() {
    $uid = (int) $this->getParams('uid');
    $prepayid =  $this->getParams('prepayid');

    if (!$uid || !$prepayid) {
      Log::info("circleFeeUpdate err param");
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    // $prepayid = DbConnect::getInstance()->escape($prepayid);

    $order_detail = CircleUtil::GetPayOrdedr($prepayid);

    if (!$order_detail) {
      Log::info("circleFeeUpdate order_id not exist");
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_ORDER_NOT_EXIST']);
    }

    if ($order_detail['user_id'] != $uid) {
      Log::info("circleFeeUpdate order_id uid is not :" . $uid);
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_ORDER_NOT_EXIST']);
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
    // join cirle
    CircleUtil::JoinPayCircle($order_detail['circel_id'], $uid);
    // 圈主收到用户支付通知
    CircleUtil::AddPayNotify($order_detail['circel_id'], $order_detail['owner'],
      $prepayid, 7);
    return $this->success();
  }

  // 交易列表
  public function transactionList() {
    $uid = (int) $this->getParams('uid');
    $page  = (int) $this->getParams('pageNo', 0);
    $limit = (int) $this->getParams('pageSize', 10);

    if (!$uid) {
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $res = CircleUtil::GetPayList($uid, $page, $limit);
    return $this->success($res);
  }

  // 订单详情
  public function transactionDetails() {
    $uid = (int) $this->getParams('uid');
    $orderNo = $this->getParams('orderNo');

    if (!$uid || !$orderNo) {
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $res = CircleUtil::GetPayDetail($uid, $orderNo);
    return $this->success($res);
  }

  // 订单详情
  public function walletInfo() {
    $uid = (int) $this->getParams('uid');

    if (!$uid) {
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $res = CircleUtil::GetUserWalletInfo($uid);
    return $this->success($res);
  }

  // 提现
  public function withdraw() {
    $uid = (int) $this->getParams('uid');
    $amount = $this->getParams('amount', 0.0);

    if (!$uid || $amount < config('app.wx_pay.withdraw_amount') || $amount > 2000.0) {
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $user_real_list = CircleUtil::GetRealUserInfo([$uid]);
    if (!$user_real_list) {
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    $user_info = $user_real_list[$uid];
    if (!$user_info['real_name'] || !$user_info['id_number']) {
      Log::error("uid:" . $uid . " real name or id_number is null");
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_NOT_VERIFIED']);
    }
    if (!$user_info['openid']) {
      Log::error("uid:" . $uid . " not bind wx");
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_NOT_BIND_WX']);
    }
    /*
     *  1 检查是否有未完成的提现订单
     *  2 检查金额
     *  3 生成提现订单
     * */
    $order = CircleUtil::QueryUserUnDoWithdrawOrder($uid);
    if ($order) {
      Log::info("withdraw uid:" . $uid . " has undone WithdrawOrder");
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_HAS_WITHDIAW_ORDER']);
    }
    // 检查当天是否有提现
    $withdraw_num = CircleUtil::GetUserCurrDayWithdraw($uid);
    if ($withdraw_num > 0) {
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_WITHDRAW_ERR_MSG']);
    }
    $walletInfo = CircleUtil::GetUserWalletInfo($uid);
    if ($walletInfo['withdrawAmount'] < $amount) {
      Log::info("withdraw uid:" . $uid . " balance:" . $walletInfo['withdrawAmount'] . " withdraw amount:" . $amount);
      return $this->error($code = ErrCode::$enums['ERR_WX_PAY_NOT_ENOUGHT_BALANCE']);
    }
    // 创建提现订单
    $res = CircleUtil::CreateWithdrawOrder($uid, $amount);
    if (!$res) {
       return $this->error($code = ErrCode::$enums['ERR_WX_PAY_SYS_ERRROR']);
    }
    // 发短信通知审核
    $record = [
      'type' => 3,
      'title' => "",
      'content' => "有提现申请，请及时处理",
      'tags' => config('app.push_notify'),
      'scheme' => "",
      'extras' => json_encode(['type' => 2]),
    ];
    CircleUtil::WritePush($record);

    CircleUtil::SendSms('提现申请，请及时处理');
    return $this->success();
  }

  // 收藏
  public function favoritePost() {
    $uid = (int) $this->getParams('uid');
    $circleId = (int)$this->getParams('circleId');
    $postId = (int)$this->getParams('postId');
    $opt = (int)$this->getParams('opt', 0);

    if (!$uid || !$postId) {
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    
    $list = CircleUtil::QueryUserFavoritePost($uid);
    if (0 == $opt) {
      array_unshift($list, $postId);
      $list = array_unique($list);
    } else {
      $key = array_search($postId, $list);
      if ($key >= 0) unset($list[$key]);
    }
    CircleUtil::UpdateUserFavoritePost($uid, $list);
    return $this->success();
  }

  // 获取收藏
  public function favoritePostList() {
    $uid = (int) $this->getParams('uid');
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 10); // 默认 25
    if (!$uid) {
      return $this->error($code = ErrCode::$enums['ERR_PARARMER']);
    }
    // $res = ['hasMore' => false, 'list' => []];
    $list = CircleUtil::QueryUserFavoritePost($uid);
    if (!$list) return $this->success();
    if (count($list) < $page * $limit) return $this->success();

    $ret_list = array_slice($list, $page * $limit, $limit);

    if (!$ret_list) return $this->success();

    $res = CircleUtil::GetUserFavoritePostListDetail($ret_list);
    return $this->success([
      'hasMore' => count($list) > ($page + 1) * $limit,
      'list' => $res,
    ]);
  }

  // 获取收藏
  public function rankingList() {
    $uid = (int) $this->getParams('uid');
    $type  = (int) $this->getParams('type');
    $page  = (int) $this->getParams('pageNo', 0);    // 0 开始
    $limit = (int) $this->getParams('pageSize', 10); // 默认 25
    $list = CircleUtil::GetRankCircle($uid, $type, $page, $limit);
    return $this->success($list);
  }

}
