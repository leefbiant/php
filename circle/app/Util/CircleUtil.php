<?php
namespace App\Util;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as Client;
use Illuminate\Support\Facades\Redis;
use App\Util\Common;
use App\Util\DbConnect;


class CircleUtil
{
  public function __construct()
  {
    date_default_timezone_set('Asia/Shanghai');
  }

  static public function getMillisecond() { 
    list($s1, $s2) = explode(' ', microtime()); 
    return (int)((floatval($s1) + floatval($s2)) * 1000); 
  }

  //  获取用户信息
  static public function GetUserInfo($uid_list) {
    $t1 = microtime(true);
    if (!$uid_list) {
      Log::info("GetUserInfo input is null array");
      return [];
    }
    $url = config('app.bbex_user_info_interface');
    $response = (new Client())->request('GET', $url, ['query' => ['uid_list' => $uid_list]]);
    $contents = json_decode($response->getBody()->getContents(), true);

    $t2 = microtime(true);
    Log::info("GetUserInfo time cost:" . (($t2-$t1)*1000));
    foreach($contents['data'] as $kye => &$val) {
      $val['scheme'] = "bbex://circleUserInfo?objId=" . $val['uid'];
    }
    foreach($uid_list as $uid) {
      if(!isset($contents['data'][$uid])) {
        $contents['data'][$uid] = [
          'uid' => $uid,
          'name' => (string)$uid,
          'avatar' => "",
          'scheme' => "bbex://circleUserInfo?objId=" . $uid,
        ];
      }
    }
    return $contents['data'];
  }

  // 发送短信
  static public function SendSms($content) {
    $url = config('app.sms_notify_addr');
    $form_params = [
      'bzId' => 'taskWarning',
      'phoneNumber' => implode(",", config('app.sms_notify_num')),
      'details' => $content,
    ];
    $response = (new Client())->post($url, ['form_params' => $form_params]);
  }

  static public function GetRealUserInfo($uid_list) {
    if (!$uid_list) {
      Log::info("GetUserInfo input is null array");
      return [];
    }
    $url = config('app.bbex_real_user_info_interface');
    $response = (new Client())->request('GET', $url, ['query' => ['uid_list' => $uid_list]]);
    $contents = json_decode($response->getBody()->getContents(), true);

    foreach($uid_list as $uid) {
      if(!isset($contents['data'][$uid])) {
        $contents['data'][$uid] = [
          'uid' => $uid,
          'name' => (string)$uid,
          'avatar' => "",
          'scheme' => "bbex://circleUserInfo?objId=" . $uid,
          'real_name' => "",
          'id_number' => "",
          'openid' => "",
        ];
      }
    }
    return $contents['data'];
  }

  // 获取用户加入圈子
  static public function GetUserjoinCircleId($uid, $circel_id = 0) {
    $result = "";
    if ($circel_id) {
      $result = DbConnect::getInstance()->select('select circel_id from circle_user
        where circel_id = :circel_id and user_id = :user_id and user_status > 0 and circel_id in (select id from circle_info where status = 0)',
        ['circel_id' => $circel_id, 'user_id' => $uid]);
    } else {
      $result = DbConnect::getInstance()->select('select circel_id from circle_user
        where user_id = :user_id and user_status > 0 and circel_id in (select id from circle_info where status = 0)',
        ['user_id' => $uid]);
    }
    $circel_id_list = [];
    foreach($result as $node) {
      $circel_id_list[] = $node->circel_id;
    }
    // 按圈子最新发帖 排序
    if (count($circel_id_list) > 1) {
      $circel_id_list =  implode(",", $circel_id_list);
      $sql = "select id from circle_info where id in (" . $circel_id_list . ") order by last_post_time desc";
      $result = DbConnect::getInstance()->select($sql);
      unset($circel_id_list);
      foreach($result as $node) {
        $circel_id_list[] = $node->id;
      }
    }
    return $circel_id_list;
  }


  // 获取用户加入圈子状态
  static public function GetUserjoinCircleStatus($uid) {
    $result = "";
    $result = DbConnect::getInstance()->select('select circel_id, user_status from circle_user
      where user_id = :user_id and circel_id in (select id from circle_info where status = 0)',
        ['user_id' => $uid]);
    $circel_id_list = [];
    foreach($result as $node) {
      $circel_id_list[$node->circel_id] = $node->user_status;
    }
    return $circel_id_list;
  }


  // 获取圈子信息
  static public function GetCircleInfo($circle_id, $uid = 0) {
    $circel_info = [];
    $circel_obj = DbConnect::getInstance()->select('select name, summary, image, owner, permission,
      project_id, project_name, valuation, review_switch, review_switch, pay, attr
      from circle_info where id = :id and status = 0 limit 1',
      ['id' => $circle_id]);
    if ($circel_obj) {
      $circel_obj = $circel_obj[0];
      $image = $circel_obj->image;
      if (!empty($image) && 0 != strcmp("http", substr($image, 0, 4))) {
        $image = config('app.image_host') . $image;
      }

      $circel_info = [
        'name' => $circel_obj->name,
        'desc' => $circel_obj->summary,
        'icon' => $image,
        'circleId' => $circle_id,
        'owner' => $circel_obj->owner,
        'lordUid' => $circel_obj->owner,
        'publishPermission' => $circel_obj->permission,
        'valuation' => $circel_obj->valuation,
        'join_switch' => $circel_obj->review_switch,
        'pay' => $circel_obj->pay,
        'attr' => $circel_obj->attr,
        'scheme' => "bbex://circleDetail?circleId=" . $circle_id,
      ];
      // 关联项目
      if ($circel_obj->project_id && $circel_obj->project_name) {
        $circel_info['tags'][] = [
          'name' => $circel_obj->project_name,
          'scheme' => "bbex://projectDetail?pid=" . $circel_obj->project_id,
        ];
      }

      if ($uid) {
        $circel_id_list = CircleUtil::GetUserjoinCircleId($uid, $circle_id);
        if ($circel_id_list) {
          $circel_info['joined'] = true;
        } else {
          $circel_info['joined'] = false;
        }
      }
    }
    return $circel_info;
  }

  static public function GetCircleUserStat($uid, $circle_id) {
    $circel_info = [];
    $circel_obj = DbConnect::getInstance()->select('select name, summary, image, owner, permission,
      project_id, project_name, valuation, review_switch, review_switch, pay, amount
      from circle_info where id = :id and status = 0 limit 1',
      ['id' => $circle_id]);
    if ($circel_obj) {
      $circel_obj = $circel_obj[0];
      $image = $circel_obj->image;
      if (!empty($image) && 0 != strcmp("http", substr($image, 0, 4))) {
        $image = config('app.image_host') . $image;
      }

      $circel_info = [
        'name' => $circel_obj->name,
        'desc' => $circel_obj->summary,
        'icon' => $image,
        'circleId' => $circle_id,
        'owner' => $circel_obj->owner,
        'lordUid' => $circel_obj->owner,
        'publishPermission' => $circel_obj->permission,
        'valuation' => $circel_obj->valuation,
        'join_switch' => $circel_obj->review_switch,
        'scheme' => "bbex://circleDetail?circleId=" . $circle_id,
      ];
      // 关联项目
      if ($circel_obj->project_id && $circel_obj->project_name) {
        $circel_info['tags'][] = [
          'name' => $circel_obj->project_name,
          'scheme' => "bbex://projectDetail?pid=" . $circel_obj->project_id,
        ];
      }

      // 用户在圈子中状态
      if ($uid) {
        $sql = sprintf("select user_status from circle_user
          where circel_id = %d and user_id = %d", $circle_id, $uid);
        $result = DbConnect::getInstance()->select($sql);
        $circel_info['status'] = -1;
        $circel_info['joined'] = false;
        if ($result) {
          $circel_info['status'] = $result[0]->user_status;
          $circel_info['joined'] = $result[0]->user_status > 0;
        }
      }

      $circel_info['feeInfo'] = [
        'isOpenFee' => $circel_obj->pay ? true : false,
        'fee' => $circel_obj->amount,
        'desc' => sprintf(config('app.wx_pay.circle_info_desc'),$circel_obj->name),
        'placeholder' => config('app.wx_pay.placeholder'),
      ];
    }
    return $circel_info;
  }


  // 批量获取圈子信息
  static public function BatchGetCircleInfo($circle_id_list) {
    $circel_info = [];
    if (!$circle_id_list) return $circel_info;
    try {
      $cirlce_id_list =  implode(",", $circle_id_list);
      $sql = "select id, name, summary, image, owner, permission
        from circle_info where id in (" . $cirlce_id_list . ")";
      $circel_obj = DbConnect::getInstance()->select($sql);
      foreach ($circel_obj as $node) {
        $image = $node->image;
        if (!empty($image) && 0 != strcmp("http", substr($image, 0, 4))) {
          $image = config('app.image_host') . $image;
        }

        $circel_info[$node->id] = [
          'name' => $node->name,
          'desc' => $node->summary,
          'icon' => $image,
          'circleId' => $node->id,
          'owner' => $node->owner,
          'lordUid' => $node->owner,
          'permission' => $node->permission,
          'scheme' => "bbex://circleDetail?circleId=" . $node->id,
      ];
      }
    } catch (\Exception $e) {
      Log::error("BatchGetCircleInfo Exception-----" . $e->getMessage());
    }
    return $circel_info;
  }


  // 获取圈子成员
  static public function GetCircleUserId($circle_id, $newmember = 0) {
    try {
      $circel_obj = [];
      if ($newmember) {
        $circel_obj = DbConnect::getInstance()->select('select user_id from circle_user
          where circel_id = :circel_id and user_status > 0 and has_check = 0',
          ['circel_id' => $circle_id]);
      } else {
        $circel_obj = DbConnect::getInstance()->select('select user_id from circle_user
          where circel_id = :circel_id and user_status > 0',
          ['circel_id' => $circle_id]);
      }

      $circle_user_list = [];
      foreach($circel_obj as $node) {
        $circle_user_list[] = $node->user_id;
      }
      return $circle_user_list;
    } catch (\Exception $e) {
      Log::error("CreateCircle Exception-----" . $e->getMessage());
    }
    return null;
  }

  static public function GetCircleDetail($uid, $circel_id_list, $page, $limit, $sortType) {
    $num = $limit + 1;
    $circel_id_list =  implode(",", $circel_id_list);
    $page = $limit * $page;
    $sql = "select id, circel_id, user_id, content, coretext, attachment, top, essence,
      unix_timestamp(created_at) as ts
      from circle_content where circel_id in (" . $circel_id_list . ") and post_id = 0
      and status = 0";
    if ($sortType) {
      $sql = $sql . " and essence > 0 ";
    }
    $sql = $sql . " order by top desc, created_at desc limit " . $page . "," . $num;
    Log::info("sql:" . $sql);
    $circel_content_list = DbConnect::getInstance()->select($sql);

    $user_id_list = [];
    $circle_list = [];
    foreach($circel_content_list as $node) {
      $user_id_list[] = $node->user_id;
      $circle_list[] = $node->circel_id;
    }

    $user_id_list = array_unique($user_id_list);
    $circle_list = array_unique($circle_list);

    // $user_arry = CircleUtil::GetUserInfo($user_id_list);

    $circle_info_list = [];
    foreach($circle_list as $node) {
      $circleInfo = CircleUtil::GetCircleInfo($node, $uid);
      if ($circleInfo) {
        $circle_info_list[$node] = $circleInfo;
      }
    }

    $list = [];
    $index = 0;
    foreach($circel_content_list as $node) {
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
      $comments = CircleUtil::GetCommentWithoutUser($node->id);

      $res_data = [
        'circleId' => $node->circel_id,
        'postId' => $node->id,
        'content' => $node->content,
        'coretext' => $node->coretext ? $node->coretext  : "",
        'ts' => $node->ts,
        'pariseCount' => $parise['pariseCount'],
        'isParised' => $parise['isParised'],
        'commentCount' => $commentCount,
        'uid' => $node->user_id,
        'tags' => $tags,
        'attachments' => $attachment,
        'circleInfo' => $circleInfo,
        'comments' => $comments,
        "scheme" => "bbex://postDetail?postId=" . $node->id,
        "shareInfo" => [
          'url' => config('app.shared_url.circle') . "?circleId=" . $node->circel_id,
        ],
      ];
      $list[] = $res_data;
    }

    foreach($list as &$node) {
      CircleUtil::CircleContentFilter($node);
    }

    // 添加分享圈子信息
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
    }

    // 添加用户信息
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
    return [
      'hasMore' => $hasMore,
      'cmList'  => $list,
    ];
  }

  static public function GetCommentWithoutUser($content_id, $num = 6) {
    $content_obj = DbConnect::getInstance()->select('select id, circel_id, user_id,
      reply_uid, content, coretext
      from circle_content
      where post_id = :post_id and status = 0
      order by top desc, created_at asc limit :num',
      ['post_id' => $content_id, 'num' => $num]);

    if (!$content_obj) {
      return [];
    }

    $limit = $num;
    $uid_list = [];
    $reply_id_list = [];
    foreach($content_obj as $node) {
      $uid_list[] = $node->user_id;
      if ($node->reply_uid) {
        $uid_list[] = $node->reply_uid;
      }
      $reply_id_list[] = $node->id;
    }
    $reply_list = [];
    if ($num > 1) {
      $reply_list = CircleUtil::GetReplyList($reply_id_list);
      if ($reply_list) {
        foreach($reply_list as $reply_array) {
          foreach($reply_array as $reply_node) {
            $uid_list[] = $reply_node['uid'];
            if (!empty($reply_node['reply_uid'])) {
              $uid_list[] = $reply_node['reply_uid'];
            }
          }
        }
      }
    }

    $comments_arry = [];
    $list = [];
    $index = 0;
    foreach($content_obj as $node) {
      if (++$index > $limit) break;
      $comments = [
        'content' => $node->content,
        'coretext' => $node->coretext ? $node->coretext  : "",
        'uid' => $node->user_id,
        'commentId' => $node->id,
        'postId' => $content_id,
        'circleId' => $node->circel_id,
        'rootCommentId' => $node->id,
        'scheme' => "bbex://circleUserInfo?objId=" . $node->user_id,
      ];
      $comments_arry[] = $comments;

      if ($reply_list && isset($reply_list[$node->id])) {
        $replyList = $reply_list[$node->id];
        $replyCount = count($reply_list[$node->id]);
        foreach ($replyList as &$obj) {
          $obj['uid'] = $obj['uid'];
          $obj['commentId'] = $obj['id'];
          $obj['postId'] = $content_id;
          $obj['isReply'] = true;
          $obj['scheme'] = "bbex://circleUserInfo?objId=" . $obj['uid'];
          if (!empty($obj['reply_uid'])) {
            $replyObjectInfo = [
              'uid' => $obj['reply_uid'],
              'scheme' => "bbex://circleUserInfo?objId=" . $obj['reply_uid'],
            ];
            $obj['replyObjectInfo'] = $replyObjectInfo;
            unset($obj['reply_uid']);
          }
          if (++$index > $limit) break;
          $comments_arry[] = $obj;
        }
      }
    }

    foreach($comments_arry as &$node) {
      CircleUtil::CircleContentFilter($node);
    }

    return $comments_arry;
  }

  static public function GetCommentNum2($circel_id, $content_id) {
    $sql = sprintf("select count(1) as count from circle_content
      where (circel_id = %d and post_id = %d and status = 0) or (status = 0 and post_id in (
        select id from circle_content where circel_id = %d and post_id = %d and status = 0))",
        $circel_id, $content_id, $circel_id, $content_id);
    $result = DbConnect::getInstance()->select($sql); 
    if ($result) return $result[0]->count;
    return 0;
  }

  // 回去点赞数
  static public function GetPariseNum($content_id, $user_id = 0) {
    // Log::info("content_id:" . $content_id . " user_id:" . $user_id);
    $pariseCount = 0;
    $isParised = false;
    $parise_obj = DbConnect::getInstance()->select('select count(1) as count from circle_praise where content_id = :content_id', ['content_id' => $content_id]);
    $pariseCount = $parise_obj[0]->count;

    if ($user_id) {
      $parise_obj = DbConnect::getInstance()->select('select user_id from circle_praise
        where content_id = :content_id and user_id = :user_id',
        ['content_id' => $content_id, 'user_id' => $user_id]);
      $isParised = count($parise_obj) > 0;
    }
    return [
      'pariseCount' => $pariseCount,
      'isParised' => $isParised,
    ];
  }

  // 查询圈子
  static public function GetCircleByName($name) {
    $obj = DbConnect::getInstance()->select('select id from circle_info
      where name = :name', ['name' => $name]);
    if ($obj) {
      return $obj[0]->id;
    }
    return null;
  }

  static public function GetCircleById($id) {
    $sql = sprintf("select id, name, owner, review_switch, attr, pay, amount, modify_amount_time from circle_info
      where id = %d", $id);
    $res = DbConnect::getInstance()->select($sql);
    if ($res) {
      foreach($res as $obj) {
        $data = [
          'id' => $obj->id,
          'name' => $obj->name,
          'owner' => $obj->owner,
          'join_switch' => $obj->review_switch,
          'attr' => $obj->attr,
          'pay' => $obj->pay,
          'amount' => $obj->amount,
          'modify_amount_time' => $obj->modify_amount_time,
        ];
      }
      return $data;
    }
    return null;
  }

  // 查询用户创建的圈子
  static public function GetUserCreateCirle($uid) {
    $obj = DbConnect::getInstance()->select('select id, name, image, summary from circle_info
      where owner = :owner and status = 0',
      ['owner' => $uid]);
    if ($obj) {
      $circle_list = [];
      foreach($obj as $node) {
        $image = $node->image;
        if (!empty($image) && 0 != strcmp("http", substr($image, 0, 4))) {
          $image = config('app.image_host') . $image;
        }

        $circle = [
          'id' => $node->id,
          'circleId' => $node->id,
          'name' => $node->name,
          'icon' => $image,
          'desc' => $node->summary,
          'scheme' => "bbex://circleDetail?circleId=" . $node->id,
        ];
        $circle_list[] = $circle;
      }
      return $circle_list;
    }
    return null;
  }
  // 创建圈子
  static public function CreateCircle($uid, $name, $logo, $desc, $fee) {
    Log::info("user:" . $uid . " create circle:" . $name);
    $id = 0;
    try {
      if ($fee > 0.01) {
        $id = DbConnect::getInstance()->table('circle_info')->insertGetId(
          ['creator' => $uid, 'owner' => $uid, 'image' => (string)$logo, 'name' => $name,
          'summary' => $desc, 'source' => 0, 'last_post_time' => time(),
          'attr' => 1, 'pay' => 1, 'amount' => $fee, 'modify_amount_time' => time(0),
          'created_at' => date("Y-m-d H:i:s",time()), 'updated_at' => date("Y-m-d H:i:s",time())]
        );
      } else {
        $id = DbConnect::getInstance()->table('circle_info')->insertGetId(
          ['creator' => $uid, 'owner' => $uid, 'image' => (string)$logo, 'name' => $name,
          'summary' => $desc, 'source' => 0, 'last_post_time' => time(),
          'created_at' => date("Y-m-d H:i:s",time()), 'updated_at' => date("Y-m-d H:i:s",time())]
        );
      }
      CircleUtil::JoinCircle($uid, $id, 1);
    } catch (\Exception $e) {
      Log::error("CreateCircle Exception-----" . $e->getMessage());
    }
    return $id;
  }

  // 加入圈子
  static public function JoinCircle($uid, $circle_id, $status = 0) {
    Log::info("--- user:" . $uid . " join circle:" . $circle_id);
    $id = 0;
    try {
      $id = DbConnect::getInstance()->table('circle_user')->insertGetId(
        ['circel_id' => $circle_id, 'user_id' => $uid, 'user_status' => $status,
        'created_at' => date("Y-m-d H:i:s",time()), 'updated_at' => date("Y-m-d H:i:s",time())]
      );
    } catch (\Exception $e) {
      Log::error("JoinCircle Exception-----" . $e->getMessage());
    }
    return $id;
  }

  static public function VirMemJoinCircle($uid, $circle_id, $status = 0, $user_type = 1) {
    Log::info("--- user:" . $uid . " join circle:" . $circle_id);
    $id = 0;
    try {
      $id = DbConnect::getInstance()->table('circle_user')->insertGetId(
        ['circel_id' => $circle_id, 'user_id' => $uid, 'user_status' => $status, 'user_type' => 1,
        'created_at' => date("Y-m-d H:i:s",time()), 'updated_at' => date("Y-m-d H:i:s",time())]
      );
    } catch (\Exception $e) {
      Log::error("JoinCircle Exception-----" . $e->getMessage());
    }
    return $id;
  }
  // 加入圈子
  static public function IgnoreJoin($circle_id, $uid, $status = 0) {
    Log::info("--- user:" . $uid . " IgnoreJoin circle:" . $circle_id);
    try {
      DbConnect::getInstance()->insert('INSERT IGNORE into circle_user
        (circel_id, user_id, user_status, created_at, updated_at)
        value(?,?,?,?,?)', [$circle_id, $uid, $status, date("Y-m-d H:i:s",time()), date("Y-m-d H:i:s",time())]);
    } catch (\Exception $e) {
      Log::error("JoinCircle Exception-----" . $e->getMessage());
    }
    return ;
  }

  // 编辑圈子
  static public function EditCircle($id, $name, $logo, $desc) {
    Log::info("--- edit circle:" . $id);
    $affected  = 0;
    try {
      $affected  = DbConnect::getInstance()->update('update circle_info set name = ?, image = ?, summary = ?
        where id = ? limit 1', [$name, (string)$logo, $desc, $id]);
    } catch (\Exception $e) {
      Log::error("EditCircle Exception-----" . $e->getMessage());
      return 0;
    }
    return 1;
  }

  // 退出圈子
  static public function QuitCircle($id, $uid) {
    Log::info("--- quit user:" . $uid . " circle :" . $id);
    try {
      // 删除圈子用户
      $sql = sprintf("delete from circle_user where circel_id = %d and user_id = %d limit 1", $id, $uid);
      $affected  = DbConnect::getInstance()->update($sql);

      // 删除圈子通知
      $sql = sprintf("delete from circle_notify where circle_id = %d and user_id = %d", $id, $uid);
      $affected  = DbConnect::getInstance()->update($sql);

    } catch (\Exception $e) {
      Log::error("QuitCircle Exception-----" . $e->getMessage());
      return 0;
    }
    return 1;
  }

  // 删除圈子
  static public function DelCircle($id) {
    Log::info("--- del circle :" . $id);
    try {
      $circle_user_list = CircleUtil::GetCircleUserId($id);
      // 更改圈子状态
      $affected  = DbConnect::getInstance()->update('update circle_info set status = 1
        where id = ? limit 1', [$id]);

      // 删除圈子用户
      $sql = sprintf("delete from circle_user where circel_id = %d", $id);
      Log::info("-- DelCircle del circle_user:" . $sql);
      $affected  = DbConnect::getInstance()->update($sql);

      // 删除圈子通知
      $sql = sprintf("delete from circle_notify where circle_id = %d", $id);
      Log::info("-- DelCircle del circle_notify:" . $sql);
      $affected  = DbConnect::getInstance()->update($sql);

      foreach($circle_user_list as $node) {
        CircleUtil::AddNotify($id, 0, 0, $node, 0, 0, 6);
      }
    } catch (\Exception $e) {
      Log::error("DelCircle Exception-----" . $e->getMessage());
      return 0;
    }
    return 1;
  }

  // 设置圈子权限
  static public function SetCirclePermission($id, $uid, $level) {
    Log::info("--- update circle Permission :" . $id);
    $affected = 0;
    try {
      $affected  = DbConnect::getInstance()->update('update circle_info set permission = ?
        where id = ? and owner = ? limit 1', [$level, $id, $uid]);
    } catch (\Exception $e) {
      Log::error("SetCirclePermission Exception-----" . $e->getMessage());
      return 0;
    }
    return $affected;
  }

  // 查看圈子成员
  static public function QueryCircleMembers($id, $owner, $uid = 0, $page, $limit) {

    try {
      $num = $limit + 1;
      $page = $limit * $page;
      $user_id_obj = DbConnect::getInstance()->select('select user_id, has_check, user_status
        from circle_user
        where circel_id = :circel_id and user_status < 4
        order by has_check asc, id desc limit :page, :limit',
        ['circel_id' => $id, 'page' => $page, 'limit' => $num]);
      $user_id_list = [];
      foreach($user_id_obj as $node) {
        $user_id_list[] = $node->user_id;
      }
      $user_id_list[] = $owner;

      $user_arry = CircleUtil::GetUserInfo($user_id_list);

      $hasMore = false;
      $index = 0;
      $newestMembers = [];
      $members = [];
      $members[] = [
        'name' => isset($user_arry[$owner]) ? $user_arry[$owner]['name'] : "",
        'uid' => $owner,
        'level' => 2,
        'avatar' => isset($user_arry[$owner]) ? $user_arry[$owner]['avatar'] : "",
        'scheme' => isset($user_arry[$owner]) ? $user_arry[$owner]['scheme'] : "",
      ];
      foreach($user_id_obj as $node) {
        if (++$index > $limit) break;
        if ($owner == $node->user_id) continue;
        $user_info = [
          'name' => isset($user_arry[$node->user_id]) ? $user_arry[$node->user_id]['name'] : "",
          'uid' => $node->user_id,
          'level' => $node->user_status == 2 ? 3 : ($node->has_check ? 0 : 1),
          'avatar' => isset($user_arry[$node->user_id]) ? $user_arry[$node->user_id]['avatar'] : "",
          'scheme' => isset($user_arry[$node->user_id]) ? $user_arry[$node->user_id]['scheme'] : "",
        ];
        $members[] = $user_info;
      }

      $circle_user_list = CircleUtil::GetCircleUserId($id);
      return [
        'hasMore' => $index > $limit,
        'list' => $members,
        'memberTotalCount' => empty($circle_user_list) ? 0 : count($circle_user_list),
      ];
    } catch (\Exception $e) {
      Log::error("QueryCircleMembers Exception-----" . $e->getMessage());
    }
    return null;
  }

  // 标记已经查看入圈用户
  static public function BeReviewCircleUser($id) {
    Log::info("--- update circle_user has_check :" . $id);
    $affected  = 0;
    try {
      $affected  = DbConnect::getInstance()->update('update circle_user set has_check = 1
        where circel_id = ?', [$id]);
    } catch (\Exception $e) {
      Log::error("BeReviewCircleUser Exception-----" . $e->getMessage());
      return 0;
    }
    return 1;
  }

  // 查询用户是否在圈子中
  static public function QueryCircleExistUser($id, $uid) {
    try {
      $user_id_obj = DbConnect::getInstance()->select('select user_id from circle_user
        where circel_id = :circel_id and user_id = :user_id',
        ['circel_id' => $id, 'user_id' => $uid]);
      // dd($user_id_obj);
      if ($user_id_obj) {
        return $user_id_obj[0]->user_id;
      }
    } catch (\Exception $e) {
      Log::error("QueryCircleMembers Exception-----" . $e->getMessage());
    }
    return null;
  }

  //  修改用户在圈子中权限
  static public function SetCircleUserPermission($id, $user_id, $permission) {
    Log::info("--- update clicle user status circle_id:" . $id . " user:" . $user_id . " user_status:" . $permission);
    try {
      if ($permission == Common::$enums['CIRCLE_USER_BLACK']) { // 禁言
        $sql = sprintf("update circle_user set user_status = 2 where circel_id = %d and user_id = %d limit 1", $id, $user_id);
        Log::info("-- SetCircleUserPermission sql:" . $sql);
        $affected  = DbConnect::getInstance()->update($sql);
      } else if ($permission == Common::$enums['CIRCLE_USER_KICK']) { // 踢人
        $sql = sprintf("delete from circle_user where circel_id = %d and user_id = %d limit 1", $id, $user_id);
        Log::info("-- SetCircleUserPermission sql:" . $sql);
        $affected  = DbConnect::getInstance()->update($sql);
      } else if ($permission == Common::$enums['CIRCLE_USER_CANCEL_BLACK']) {
        $sql = sprintf("update circle_user set user_status = 1 where circel_id = %d and user_id = %d limit 1", $id, $user_id);
        Log::info("-- SetCircleUserPermission sql:" . $sql);
        $affected  = DbConnect::getInstance()->update($sql);
      } else if ($permission == Common::$enums['CIRCLE_TRANSFRER']) {
        $sql = sprintf("update circle_info set owner = %d where id = %d limit 1", $user_id, $id);
        Log::info("-- SetCircleUserPermission sql:" . $sql);
        $affected  = DbConnect::getInstance()->update($sql);
      }
    } catch (\Exception $e) {
      Log::error("SetCircleUserPermission Exception-----" . $e->getMessage());
      return 0;
    }
    return 1;
  }

  //  查询一个帖子(主贴/评论)
  static public function QueryCircleContent($id) {
    try {
      $circle_content_obj = DbConnect::getInstance()->select('select circel_id, user_id, post_id, reply_uid, content, coretext,
        attachment, top, essence, unix_timestamp(created_at) as ts
        from circle_content
        where id = :id and status = 0 limit 1', ['id' => $id]);
      if ($circle_content_obj) {
        $content =  [
          'id' => $id,
          'circel_id' => $circle_content_obj[0]->circel_id,
          'user_id' => $circle_content_obj[0]->user_id,
          'post_id' => $circle_content_obj[0]->post_id,
          'reply_uid' => $circle_content_obj[0]->reply_uid,
          'content' => $circle_content_obj[0]->content,
          'coretext' => $circle_content_obj[0]->coretext ? $circle_content_obj[0]->coretext  : "",
          'attachment' => $circle_content_obj[0]->attachment,
          'top' => $circle_content_obj[0]->top,
          'essence' => $circle_content_obj[0]->essence,
          'ts' => $circle_content_obj[0]->ts,
        ];
        CircleUtil::CircleContentFilter($content);
        return $content;
      }
    } catch (\Exception $e) {
      Log::error("QueryCircleContent Exception-----" . $e->getMessage());
      return null;
    }
    return null;
  }

  // 批量查询帖子
  static public function BatchQueryCircleContent($id_list) {
    $content_list = [];
    if (!$id_list) return $content_list;
    try {
      $content_id_list =  implode(",", $id_list);

      $sql = "select id, circel_id, user_id, post_id, reply_uid, content, coretext, attachment, top,
        essence, unix_timestamp(created_at) as ts from circle_content where id in(" . $content_id_list . ")";

      $content_obj = DbConnect::getInstance()->select($sql);
      foreach($content_obj as $node) {
        $content_list[$node->id] = [
          'id' => $node->id,
          'circel_id' => $node->circel_id,
          'user_id' => $node->user_id,
          'post_id' => $node->post_id,
          'reply_uid' => $node->reply_uid,
          'content' => $node->content,
          'coretext' => $node->coretext,
          'attachment' => $node->attachment,
          'top' => $node->top,
          'essence' => $node->essence,
          'ts' => $node->ts,
        ];
      }
      foreach($content_list as $key => &$content) {
        CircleUtil::CircleContentFilter($content);
      }
      return $content_list;
    } catch (\Exception $e) {
      Log::error("BatchQueryCircleContent Exception-----" . $e->getMessage());
    }
    return $content_list;
  }


  //  点赞帖子(主贴/评论)
  static public function CircleCotentParise($scheme_id, $content_id, $author_id, $user_id) {
    Log::info("--- circle content parise:" . $content_id . " author_id:" . $author_id . " user_id:" . $user_id);
    $insert_id = 0;
    try {
      $insert_id = DbConnect::getInstance()->table('circle_praise')->insertGetId(
        ['scheme_id' => $scheme_id, 'content_id' => $content_id, 'author_id' => $author_id,
        'user_id' => $user_id, 'status' => 0,'created_at' => date("Y-m-d H:i:s",time()),
        'updated_at' => date("Y-m-d H:i:s",time())]
      );
      // 增加评论计数
      if ($insert_id) {
        $sql = sprintf("update circle_content set praise = praise + 1 where id = %d limit 1", $content_id);
        $affected  = DbConnect::getInstance()->update($sql);
      }
    } catch (\Exception $e) {
      Log::error("CircleCotentParise Exception-----" . $e->getMessage());
    }
    Log::info("CircleCotentParise insert id:" . $insert_id);
    return $insert_id;
  }

  //  查询一个帖子(主贴/评论)
  static public function QueryCircleContentDetail($circle_content, $user_id, &$err_code) {
    try {
      $user_id_list = [];
      $user_id_list[] = $circle_content['user_id'];
      $user_arry = CircleUtil::GetUserInfo($user_id_list);
      $userInfo = [
        'name' => $user_arry[$circle_content['user_id']]['name'],
        'uid' => $circle_content['user_id'],
        'icon' => $user_arry[$circle_content['user_id']]['avatar'],
        'scheme' => $user_arry[$circle_content['user_id']]['scheme'],
      ];

      $commentCount = CircleUtil::GetCommentNum2($circle_content['circel_id'], $circle_content['id']);
      $parise = CircleUtil::GetPariseNum($circle_content['id'], $user_id);

      $tags = [];
      if ($circle_content['top']) {
        $tags[] = [
          'name' => '置顶',
        ];
      }
      if ($circle_content['essence']) {
        $tags[] = [
          'name' => '精华',
        ];
      }
      $attachment = [];
      if ($circle_content['attachment']) {
        $pictures_array = json_decode($circle_content['attachment'], true);
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

      $postInfo = [
        'circleId' => $circle_content['circel_id'],
        'postId' => $circle_content['id'],
        'content' => $circle_content['content'],
        'coretext' => $circle_content['coretext'],
        'ts' => $circle_content['ts'],
        'pariseCount' => $parise['pariseCount'],
        'isParised' => $parise['isParised'],
        'commentCount' => $commentCount,
        'userInfo' => $userInfo,
        'tags' => $tags,
        'attachments' => $attachment,
      ];
      return $postInfo;
    } catch (\Exception $e) {
      Log::error("QueryCircleContentDetail Exception-----" . $e->getMessage());
      return null;
    }
    return null;
  }

  static public function GetReplyList($content_id_list) {
    $reply_list = [];
    if (!$content_id_list) return $reply_list;
    try {
      $content_id_list =  implode(",", $content_id_list);
      $sql = "select id, circel_id, post_id, user_id, content, coretext, reply_uid from circle_content
        where post_id in (" . $content_id_list . ") and status = 0
        order by id asc";
      $content_obj = DbConnect::getInstance()->select($sql);
      foreach($content_obj as $node) {
        $reply_list[$node->post_id][] = [
          'circleId' => $node->circel_id,
          'id' => $node->id,
          'commentId' => $node->id,
          'uid' => $node->user_id,
          'content' => $node->content,
          'coretext' => $node->coretext,
          'reply_uid' => $node->reply_uid,
          'rootCommentId' => $node->post_id,
          'scheme' => "bbex://circleUserInfo?objId=" . $node->user_id,
        ];
      }
      foreach($reply_list as $key => &$value) {
        foreach($value as &$content) {
          CircleUtil::CircleContentFilter($content);
        }
      }
      return $reply_list;
    } catch (\Exception $e) {
      Log::error("GetReplyList Exception-----" . $e->getMessage());
      return [];
    }
    return [];
  }

  // 评论列表
  static public function CommentList($postId, $uid, $page, $limit) {
    try {
      $page = $page * $limit;
      $num = $limit + 1;
      $comment_obj = DbConnect::getInstance()->select('select id, circel_id, user_id, post_id,
        reply_uid, content, coretext,
        attachment, top, essence, unix_timestamp(created_at) as ts
        from circle_content where post_id = :post_id and status = 0
        order by top desc, id asc limit :page, :num',
        ['post_id' => $postId, 'page' => $page, 'num' => $num]);
      if (!$comment_obj) {
        return [
          'list' => [],
          'hasMore' => false,
        ];
      }

      // for user info
      $uid_list = [];
      $reply_id_list = [];
      foreach($comment_obj as $node) {
        $uid_list[] = $node->user_id;
        if ($node->reply_uid) {
          $uid_list[] = $node->reply_uid;
        }
        $reply_id_list[] = $node->id;
      }

      $reply_list = CircleUtil::GetReplyList($reply_id_list);
      if ($reply_list) {
        // 获取评论用户以及回复用户
        foreach($reply_list as $reply_array) {
          foreach($reply_array as $reply_node) {
            $uid_list[] = $reply_node['uid'];
            if (!empty($reply_node['reply_uid'])) {
              $uid_list[] = $reply_node['reply_uid'];
            }
          }
        }
      }

      // for reply info info
      $uid_list = array_unique($uid_list);
      $user_info_list = CircleUtil::GetUserInfo($uid_list);

      $list = [];
      $index = 0;
      foreach($comment_obj as $node) {
        if (++$index > $limit) break;
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

        $replyCount = 0;
        $replyList = [];
        if ($reply_list && isset($reply_list[$node->id])) {
          $replyList = $reply_list[$node->id];
          $replyCount = count($reply_list[$node->id]);
          foreach($replyList as &$obj) {
            $obj['name'] = $user_info_list[$obj['uid']]['name'];
            $obj['postId'] = $postId;
            if (!empty($obj['reply_uid'])) {
              $replyObjectInfo = [
                'name' => $user_info_list[$obj['reply_uid']]['name'],
                'uid' => $obj['reply_uid'],
                'scheme' => "bbex://circleUserInfo?objId=" . $obj['reply_uid'],
              ];
              $obj['replyObjectInfo'] = $replyObjectInfo;
              unset($obj['reply_uid']);
            }
          }
        }

        $parise = CircleUtil::GetPariseNum($node->id, $uid);

        $res_data = [
          'name' => $user_info_list[$node->user_id]['name'],
          'content' => $node->content,
          'coretext' => $node->coretext,
          'ts' => $node->ts,
          'icon' => $user_info_list[$node->user_id]['avatar'],
          'pariseCount' => $parise['pariseCount'],
          'isParised' => $parise['isParised'],
          'replyCount' => $replyCount,
          'commentId' => $node->id,
          'postId' => $postId,
          'circleId' => $node->circel_id,
          'uid' => $node->user_id,
          'scheme' => "bbex://circleUserInfo?objId=" . $node->user_id,
          'tags' => $tags,
          'isReply' => true, // 待确认
          'rootCommentId' => $node->id,
          'replyList' => $replyList,
        ];
        $list[] = $res_data;
      }
      foreach($list as &$content) {
        CircleUtil::CircleContentFilter($content);
      }
      $hasMore = $index > $limit;
      return [
        'hasMore' => $hasMore,
        'list' => $list,
      ];
    } catch (\Exception $e) {
      Log::error("CommentList Exception-----" . $e->getMessage());
      return [];
    }
    return [];
  }

  // 发布帖子
  static public function PublishContent($uid, $circleId, $content, $attachments) {
    Log::info("--- PublishContent uid:" . $uid . " circleId:" . $circleId);
    $id = 0;
    try {
      $id = DbConnect::getInstance()->table('circle_content')->insertGetId(
        ['circel_id' => $circleId, 'user_id' => $uid, 'content' => $content, 'attachment' => $attachments,
        'source' => 0, 'created_at' => date("Y-m-d H:i:s",time()),'updated_at' => date("Y-m-d H:i:s",time())]
      );
      return $id;
    } catch (\Exception $e) {
      Log::error("PublishContent Exception-----" . $e->getMessage());
    }
    return $id;
  }

  static public function PublishCoretext($uid, $circleId, $content) {
    Log::info("--- PublishContent uid:" . $uid . " circleId:" . $circleId);
    $id = 0;
    try {
      $id = DbConnect::getInstance()->table('circle_content')->insertGetId(
        ['circel_id' => $circleId, 'user_id' => $uid, 'content' => "", 'coretext' => $content, 
        'source' => 0, 'created_at' => date("Y-m-d H:i:s",time()),'updated_at' => date("Y-m-d H:i:s",time())]
      );
      return $id;
    } catch (\Exception $e) {
      Log::error("PublishContent Exception-----" . $e->getMessage());
    }
    return $id;
  }


  // 回复/评论
  static public function Comment($uid, $circleId, $commentId, $content, $reply_comment_uid, $reply_comment_id) {
    Log::info("--- Comment uid:" . $uid . " circleId:" . $circleId . " reply uid:" . $reply_comment_uid . " reply_comment_id:" . $reply_comment_id);
    $id = 0;
    try {
      $id = DbConnect::getInstance()->table('circle_content')->insertGetId(
        ['circel_id' => $circleId, 'user_id' => $uid, 'post_id' => $commentId, 'content' => $content,
        'reply_uid' => $reply_comment_uid, 'reply_id'=>$reply_comment_id, 'source' => 0, 'created_at' => date("Y-m-d H:i:s",time()),
        'updated_at' => date("Y-m-d H:i:s",time())]
      );
      // 增加评论计数
      if ($id) {
        $sql = sprintf("update circle_content set comments = comments + 1 where id = %d limit 1", $commentId);
         $affected  = DbConnect::getInstance()->update($sql);
      }
      return $id;
    } catch (\Exception $e) {
      Log::error("Comment Exception-----" . $e->getMessage());
    }
    return $id;
  }

  // 添加通知
  static public function AddNotify($circleId, $postId, $commentId, $comment_uid, $reply_id, $reply_uid, $type = 1) {
    Log::info("--- AddNotify circleId:" . $circleId . " main conten :" . $postId . " commentId:" . $commentId . " comment_uid:"
      . $comment_uid . " reply uid:" . $reply_uid . " content id:" . $reply_id);
    $id = 0;
    try {
      $id = DbConnect::getInstance()->table('circle_notify')->insertGetId(
        ['type' => $type, 'circle_id' => $circleId, 'content_id' => $postId, 'comment_id' => $commentId , 'user_id' => $comment_uid,
        'reply_id' => $reply_id,'reply_uid' => $reply_uid, 'status' => 0, 'created_at' => date("Y-m-d H:i:s",time()),
        'updated_at' => date("Y-m-d H:i:s",time())]
      );
      return $id;
    } catch (\Exception $e) {
      Log::error("Comment Exception-----" . $e->getMessage());
    }
    return $id;
  }

  // 删除通知
  static public function DelNotify($circleId, $source_uid, $source_id) {
    Log::info("--- DelNotify :" . $circleId . " source uid:" . $source_uid . " conten_id:" . $source_id);
    $deleted = 0;
    try {
      $deleted = DbConnect::getInstance()->update('delete from circle_notify
        where circle_id = ? and content_id = ? and user_id = ?',[$circleId, $source_id, $source_uid]);
      Log::info("delete circle_notify deleted:" . $deleted);
      return $deleted;
    } catch (\Exception $e) {
      Log::error("Comment Exception-----" . $e->getMessage());
    }
    return $deleted;
  }

  // 删除帖子
  static public function DelContent($id) {
    Log::info("--- DelContent id:" . $id);
    $affected  = 0;
    try {
      $affected  = DbConnect::getInstance()->update('update circle_content set status = 1
        where id = ? limit 1', [$id]);
    } catch (\Exception $e) {
      Log::error("DelContent Exception-----" . $e->getMessage());
      return 0;
    }
    return 1;
  }

  static public function HomeRecommend($uid, $circlePageNo) {
    $list = [];
    $limit = 4;
    $num = $limit + 1;
    $page = $limit * $circlePageNo;
    $sql = "select id, name, summary, image, owner from circle_info
      where status = 0 and top > 0
      order by weights desc, top desc limit " . $page . ", " . $num;

    $result = DbConnect::getInstance()->select($sql);
    if (!$result) {
      return $list;
    }

    $index = 0;
    $user_join_circle_list = CircleUtil::GetUserjoinCircleStatus($uid);
    foreach($result as $node) {
      if (++$index > $limit) break;
      $image = $node->image;
      if (!empty($image) && 0 != strcmp("http", substr($image, 0, 4))) {
        $image = config('app.image_host') . $image;
      }

      $members = CircleUtil::GetCircleUserId($node->id);
      if (!$members) {
        $members = 1;
      } else {
        $members = count($members);
      }
      $status = -1;
      $joined = false;
      if (isset($user_join_circle_list[$node->id])) {
        $status = $user_join_circle_list[$node->id];
        $joined = $status > 0;
      }

      $tmp = [
        'name' => $node->name,
        'icon' => $image,
        'circleId' => $node->id,
        'scheme' => "bbex://circleDetail?circleId=" . $node->id,
        'members' => $members,
        'joined' => $joined,
        'status' => $status,
        'lordUid' => $node->owner,
        'lord' => $uid == $node->owner,
      ];
      $list[] = $tmp;
    }
    return [
      'hasMore' => $index > $limit,
      'circles' => $list,
    ];
  }

  static public function CircleMsgCount($uid) {
    $list = [];
    try {
      $result = DbConnect::getInstance()->select('select count(1) as count from circle_notify
        where user_id = :user_id and status = 0', ['user_id' => $uid]);
      $circleMsgCount = $result[0]->count;

      $result = DbConnect::getInstance()->select('select count(1) as count from circle_praise
        where author_id = :user_id and status = 0', ['user_id' => $uid]);
      $pariseCount = $result[0]->count;

      $result = DbConnect::getInstance()->select('select id from circle_info
        where status = 0 and owner = :owner', ['owner' => $uid]);
      $clrcle_list = [];
      foreach($result as $node) {
        $clrcle_list[] = $node->id;
      }
      $review = 0;
      if ($clrcle_list) {
        $clrcle_list = implode(",", $clrcle_list);
        $sql = sprintf("select count(1) as count from circle_user where user_status = 0 and circel_id in (%s)", $clrcle_list);
        $result = DbConnect::getInstance()->select($sql);
        $review = $result[0]->count;
      }

      return [
        'circleMsgCount' => $circleMsgCount,
        'pariseCount' => $pariseCount,
        'review' => $review,
      ];
    } catch (\Exception $e) {
      Log::error("CircleMsgCount Exception-----" . $e->getMessage());
    }
    return $list;
  }

  // 获取系统通知
  static public function GetNotifyMsg($uid, $page, $limit) {
    $list = [];
    try {
      $num = $limit + 1;
      $page = $page * $limit;
      $result = DbConnect::getInstance()->select('select type, circle_id, content_id, comment_id, user_id,
        reply_id, reply_uid, unix_timestamp(created_at) as ts, order_id
        from circle_notify where user_id = :user_id and del = 0 order by id desc
        limit :page, :num', ['user_id' => $uid, 'page' => $page, 'num' => $num]);

      $uid_list = [];
      $content_id = [];
      $circle_id_list = [];
      $order_id_list = [];
      foreach($result as $node) {
        if ($node->type == 1) {
          $uid_list[] = $node->user_id;
          $uid_list[] = $node->reply_uid;
          $content_id[] = $node->comment_id;
          $content_id[] = $node->reply_id;
          $circle_id_list[] = $node->circle_id;
        } else if ($node->type == 2 || $node->type == 3) { // 置顶 精华:
          $uid_list[] = $node->user_id;
          $content_id[] = $node->comment_id;
          $circle_id_list[] = $node->circle_id;
        } else if ($node->type == 4 || $node->type == 5) { // 审批加入圈子
          $uid_list[] = $node->user_id;
          $circle_id_list[] = $node->circle_id;
        } else if ($node->type == 6) { // 删除圈子
          $circle_id_list[] = $node->circle_id;
        } else if ($node->type == 7) { // 入圈支付通知
          $circle_id_list[] = $node->circle_id;
          $uid_list[] = $node->user_id; 
          $order_id_list[] = (string)$node->order_id;
        } else if ($node->type == 8) { // 提现
          $uid_list[] = $node->user_id; 
          $order_id_list[] = (string)$node->order_id;
        }
      }
      // 获取订单中用户
      $order_list = CircleUtil::BatchGetPayOrder($order_id_list);
      if ($order_list) {
        foreach($order_list as $order) {
          $uid_list[] = $order['uid'];
        }
      }

      $uid_list = array_unique($uid_list);
      $content_id = array_unique($content_id);
      $circle_id_list = array_unique($circle_id_list);

      $user_arry = CircleUtil::GetUserInfo($uid_list);
      $content_list = CircleUtil::BatchQueryCircleContent($content_id);
      $circle_list = CircleUtil::BatchGetCircleInfo($circle_id_list);

      $index = 0;
      foreach($result as $node) {
        if (++$index > $limit) break;
        $msg_node = [];
        if ($node->type == 1) {
          if (!isset($content_list[$node->reply_id])) continue;
          if (!isset($content_list[$node->comment_id])) continue;
          if (!isset($circle_list[$node->circle_id])) continue;
          $msg_node = [
            'type' => $node->type,
            'name' => $user_arry[$node->reply_uid]['name'],
            'icon' => $user_arry[$node->reply_uid]['avatar'],
            'content' => $content_list[$node->reply_id]['content'],
            'ts' => $content_list[$node->reply_id]['ts'],
            'replyObjectInfo' => [
              'content' => $content_list[$node->comment_id]['content'],
            ],
            'circleName' => $circle_list[$node->circle_id]['name'],
            'scheme' => "bbex://postDetail?postId=" . $node->content_id,
          ];
        } else if ($node->type == 2) {
          if (!isset($content_list[$node->comment_id])) continue;
          if (!isset($circle_list[$node->circle_id])) continue;
          $content = "<font style=\"font-size:15px;color:#181B23;\">您在「";
          $content = $content . $circle_list[$node->circle_id]['name'];
          $content = $content . "」发表的<strong>\"";
          $content = $content . mb_substr($content_list[$node->comment_id]['content'], 0, 20, 'utf-8');
          $content = $content . "...\"</strong>被圈主设置为置顶帖。</font>";
          $content = urlencode($content);
          $msg_node = [
            'type' => 2,
            'content' => $content,
            'ts' => $node->ts,
            'scheme' => "bbex://postDetail?postId=" . $node->content_id,
          ];
        } else if ($node->type == 3) {
          if (!isset($content_list[$node->comment_id])) continue;
          if (!isset($circle_list[$node->circle_id])) continue;
          $content = "<font style=\"font-size:15px;color:#181B23;\">您在「";
          $content = $content . $circle_list[$node->circle_id]['name'];
          $content = $content . "」发表的<strong>\"";
          $content = $content . mb_substr($content_list[$node->comment_id]['content'], 0, 20, 'utf-8');
          $content = $content . "...\"</strong>被圈主设置为精华帖。</font>";
          $content = urlencode($content);
          $msg_node = [
            'type' => 2,
            'content' => $content,
            'ts' => $node->ts,
            'scheme' => "bbex://postDetail?postId=" . $node->content_id,
          ];
        } else if ($node->type == 4) {
          if (!isset($circle_list[$node->circle_id])) continue;
          $msg_node = [
            'type' => 2,
            'content' => sprintf("<font style=\"font-size:15px;color:#181B23;\">您被拒绝加入「%s」</font>", $circle_list[$node->circle_id]['name']),
            'ts' => $node->ts,
          ];
        } else if ($node->type == 5) {
          if (!isset($circle_list[$node->circle_id])) continue;
          $msg_node = [
            'type' => 2,
            'content' => sprintf("<font style=\"font-size:15px;color:#181B23;\">您已通过「%s」入圈申请</font>", $circle_list[$node->circle_id]['name']),
            'ts' => $node->ts,
            'scheme' => "bbex://circleDetail?circleId=" . $node->circle_id,
          ];
        } else if ($node->type == 6) {
          if (!isset($circle_list[$node->circle_id])) continue;
          $msg_node = [
            'type' => 2,
            'content' => sprintf("<strong>「%s」</strong>因为经常发不合法信息，该圈已经被禁用，你将无法查看圈内信息。", $circle_list[$node->circle_id]['name']),
            'ts' => $node->ts,
          ];
        } else if ($node->type == 7) {
          if (!isset($order_list[$node->order_id])) continue;
          $order = $order_list[$node->order_id];
          $join_uid = $order_list[$node->order_id]['uid'];
          $msg_node = [
            'type' => 2,
            'content' => sprintf("收到<strong>「%s」</strong>加入<strong>「%s」</strong>的费用%0.2f元。", 
           $user_arry[$join_uid]['name'],  $circle_list[$node->circle_id]['name'], $order['actual_amount']),
            'ts' => $node->ts,
            'scheme' => "bbex://transactionDetails?orderNo=" . $node->order_id,
          ];
        } else if ($node->type == 8) {
          if (!isset($order_list[$node->order_id])) continue;
          $order = $order_list[$node->order_id];
          $content = "";
          if ($order['status'] == 1) {
            $content = sprintf("你的提现申请已经到账，金额为:%.2f元\n点击查看明细>>", $order['amount']);
          } else {
            $content = sprintf("你的提现申请遇到问题:%s", $order['err_msg']);
          }
          $msg_node = [
            'type' => 2,
            'content' => $content,
            'ts' => $node->ts,
            // 'scheme' => "bbex://transactionDetails?orderNo=" . $node->order_id,
          ];
          if ($order['status'] == 1) $msg_node['scheme'] = "bbex://transactionDetails?orderNo=" . $node->order_id;
        }
        $list[] = $msg_node;
      }
      $hasMore = $index > $limit;
      return [
        'hasMore' => $hasMore,
        'list' => $list,
      ];
    } catch (\Exception $e) {
      Log::error("GetNotifyMsg Exception-----" . $e->getMessage());
    }
    return $list;
  }

  // 设置消息为已读
  static public function SetReadMsg($uid) {
    Log::info("--- SetReadMsg user:" . $uid);
    $affected  = 0;
    try {
      $affected  = DbConnect::getInstance()->update('update circle_notify set status = 1
        where user_id = ?', [$uid]);
    } catch (\Exception $e) {
      Log::error("BeReviewCircleUser Exception-----" . $e->getMessage());
    }
    return $affected;
  }

  static public function GetPraiseMsg($uid, $page, $limit) {
    $list = [];
    try {
      $num = $limit + 1;
      $page = $page * $limit;
      $result = DbConnect::getInstance()->select('select scheme_id, content_id, author_id, user_id
        from  circle_praise where author_id = :user_id and user_id != :uid order by id desc
        limit :page, :num', ['user_id' => $uid, 'uid' => $uid, 'page' => $page, 'num' => $num]);

      $uid_list = [];
      $content_id = [];
      $circle_id_list = [];
      foreach($result as $node) {
        $uid_list[] = $node->author_id;
        $uid_list[] = $node->user_id;
        $content_id[] = $node->content_id;
      }
      $uid_list = array_unique($uid_list);
      $content_id = array_unique($content_id);
      $user_arry = CircleUtil::GetUserInfo($uid_list);
      $content_list = CircleUtil::BatchQueryCircleContent($content_id);

      // 获取圈子信息
      foreach($content_list as $node) {
        $circle_id_list[] = $node['circel_id'];
      }
      $circle_id_list = array_unique($circle_id_list);
      $circle_list = CircleUtil::BatchGetCircleInfo($circle_id_list);

      $index = 0;
      foreach($result as $node) {
        if (++$index > $limit) break;
        if (!isset($content_list[$node->content_id])) continue;
        $circle_id = $content_list[$node->content_id]['circel_id'];
        if (!isset($circle_list[$circle_id])) continue;
        $msg_node = [
          'type' => 0,
          'name' => $user_arry[$node->user_id]['name'],
          'icon' => $user_arry[$node->user_id]['avatar'],
          'ts' => $content_list[$node->content_id]['ts'],
          'replyObjectInfo' => [
            'content' => $content_list[$node->content_id]['content'],
          ],
          'circleName' => $circle_list[$circle_id]['name'],
          'scheme' => "bbex://postDetail?postId=" . $node->scheme_id,
        ];
        $list[] = $msg_node;
      }
      $hasMore = $index > $limit;
      return [
        'hasMore' => $hasMore,
        'list' => $list,
      ];
    } catch (\Exception $e) {
      Log::error("GetPraiseMsg Exception-----" . $e->getMessage());
    }
    return $list;
  }

  // 设置消息为已读
  static public function SetReadPraise($uid) {
    Log::info("--- SetReadPraise user:" . $uid);
    $affected  = 0;
    try {
      $affected  = DbConnect::getInstance()->update('update circle_praise set status = 1
        where author_id = ?', [$uid]);
    } catch (\Exception $e) {
      Log::error("BeReviewCircleUser Exception-----" . $e->getMessage());
    }
    return $affected;
  }

  // 添加一条push
  static public function WritePush($record) {
    Log::info("-- WritePush:" , $record);
    try {
      $type = $record['type'];
      $title = mb_substr($record['title'], 0, 20, 'utf-8');
      $content = mb_substr($record['content'], 0, 120, 'utf-8');
      $setall = 0;
      $tags = [];
      $extras = $record['extras'];
      foreach($record['tags'] as $uid) {
        $tags[] = (string)$uid;
      }
      $tags = json_encode($tags);
      $scheme = $record['scheme'];
      $id = DbConnect::getInstance()->insert('insert into circle_push_msg
        (type, title, content, setall, tags, scheme, status, extras, created_at, updated_at)
        value(?,?,?,0,?,?,0,?,?,?)', [$type, $title, $content, $tags, $scheme, (string)$extras, date("Y-m-d H:i:s",time()), date("Y-m-d H:i:s",time())]);
    } catch (\Exception $e) {
      Log::error("WritePush Exception-----" . $e->getMessage());
      return ;
    }
  }

  static public function MakeCommentPush($uid, $postId, $content) {
    $clircle_content = CircleUtil::QueryCircleContent($postId);
    if (!$clircle_content) return;
    if ($uid == $clircle_content['user_id']) return;
    $user_arry = CircleUtil::GetUserInfo([$uid]);
    $record = [
      'type' => 2,
      'title' => "",
      'content' => "[". $user_arry[$uid]['name'] . "]:" . " 评论了你 : " . $content,
      'tags' => [$clircle_content['user_id']],
      'scheme' => "bbex://postDetail?postId=" . $postId,
      'extras' => json_encode(['type' => 1]),
      ];
    CircleUtil::WritePush($record);
  }
  static public function MakeReplyPush($uid, $postId, $reply_uid, $content) {
    if ($uid == $reply_uid) return;
    $user_arry = CircleUtil::GetUserInfo([$uid]);
    $record = [
      'type' => 3,
      'title' => "",
      'content' => "[". $user_arry[$uid]['name'] . "]:" . " 回复了你 : " . $content,
      'tags' => [$reply_uid],
      'scheme' => "bbex://postDetail?postId=" . $postId,
      'extras' => json_encode(['type' => 1]),
      ];
    CircleUtil::WritePush($record);
  }
  static public function MakePraisePush($uid, $content_id, $postId) {
    $clircle_content = CircleUtil::QueryCircleContent($content_id);
    if (!$clircle_content) return;
    if ($uid == $clircle_content['user_id']) return;
    $user_arry = CircleUtil::GetUserInfo([$uid]);
    $record = [
      'type' => 4,
      'title' => "",
      'content' => "[". $user_arry[$uid]['name'] . "]:" . " 点赞了你",
      'tags' => [$clircle_content['user_id']],
      'scheme' => "bbex://postDetail?postId=" . $postId,
      'extras' => json_encode(['type' => 0]),
    ];
    CircleUtil::WritePush($record);
  }

  static public function ChangeContent($content_id, $topone) {
    Log::info("--- ChangeContent content_id:" . $content_id . " operate :" . $topone);
    try {
      $topone = $topone ? time() : 0;
      $sql = "update circle_content set top = " . $topone . " where id = " . $content_id . " limit 1";
      Log::info("sql:" . $sql);
      $affected  = DbConnect::getInstance()->update($sql);
      return $affected;
    } catch (\Exception $e) {
      Log::error("ChangeContent Exception-----" . $e->getMessage());
      return 0;
    }
    return 0;
  }

  static public function SetContentFine($content_id, $addFine) {
    Log::info("--- SetContentFine content_id:" . $content_id . " operate :" . $addFine);
    try {
      $addFine = $addFine ? time() : 0;
      $sql = "update circle_content set essence = " . $addFine . " where id = " . $content_id . " limit 1";
      Log::info("sql:" . $sql);
      $affected  = DbConnect::getInstance()->update($sql);
      return $affected;
    } catch (\Exception $e) {
      Log::error("SetContentFine Exception-----" . $e->getMessage());
      return 0;
    }
    return 0;
  }

  static public function UpdateCircleLastPostTime($circle_id) {
    Log::info("--- UpdateCircleLastPostTime circle_id:" . $circle_id);
    try {
      $sql = "update circle_info set last_post_time = " . time() . " where id = " . $circle_id . " limit 1";
      Log::info("sql:" . $sql);
      $affected  = DbConnect::getInstance()->update($sql);
      return $affected;
    } catch (\Exception $e) {
      Log::error("UpdateCircleLastPostTime Exception-----" . $e->getMessage());
      return 0;
    }
    return 0;
  }

  //////////////////////////////////数据统计///////////////////////////////////////
  static public function DataStatActive($uid, $circle_id) {
    if (!$uid || !$circle_id) return;
    // date_default_timezone_set("Asia/Shanghai");
    $redis_tab = "cirlce_avtice_" . date('Ymd');
    $redis_key = $circle_id . "#" . $uid;
    Log::info("table:" . $redis_tab . " key:" . $redis_key . " value:" . $uid);
    Redis::hSetNx($redis_tab, $redis_key, $uid);
  }

  static public function DataStatPost($circle_id, $isMaster) {
    if (!$circle_id) return;
    try {
      // 插入一条数据
      DbConnect::getInstance()->insert('insert ignore into circle_data
        (circel_id, circle_date, created_at, updated_at)
        value(?,?,?,?)', [$circle_id, date("Ymd"),date("Y-m-d H:i:s",time()),
          date("Y-m-d H:i:s",time())]);

      $affected  = DbConnect::getInstance()->update('update circle_data
        set post_num = post_num + 1 where circel_id = ? and circle_date = ?
        limit 1', [$circle_id, date('Ymd')]);

      if ($isMaster) {
        $affected  = DbConnect::getInstance()->update('update circle_data
          set master_post_num = master_post_num + 1 where circel_id = ? and circle_date = ?
          limit 1', [$circle_id, date('Ymd')]);
      }
    } catch (\Exception $e) {
      Log::error("DataStatPost Exception-----" . $e->getMessage());
      return 0;
    }
  }

  // 评论
  static public function DataStatComment($circle_id, $isMaster) {
    if (!$circle_id) return;
    try {
      // 插入一条数据
      DbConnect::getInstance()->insert('insert ignore into circle_data
        (circel_id, circle_date, created_at, updated_at)
        value(?,?,?,?)', [$circle_id, date("Ymd"), date("Y-m-d H:i:s",time()),
          date("Y-m-d H:i:s",time())]);

      $affected  = DbConnect::getInstance()->update('update circle_data
        set reply_num = reply_num + 1 where circel_id = ? and circle_date = ?
        limit 1', [$circle_id, date('Ymd')]);

      if ($isMaster) {
        $affected  = DbConnect::getInstance()->update('update circle_data
          set master_reply_num = master_reply_num + 1 where circel_id = ? and circle_date = ?
          limit 1', [$circle_id, date('Ymd')]);
      }
    } catch (\Exception $e) {
      Log::error("DataStatPost Exception-----" . $e->getMessage());
      return 0;
    }
  }

  // 点赞
  static public function DataStatPraise($circle_id, $isMaster) {
    if (!$circle_id) return;
    try {
      // 插入一条数据
      DbConnect::getInstance()->insert('insert ignore into circle_data
        (circel_id, circle_date, created_at, updated_at)
        value(?,?,?,?)', [$circle_id, date("Ymd"), date("Y-m-d H:i:s",time()),
          date("Y-m-d H:i:s",time())]);

      $affected  = DbConnect::getInstance()->update('update circle_data
        set praise_num = praise_num + 1 where circel_id = ? and circle_date = ?
        limit 1', [$circle_id, date('Ymd')]);

      if ($isMaster) {
        $affected  = DbConnect::getInstance()->update('update circle_data
          set master_praise_num = master_praise_num + 1 where circel_id = ? and circle_date = ?
          limit 1', [$circle_id, date('Ymd')]);
      }
    } catch (\Exception $e) {
      Log::error("DataStatPost Exception-----" . $e->getMessage());
      return 0;
    }
  }

  /////////////////////////////////////////////////////////////////////

  static public function searchCircle($uid, $page, $limit, $searchKey) {
    try {
      // $join_circle_list = CircleUtil::GetUserjoinCircleId($uid);
      // $join_circle_list =  implode(",", $join_circle_list);
      $join_circle_list =  [];

      $num = $limit + 1;
      $page = $limit * $page;
      $sql = "select id, name, summary, image, recommend from circle_info where status = 0";
      // if ($join_circle_list) {
      //   $sql = $sql . " and id not in (" . $join_circle_list . ")";
      // }
      $sql = $sql . " and name like '%" . $searchKey . "%'";
      $sql = $sql . " order by id desc limit " . $page . "," . $num;
      Log::info("searchCircle sql:" .$sql);
      $result = DbConnect::getInstance()->select($sql);
      $list = [];
      $index = 0;
      foreach($result as $node) {
        if (++$index > $limit) break;
        $image = $node->image;
        if (0 != strcmp("http", substr($image, 0, 4))) {
          $image = config('app.image_host') . $image;
        }

        $circle_user_list = CircleUtil::GetCircleUserId($node->id);
        $members = empty($circle_user_list) ? 0 : count($circle_user_list);
        if ($members > 1000) {
          $members = round($members / 1000, 2) . "K";
        }

        $tmp = [
          'name' => $node->name,
          'desc' => $node->summary,
          'icon' => $image,
          'members' => $members,
          'circleId' => $node->id,
          'scheme' => "bbex://circleDetail?circleId=" . $node->id,
        ];
        $list[] = $tmp;
      }
      $hasMore = $index > $limit;
      return [
        'hasMore' => $hasMore,
        'list'  => $list,
      ];
    } catch (\Exception $e) {
      Log::error("DataStatPost Exception-----" . $e->getMessage());
      return [];
    }
    return [];
  }

  static public function searchPost($uid, $circleId, $page, $limit, $searchKey) {
    try {
      $num = $limit + 1;
      $page = $limit * $page;
      $sql = sprintf("select id, user_id, content, coretext,
        unix_timestamp(created_at) as ts from circle_content
        where circel_id = %d and post_id = 0 and content like '%%%s%%' order by id desc limit %d, %d",
        $circleId, $searchKey, $page, $num);

      Log::info("searchPost sql:" .$sql);
      $result = DbConnect::getInstance()->select($sql);
      $list = [];
      $index = 0;

      $user_id_list = [];
      foreach($result as $node) {
        $user_id_list[] = $node->user_id;
      }
      $user_arry = [];
      if ($user_id_list) {
        $user_arry = CircleUtil::GetUserInfo($user_id_list);
      }

      foreach($result as $node) {
        if (++$index > $limit) break;
        $tmp = [
          'postId' => $node->id,
          'name' => $user_arry[$node->user_id]['name'],
          'ts' => $node->ts,
          'content' => $node->content,
          'coretext' => $node->coretext,
          'scheme' => "bbex://postDetail?postId=" . $node->id,
        ];
        $list[] = $tmp;
      }
      foreach($list as &$content) {
        CircleUtil::CircleContentFilter($content);
      }

      $hasMore = $index > $limit;
      return [
        'hasMore' => $hasMore,
        'list'  => $list,
      ];
    } catch (\Exception $e) {
      Log::error("DataStatPost Exception-----" . $e->getMessage());
      return [];
    }
    return [];
 }

  static public function hotCircleSearch($page, $limit) {
    try {
      $num = $limit + 1;
      $sql = "select id, name, summary, image, recommend from circle_info where status = 0";
      $sql = $sql . " order by weights desc, id desc limit " . $page . "," . $num;
      Log::info("searchCircle sql:" .$sql);
      $result = DbConnect::getInstance()->select($sql);
      $list = [];
      $index = 0;
      foreach($result as $node) {
        if (++$index > $limit) break;
        $image = $node->image;
        if (0 != strcmp("http", substr($image, 0, 4))) {
          $image = config('app.image_host') . $image;
        }

        $circle_user_list = CircleUtil::GetCircleUserId($node->id);
        $members = empty($circle_user_list) ? 0 : count($circle_user_list);
        if ($members > 1000) {
          $members = round($members / 1000, 2) . "K";
        }

        $tmp = [
          'name' => $node->name,
          'desc' => $node->summary,
          'icon' => $image,
          'members' => $members,
          'circleId' => $node->id,
          'scheme' => "bbex://circleDetail?circleId=" . $node->id,
        ];
        $list[] = $tmp;
      }
      $hasMore = $index > $limit;
      return [
        'hasMore' => $hasMore,
        'list'  => $list,
      ];
    } catch (\Exception $e) {
      Log::error("DataStatPost Exception-----" . $e->getMessage());
      return [];
    }
    return [];
  }

  // 设置加入圈子审核开关
  static public function joinCirclePermission($uid, $circleId, $joinLevel) {
    Log::info("--- joinCirclePermission circle:" . $circleId . " joinLevel:" . $joinLevel);
    $affected  = 0;
    try {
      $affected  = DbConnect::getInstance()->update('update circle_info set review_switch = ?
        where id = ? limit 1', [$joinLevel, $circleId]);
      return $affected;
    } catch (\Exception $e) {
      Log::error("joinCirclePermission Exception-----" . $e->getMessage());
      return 0;
    }
    return 1;
  }

  // 设置加入圈子审核开关
  static public function reviewList($uid) {
    try {
      $sql = sprintf("select id from circle_info where owner = %d and status = 0", $uid);
      $result  = DbConnect::getInstance()->select($sql);
      $clircle_list = [];
      foreach($result as $node) {
        $clircle_list[] = $node->id;
      }
      if (!$clircle_list) return [];

      $clircle_list =  implode(",", $clircle_list);
      $sql = sprintf("select circel_id, user_id from circle_user where circel_id in (%s) and user_status = 0", $clircle_list);
      $result  = DbConnect::getInstance()->select($sql);

      $clircle_list = [];
      $uid_list = [];
      foreach($result as $node) {
        $clircle_list[] = $node->circel_id;
        $uid_list[] = $node->user_id;
      }
      $user_arry = CircleUtil::GetUserInfo($uid_list);
      $circle_info_list = CircleUtil::BatchGetCircleInfo($clircle_list);

      $list = [];
      foreach($result as $node) {
        $tmp = [
          'name'  => $user_arry[$node->user_id]['name'],
          'uid'  => $node->user_id,
          'circleName'  => $circle_info_list[$node->circel_id]['name'],
          'circleId'  => $node->circel_id,
          'avatar'  => $user_arry[$node->user_id]['avatar'],
          'scheme'  => $user_arry[$node->user_id]['scheme'],
        ];
        $list[] = $tmp;
      }
      return ['list' => $list];
    } catch (\Exception $e) {
      Log::error("reviewList Exception-----" . $e->getMessage());
      return [];
    }
    return [];
  }


  // 进圈审核
  static public function review($circle_review_list, $operate) {
    try {
      foreach($circle_review_list as $key => $val) {
        $circel_info = CircleUtil::GetCircleInfo($key);
        $uid_list = implode(",", $val);
        if (1 == $operate) {
          $sql = sprintf("update circle_user set user_status = 1 where circel_id = %d and user_id in (%s)", $key, $uid_list);
          Log::info("-- review sql:" . $sql);
          $affected  = DbConnect::getInstance()->update($sql);
          foreach($val as $uid) {
            CircleUtil::AddNotify($key, 0, 0, $uid, 0, 0, 5);
          }
          if ($circel_info) {
            $record = [
              'type' => 1,
              'title' => "",
              'content' => sprintf("您已通过「%s」入圈申请", $circel_info['name']),
              'tags' => $val,
              'scheme' => "bbex://circleDetail?circleId=" . $key,
              'extras' => json_encode(['type' => 0]),
            ];
            CircleUtil::WritePush($record);
          }
        } else {
          $sql = sprintf("delete from circle_user where circel_id = %d and user_id in (%s)", $key, $uid_list);
          Log::info("-- review sql:" . $sql);
          $affected  = DbConnect::getInstance()->update($sql);
          foreach($val as $uid) {
            CircleUtil::AddNotify($key, 0, 0, $uid, 0, 0, 4);
          }
          if ($circel_info) {
            $record = [
              'type' => 1,
              'title' => "",
              'content' => sprintf("您被拒绝加入「%s」", $circel_info['name']),
              'tags' => $val,
              'extras' => json_encode(['type' => 0]),
              'scheme' => "",
            ];
            CircleUtil::WritePush($record);
          }
        }
      }
    } catch (\Exception $e) {
      Log::error("reviejoinCirclePermissioow Exception-----" . $e->getMessage());
      return [];
    }
    return [];
  }

  // 获取项目圈子
  static public function ProjectCircle($pid) {
    $sql = sprintf("select id, name, summary, image from circle_info where status = 0 and project_id = %d", $pid);
    $result = DbConnect::getInstance()->select($sql);
    $list = [];
    foreach($result as $node) {

      $image = $node->image;
      if (!empty($image) && 0 != strcmp("http", substr($image, 0, 4))) {
        $image = config('app.image_host') . $image;
      }

      $circle_user_list = CircleUtil::GetCircleUserId($node->id);
      $members = empty($circle_user_list) ? 0 : count($circle_user_list);
      if ($members > 1000) {
        $members = round($members / 1000, 2) . "K";
      }

      $tmp = [
        'name' => $node->name,
        'desc' => $node->summary,
        'icon' => $image,
        'members' => $members,
        'scheme' => "bbex://circleDetail?circleId=" . $node->id,
      ];
      $list[] = $tmp;
    }
    return ['list' => $list];
  }

  // 获取圈子用户状态
  static public function GetCircleUserInfo($circel_id, $uid) {
    $sql = sprintf("select circel_id, user_status from circle_user
      where circel_id = %d and user_id = %d", $circel_id, $uid);
    $result = DbConnect::getInstance()->select($sql);
    if (!$result) return [];
    $list = [];
    foreach($result as $node) {
      $list = [
        'clrcle_id' => $node->circel_id,
        'user_status' => $node->user_status,
      ];
    }
    return $list;
  }

  // 获取未读帖子
  static public function GetCircleUnReadCount($circel_id, $ts) {
    $sql = sprintf("select count(1) as count from circle_content
      where circel_id = %d and post_id = 0  and status = 0 and unix_timestamp(created_at) > %d", $circel_id, $ts);
    $result = DbConnect::getInstance()->select($sql);
    if (!$result) return 0;
    return $result[0]->count;
  }

  // 获取未读帖子
  static public function GetCircleFineCount($circel_id) {
    $sql = sprintf("select count(1) as count from circle_content
      where circel_id = %d and post_id = 0  and status = 0 and essence > 0", $circel_id);
    $result = DbConnect::getInstance()->select($sql);
    if (!$result) return 0;
    return $result[0]->count;
  }
  // 获取未读帖子
  static public function GetUserCreateCircle($uid) {
    $sql = sprintf("select id, image, name from circle_info
      where owner = %d and status = 0", $uid);
    $result = DbConnect::getInstance()->select($sql);
    if (!$result) return [];
    $circle_list = [];
    foreach($result as $node) {
      $image = $node->image;
      if (0 != strcmp("http", substr($node->image, 0, 4))) {
        $image = config('app.image_host') . $image;
      }

      $circle_list[] = [
        'id' => $node->id,
        'name' => $node->name,
        'image' => $image,
      ];
    }
    return $circle_list;
  }

  static public function GetTopCircle($cirlce_id_list = []) {
    $sql = "select circel_id, count(circel_id) as count from circle_user
      where circel_id in (select id from circle_info where status = 0 and id != 26)
      and user_status > 0 GROUP BY circel_id order by count desc  limit 70";
    if ($cirlce_id_list) {
      $cirlce_id_list = implode(",", $cirlce_id_list);
      $sql = sprintf("select circel_id, count(circel_id) as count from circle_user
        where circel_id in (%s) and user_status > 0 GROUP BY circel_id order by count desc",
        $cirlce_id_list);
    }
    $result = DbConnect::getInstance()->select($sql);
    if (!$result) return [];
    $circle_list = [];
    $circle_id_list = [];
    foreach($result as $node) {
      $circle_list[$node->circel_id] = $node->count;
      $circle_id_list[] = $node->circel_id;
    }

    // 去重
    $cirlce_id_list =  implode(",", $circle_id_list);
    $sql = sprintf("select id, owner from circle_info where id in (%s)", $cirlce_id_list);
    $result = DbConnect::getInstance()->select($sql);
    $user_circle_list = [];
    $user_list = [];
    foreach($result as $node) {
      $user_circle_list[$node->id] = $node->owner;
      $user_list[$node->owner][] = $node->id;
    }
    // id => user
    $circle_id_list = [];
    foreach($circle_list as $key => $value) {
      $user = $user_circle_list[$key];
      if (isset($user_list[$user])) {
        $circle_id_list[$key] = $value;
        unset($user_list[$user]);
      }
      if (count($circle_id_list) >= 10) break;
    }
    return $circle_id_list;
  }


  static public function GetCircleRank() {
    $sql = sprintf("select IFNULL(circle_info.id, 0) as id, IFNULL(circle_info.owner, 0) as uid, count(circle_user.user_id) as count
      from circle_info right join circle_user on circle_info.id = circle_user.circel_id
      where circle_info.id != 26 and circle_info.status = 0 and circle_user.user_status > 0
      group by circle_info.id , circle_info.owner order by count desc");
    $result = DbConnect::getInstance()->select($sql);
    $circle_list = [];
    $user_set = [];
    $index = 0;
    foreach($result as $node) { 
      if ($node->id == 0 || $node->uid == 0) continue;
      if (isset($user_set[$node->uid])) continue;
      $circle_list[$node->id] =[
        'id' => $node->id,
        'uid' => $node->uid,
        'members' => $node->count,
        'rank' => ++$index,
      ];
      $user_set[$node->uid] = $node->id;
    }
    return $circle_list;
  }

  static public function GetEssEnce($page, $limit) {
    $num = $limit + 1;
    $sql = sprintf("select id, circel_id, content, coretext from circle_content where essence > 0
      order by essence limit %d, %d", $page, $num);

    $result = DbConnect::getInstance()->select($sql);
    if (!$result) return [];
    $list = [];
    $index = 0;
    foreach($result as $node) {
      if (++$index > $limit) break;
      $list[] = [
        'id' => $node->id,
        'circel_id' => $node->circel_id,
        'content' => $node->content,
        'coretext' => $node->content,
      ];
    }
    foreach($list as &$content) {
      CircleUtil::CircleContentFilter($content);
    }

    return [
      'hasMore' => $index > $limit,
      'list'  => $list,
    ];
  }

  static public function sortCircleList($uid, $circle_sort) {
    Log::info("--- user:" . $uid . " sortCircleList uid:" . $uid);
    try {
      DbConnect::getInstance()->insert('replace into circle_user_circle_sort
        (user_id, sort_list, created_at, updated_at)
        value(?,?,?,?)', [$uid, $circle_sort, date("Y-m-d H:i:s",time()), date("Y-m-d H:i:s",time())]);
    } catch (\Exception $e) {
      Log::error("sortCircleList Exception-----" . $e->getMessage());
    }
    return ;
  }

  static public function GetsortCircleList($uid) {
    $sql = sprintf("select sort_list from circle_user_circle_sort where user_id = %d", $uid);
    $result = DbConnect::getInstance()->select($sql); 
    if ($result) return json_decode($result[0]->sort_list);
    return [];
  }

  static public function GetUserPostList($uid, $page, $limit) {
    $page = $limit * $page;
    $sql = sprintf("select circle_info.id as id from circle_content inner join circle_info
      on circle_info.id = circle_content.circel_id
      where circle_info.status = 0 and circle_content.post_id = 0 
      and circle_content.user_id = %d and circle_content.status = 0", $uid);
    $result = DbConnect::getInstance()->select($sql);
    if (!$result) {
      return ['hasMore' => false,'cmList'  => []];
    }

    $cirlce_list = [];
    foreach($result as $node) {
      $cirlce_list[] = $node->id;
    }
    $cirlce_list = implode(",", $cirlce_list);

    $num = $limit + 1;
    $sql = sprintf("select id, circel_id, user_id, content, coretext, attachment, top, essence,
      unix_timestamp(created_at) as ts from circle_content where circel_id in (%s)
      and post_id = 0 and status = 0 and user_id = %d order by id desc limit %d, %d", $cirlce_list, $uid, $page, $num);
    $result = DbConnect::getInstance()->select($sql);

    $user_id_list = [];
    $circle_list = [];
    foreach($result as $node) {
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
    foreach($result as $node) {
      if (!isset($circle_info_list[$node->circel_id])) continue;
      if (++$index > $limit) break;

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
        'circleInfo' => $circleInfo,
        "scheme" => "bbex://postDetail?postId=" . $node->id,
        "shareInfo" => [
          'url' => config('app.shared_url.circle') . "?circleId=" . $node->circel_id,
          ],
        ];
      $list[] = $res_data;
    }

    foreach($list as &$content) {
      CircleUtil::CircleContentFilter($content);
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
    return [
      'hasMore' => $hasMore,
      'cmList'  => $list,
    ];
  }

  // 虚拟点赞
  static public function VirtualParise($param) {
    $postId = $param['postId'];
    $circle_content_id = $param['circle_content_id'];
    $user_id = $param['user_id'];
    $uid = $param['uid'];
    $circel_id = $param['circel_id'];

    $id = CircleUtil::CircleCotentParise($postId, $circle_content_id, $user_id, $uid);
    // if ($id && $uid != $user_id) {
    //   CircleUtil::MakePraisePush($uid, $circle_content_id, $postId);
    // }
    if ($id) {
      $circle_info = CircleUtil::GetCircleInfo($circel_id);
      CircleUtil::DataStatPraise($circel_id, false);
      CircleUtil::UpdateVirtualParise($circle_content_id);
    }
    return 0;
  }

  static public function UpdateVirtualParise($id) {
    $sql = sprintf("update circle_content set virtual_praise = virtual_praise + 1 where id = %d limit 1", $id);
    Log::info("sql:" . $sql);
    $affected  = DbConnect::getInstance()->update($sql);
    return $affected;
  }

  // 发布微博贴子
  static public function PublishWbContent($uid, $circleId, $content, $attachments, $msgid) {
    Log::info("--- PublishWbContent uid:" . $uid . " circleId:" . $circleId);
    $id = 0;
    try {
      $id = DbConnect::getInstance()->table('circle_content')->insertGetId(
        ['circel_id' => $circleId, 'user_id' => $uid, 'content' => $content, 'third_msg_id' => $msgid,
        'attachment' => $attachments,
        'source' => 1, 'created_at' => date("Y-m-d H:i:s",time()),'updated_at' => date("Y-m-d H:i:s",time())]
      );
      return $id;
    } catch (\Exception $e) {
      Log::error("PublishWbContent Exception-----" . $e->getMessage());
    }
    return $id;
  }

  static public function GetCircleVirUser($circleId) {
    $sql = sprintf("select user_id from circle_user where circel_id = %d and user_type = 1", $circleId);
    $result = DbConnect::getInstance()->select($sql);
    $user_list = [];
    foreach($result as $node) {
      $user_list[] = $node->user_id;
    }
    return $user_list;
  }

  // 设置圈子费用
  static public function SetCirclePay($cirlce, $amount) {
    $circleId = $cirlce['id'];
    $sql = sprintf("update circle_info set amount = %f, modify_amount_time = %d
      where id = %d", $amount, time(), $circleId);
    Log::info("SetCirclePay sql:" . $sql);
    $affected  = DbConnect::getInstanceByName('mysql_circle')->update($sql);
    if ($affected <= 0) {
      Log::error("SetCirclePay failed");
      return -1;
    }
    if ($amount > 0.0) {
      $sql = sprintf("update circle_info set attr = 1, pay = 1 where id = %d", $circleId);
    } else {
      $sql = sprintf("update circle_info set pay = 0 where id = %d", $circleId);
    }
    $affected = DbConnect::getInstanceByName('mysql_circle')->update($sql);
    if (abs($cirlce['amount'] - 0.0) < 0.01 && abs($amount - 0.0) > 0.01) {
      $expire_date = time() + (60 * 60 * 24 * 365);
      $sql = sprintf("update circle_user set expire_date = %d where circel_id = %d",
        $expire_date, $circleId);
      DbConnect::getInstanceByName('mysql_circle')->update($sql);
    }
    return  0;
  }

  // 下单接口
  static public function MkWxOrder($circleId, $owner, $uid, $amount, $code = "", $openid = "") {
    Log::info("--- MkWxOrder uid:" . $uid . " circleId:" . $circleId);
    $id = 0;
    try {
      $actual_amount = (float) ($amount * 95 / 100);
      if ($code || $openid) {
        $order_id = sprintf("gzh_%d_%d_%d", $circleId, $uid, CircleUtil::getMillisecond());
      } else {
        $order_id = sprintf("%d_%d_%d", $circleId, $uid, CircleUtil::getMillisecond());
      }
      $id = DbConnect::getInstanceByName('mysql_wx_pay')->table('wx_pay_order')->insertGetId(
        ['circel_id' => $circleId, 'owner' => $owner, 'user_id' => $uid, 'amount' => $amount,
        'order_timestamp' => time(), 'order_id' => $order_id, 'actual_amount' => $actual_amount,
        'created_at' => date("Y-m-d H:i:s",time()),'updated_at' => date("Y-m-d H:i:s",time())]
      );
      if ($id > 0) {
        return $order_id;
      }
    } catch (\Exception $e) {
      Log::error("MkWxOrder Exception-----" . $e->getMessage());
      return null;
    }
    return null;
  }

  // 获取微信订单接口
  static public function AppPayUnifiedOrder($order_id, $fee, $commodity, $code = "", $openid) {
    $js_pay = ($code || $openid) ? 1 : 0; 
    if ($code || $openid) {
      $url = config('app.wx_pay.JsunifiedOrder');
    } else {
      $url = config('app.wx_pay.unifiedOrder');
    }
    $parame = [
      'out_trade_no' => $order_id,
      'total_fee' => $fee * 100,
      'body' => $commodity,
      'spbill_create_ip' => env('APP_ENV') == 'local' ? $_SERVER["REMOTE_ADDR"] : (isset($_SERVER["HTTP_X_REAL_IP"]) ? $_SERVER["HTTP_X_REAL_IP"] : $_SERVER["REMOTE_ADDR"]),
      'code' => $code,
      'openid' => $openid,
    ];
    Log::info("AppPayUnifiedOrder parame:" . json_encode($parame));
    $response = (new Client())->request('Get', $url, ['query' => $parame]);
    $contents = json_decode($response->getBody()->getContents(), true);
    Log::info("AppPayUnifiedOrder res:" . json_encode($contents));
    if (!isset($contents['data']))  {
      Log::info("not find data from res");
      return null;
    }
    $order_detail = $contents['data'];
    if (!isset($order_detail['prepayid']))  {
      Log::info("not find data from prepayid");
      return null;
    }
    $prepayid = $order_detail['prepayid'];

    $sql = sprintf("update wx_pay_order set prepayid = '%s'
      where order_id = '%s' and order_type = 0 limit 1",
      $prepayid, $order_id);

    $affected  = DbConnect::getInstanceByName('mysql_wx_pay')->update($sql); 
    if ($affected <= 0) {
      Log::error("AppPayUnifiedOrder update prepayid failed");
      return null;
    }
    return $order_detail;
  }

  // 获取订单
  static public function GetPayOrdedr($prepayid) {
    $sql = sprintf("select circel_id, owner, user_id, amount, status, order_id, prepayid
      from wx_pay_order where prepayid = '%s' and order_type = 0", $prepayid);
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    if(!$result) return null;
    foreach($result as $node) {
      return [
        'circel_id' => $node->circel_id,
        'owner' => $node->owner,
        'user_id' => $node->user_id,
        'amount' => $node->amount,
        'status' => $node->status,
        'order_id' => $node->order_id,
        'prepayid' => $node->prepayid,
      ];
    }
    return null;
  }

  static public function GetPayOrdedrBybbexOrder($order_id) {
    $sql = sprintf("select circel_id, owner, user_id, amount, status, order_id, prepayid
      from wx_pay_order where order_id = '%s' and order_type = 0", $order_id);
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    if(!$result) return null;
    foreach($result as $node) {
      return [
        'circel_id' => $node->circel_id,
        'owner' => $node->owner,
        'user_id' => $node->user_id,
        'amount' => $node->amount,
        'status' => $node->status,
        'order_id' => $node->order_id,
        'prepayid' => $node->prepayid,
      ];
    }
    return null;
  }


  // 查询支付订单状态
  static public function QueryOrderPayStatus($order_id) {
    $url = config('app.wx_pay.orderQuery');
    $parame = [
      'out_trade_no' => $order_id,
    ];
    $response = (new Client())->request('Get', $url, ['query' => $parame]);
    $contents = json_decode($response->getBody()->getContents(), true);
    Log::info("QueryOrderPayStatus res:" . json_encode($contents));
    if (!isset($contents['data']))  {
      Log::info("not find data from res");
      return -1;
    }
    $res = $contents['data'];
    if (!isset($res['succCode']))  {
      return -1;
    }
    if ($res['succCode'] != '0') {
      return -2;
    }
    return 0;
  }

  static public function QueryOrderWithdraw($order_id) {
    $url = config('app.wx_pay.QueryOrderWithdraw');
    $parame = [
      'partner_trade_no' => $order_id,
    ];
    $response = (new Client())->request('Get', $url, ['query' => $parame]);
    $contents = json_decode($response->getBody()->getContents(), true);
    Log::info("QueryOrderWithdraw res:" . json_encode($contents));
    if (!isset($contents['data']))  {
      Log::info("not find data from res");
      return null;
    }
    $res = $contents['data'];
    return $res;
  }

  // 更新支付订单状态
  static public function UpdateWxPayStatus($order_id) {
    $sql = sprintf("update wx_pay_order set status = 1, has_check = 1, pay_time = now()
      where order_id = '%s' and order_type = 0 limit 1",
      $order_id);
    Log::info("UpdateWxPayStatus sql:" . $sql);
    $affected  = DbConnect::getInstanceByName('mysql_wx_pay')->update($sql); 
    if ($affected <= 0) {
      Log::error("UpdateWxPayStatus update prepayid failed");
      return -1;
    }
    return 0;
  }

  // 加入付费圈子
  static public function JoinPayCircle($circle_id, $uid) {
    Log::info("--- user:" . $uid . " IgnoreJoin circle:" . $circle_id);
    try {
      $expire_date = time() + (60 * 60 * 24 * 365);
      DbConnect::getInstance()->insert('INSERT IGNORE into circle_user
        (circel_id, user_id, user_status, expire_date, join_type, created_at, updated_at)
        value(?,?,?,?,?,?,?)', [$circle_id, $uid, 1, $expire_date, 1,
          date("Y-m-d H:i:s",time()), date("Y-m-d H:i:s",time())]);
    } catch (\Exception $e) {
      Log::error("JoinCircle Exception-----" . $e->getMessage());
      return -1;
    }
    return 0;
  }

  // 加入付费圈子通知
  static public function AddPayNotify($circleId, $uid, $order_id, $type) {
    $id = 0;
    try {
      $id = DbConnect::getInstance()->table('circle_notify')->insertGetId(
        ['type' => $type, 'circle_id' => $circleId, 'user_id' => $uid,
        'status' => 0, 'order_id' => $order_id, 'created_at' => date("Y-m-d H:i:s",time()),
        'updated_at' => date("Y-m-d H:i:s",time())]
      );
      return $id;
    } catch (\Exception $e) {
      Log::error("Comment Exception-----" . $e->getMessage());
    }
    return $id;
  }

  // 获取用户支付流水
  static public function GetPayList($uid, $page, $limit) {

    $num = $limit + 1;
    $page = $limit * $page;
    $sql = sprintf("select circel_id, user_id, amount, actual_amount, prepayid, order_timestamp, order_type
      from wx_pay_order
      where (owner = %d and status = 1 and order_type = 0) or (user_id = %d and status = 1 and order_type = 1)
      order by id desc limit %d, %d",
      $uid, $uid, $page, $num);

    Log::info("sql:" . $sql);
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    if (!$result) {
      return ['hasMore' => false, 'list' => []];
    }
    $list = [];
    $user_id_list = [];
    foreach($result as $node) {
      $user_id_list[] = $node->user_id;
    }
    $user_arry = CircleUtil::GetUserInfo($user_id_list);
    $index = 0;
    foreach($result as $node) {
      if (++$index > $limit) break;
      if (0 == $node->order_type) {
        $list[] = [
          'ts' => $node->order_timestamp,
          'orderNo' => $node->prepayid,
          'type' => $node->order_type,
          'fee' => $node->actual_amount,
          'desc' => sprintf("「%s」 入圈费用", $user_arry[$node->user_id]['name']),
          'scheme' => "bbex://transactionDetails?orderNo=" . $node->prepayid,
        ];
      } else if (1 == $node->order_type){
        $list[] = [
          'ts' => $node->order_timestamp,
          'orderNo' => $node->prepayid,
          'type' => 2,
          'fee' => $node->amount,
          'desc' => sprintf("提现"),
          'scheme' => "bbex://transactionDetails?orderNo=" . $node->prepayid,
        ];
      }
    }
    $hasMore = $index > $limit;
    return [
      'hasMore' => $hasMore,
      'list'  => $list,
    ];
  }

  // 获取用户支付流水
  static public function GetPayDetail($uid, $orderNo) {
    $sql = sprintf("select circel_id, user_id, amount, actual_amount, prepayid,
      order_timestamp, order_type from wx_pay_order
      where prepayid = '%s' and status = 1",
      $orderNo);

    Log::info("sql:" . $sql);
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    if (!$result) {
      return ['hasMore' => false, 'list' => []];
    }
    $list = [];
    $user_id_list = [];
    $circel_id_list = [];
    foreach($result as $node) {
      $user_id_list[] = $node->user_id;
      $circel_id_list[] = $node->circel_id;
    }
    $user_arry = CircleUtil::GetUserInfo($user_id_list);
    $circel_arry = CircleUtil::BatchGetCircleInfo($circel_id_list);
    $index = 0;
    foreach($result as $node) {
      if ($node->order_type == 0 && !isset($circel_arry[$node->circel_id])) continue;
      if ($node->order_type == 0) {
        $list = [
          'ts' => $node->order_timestamp,
          'orderNo' => $node->prepayid,
          'handingFee' => $node->amount - $node->actual_amount, 
          'type' => $node->order_type,
          'fee' => $node->actual_amount,
          'desc' => sprintf("收到 「%s」 加入「%s」的费用 %0.2f 元", 
            $user_arry[$node->user_id]['name'], 
            $circel_arry[$node->circel_id]['name'], 
            $node->actual_amount),
        ];
      } else if ($node->order_type == 1) {
        $list = [
          'ts' => $node->order_timestamp,
          'orderNo' => $node->prepayid,
          'handingFee' => 0, 
          'type' => 2,
          'fee' => $node->amount,
          'desc' => sprintf("提现到微信"),
        ];
      }
      return $list;
    }
    return $list;
  }

  // 获取用户支付流水
  static public function GetUserWalletInfo($uid) {
    $walletinfo = [
      'balance' => 0,
      'withdrawAmount' => 0,
      'pendingAmount' => 0,
      'withdrawEnable' => true,
      'placeholder1' => config('app.wx_pay.placeholder1'),
      'placeholder2' => config('app.wx_pay.placeholder2'),
    ];

    $sql = sprintf("select balance, withdraw_amount from user_wallet where uid = %d", $uid);
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql); 

    foreach($result as $node) {
      $walletinfo['withdrawAmount'] = $node->balance;
    }

    $sql = sprintf("select sum(actual_amount) as pendingAmount from wx_pay_order
      where owner = %d and status = 1 and biling_status = 0 and order_type = 0", $uid);
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql); 
    foreach($result as $node) {
      $walletinfo['pendingAmount'] = $node->pendingAmount ? $node->pendingAmount : 0;
    }
    $walletinfo['balance'] = round($walletinfo['withdrawAmount'] + $walletinfo['pendingAmount'], 2);

    return $walletinfo;
  }

  // 圈子待审核人数
  static public function GetCircleViewNum($circleId) {
    $sql = sprintf("select count(1) as cnt from circle_user 
      where circel_id = %d and user_status = 0", $circleId);
    $result = DbConnect::getInstance()->select($sql);
    if ($result) {
      return $result[0]->cnt;
    }
    return 0;
  }
  // 批量获取支付订单
  static public function BatchGetPayOrder($order_id_list) {
    $list = [];
    if (!$order_id_list) return $list;
    $string_order = "";
    foreach($order_id_list as $order_id) {
      if (empty($string_order)) {
        $string_order .= sprintf("'%s'", $order_id);
      } else {
        $string_order .= sprintf(",'%s'", $order_id);
      }
    }
    $sql = sprintf("select user_id, amount, status, order_type, actual_amount, prepayid, err_msg
      from wx_pay_order
      where prepayid in (%s)", $string_order);

    Log::info("sql:" . $sql);
    $list = [];
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    foreach($result as $node) {
      $list[$node->prepayid] = [
        'uid' => $node->user_id,
        'amount' => $node->amount,
        'actual_amount' => $node->actual_amount,
        'order_type' => $node->order_type,
        'status' => $node->status,
        'err_msg' => $node->err_msg,
      ];
    }
    return $list;
  }

  static public function QueryUserUnDoWithdrawOrder($uid, $order_id = "") {
    $list = [];
    if ($order_id) {
      $sql = sprintf("select circel_id, user_id, amount, status, order_id, order_type, status
        from wx_pay_order
        where order_id = '%s' and user_id = %d and order_type = 1 and status = 0", 
        $order_id, $uid);
    } else {
      $sql = sprintf("select circel_id, user_id, amount, status, order_id, order_type,status
        from wx_pay_order
        where user_id = %d and order_type = 1 and status = 0", $uid);
    }
    $result = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    foreach($result as $node) {
      $list[$node->order_id]  = [
        'circel_id' => $node->circel_id,
        'uid' => $node->user_id,
        'amount' => $node->amount,
        'status' => $node->status,
        'order_id' => $node->order_id,
        'order_type' => $node->order_type,
        'status' => $node->status,
      ];
    }
    return $list;
  } 

  // 创建提现订单
  static public function CreateWithdrawOrder($uid, $amount) {
    Log::info("--- CreateWithdrawOrder uid:" . $uid . " amount:" . $amount);
    $id = 0;
    try {
      $order_id = sprintf("withdraw%d%d", $uid, CircleUtil::getMillisecond());
      $id = DbConnect::getInstanceByName('mysql_wx_pay')->table('wx_pay_order')->insertGetId(
        ['user_id' => $uid, 'amount' => $amount, 'order_type' => 1,
        'order_timestamp' => time(), 'order_id' => $order_id, 
        'created_at' => date("Y-m-d H:i:s",time()),'updated_at' => date("Y-m-d H:i:s",time())]
      );
      if ($id > 0) {
        return $order_id;
      }
    } catch (\Exception $e) {
      Log::error("CreateWithdrawOrder Exception-----" . $e->getMessage());
      return null;
    }
    return null;
  }

  // 用户提现事务处理
  static public function DOUserWithdraw($user_info, $order) {
    Log::info("--- CreateWithdrawOrder uid:" . $user_info['uid'] . " order_id:"
      . $order['order_id'] . " amount:" . $order['amount']);
    $amount = $order['amount'];
    $uid = $user_info['uid'];
    $ret_obj = [
      'code' => -1,
      'msg' => "系统错误",
    ];
    try {
      // 更改钱包余额
      DbConnect::getInstanceByName('mysql_wx_pay')->beginTransaction();
      $sql = sprintf("update user_wallet set balance = balance - %f,
        withdraw_amount = withdraw_amount + %f
        where uid = %d and balance >= %f", 
        $amount, $amount, $uid, $amount);

      Log::info("update user_wallet sql:" . $sql);
      $affected = DbConnect::getInstanceByName('mysql_wx_pay')->update($sql);
      if ($affected != 1) {
        Log::error("sql:" . $sql . " affected 0");
        DbConnect::getInstanceByName('mysql_wx_pay')->rollback();
        return $ret_obj;
      }

      // 付款
      $url = config('app.wx_pay.Withdraw');
      $parame = [
        'out_trade_no' => $order['order_id'],
        'spbill_create_ip' => env('APP_ENV') == 'local' ? $_SERVER["REMOTE_ADDR"] : (isset($_SERVER["HTTP_X_REAL_IP"]) ? $_SERVER["HTTP_X_REAL_IP"] : $_SERVER["REMOTE_ADDR"]) ,
        'openid' => $user_info['openid'],
        'check_name' => 1,
        'real_name' => $user_info['real_name'],
        'amount' => $order['amount'] * 100,
        'desc' => $user_info['name'] . ' 提现 ' . $order['amount'],
      ];
      $response = (new Client())->request('Get', $url, ['query' => $parame]);
      $response = json_decode($response->getBody()->getContents(), true);
      Log::info("DOUserWithdraw res:" . json_encode($response));
      if (!isset($response['data']))  {
        Log::info("not find data from res");
        DbConnect::getInstanceByName('mysql_wx_pay')->rollback(); 
        return $ret_obj;
      }
      $ret_data = $response['data'];
      $err_code = "";
      $err_code_des = "";
      $payment_no = "";
      if ($ret_data['return_code'] == 'SUCCESS' ) {
        if ($ret_data['result_code'] != 'SUCCESS') {
          $err_code = $ret_data['err_code'];
          $err_code_des = $ret_data['err_code_des'];
        } else {
          if ($ret_data['result_code'] == 'SUCCESS') {
            $payment_no = $ret_data['payment_no'];
          }
        }
      }

      // 更新数据;
      if (!$payment_no) {
        $ret_obj['code'] = -2;
        $ret_obj['msg'] = $ret_data['err_code_des'];
        DbConnect::getInstanceByName('mysql_wx_pay')->rollback();
        if ($err_code == 'NOTENOUGH') {
          Log::info("DOUserWithdraw NOTENOUGH amount:" . $order['amount']);

          // push 通知
          $record = [
            'type' => 3,
            'title' => "",
            'content' => "账户金额不足，请充值",
            'tags' => config('app.push_notify'),
            'scheme' => "",
            'extras' => json_encode(['type' => 2]),
          ];
          CircleUtil::WritePush($record);

          // 短信
          CircleUtil::SendSms("账户金额不足，请充值");
          return $ret_obj;
        }
        if ($err_code == 'NAME_MISMATCH') {
          $ret_obj['msg'] = '实名认证信息与微信支付实名信息不一致，请加微信「bbex01」处理此问题。';
        }
        $sql = sprintf("update wx_pay_order set status = 2, prepayid = '%s', 
          err_msg = '%s'
          where order_id = '%s' and user_id = %d limit 1",
          $order['order_id'], $ret_obj['msg'], $order['order_id'], $uid);
        Log::info("update wx_pay_order sql:" . $sql);
        $affected = DbConnect::getInstanceByName('mysql_wx_pay')->update($sql);
        if ($affected != 1) {
          Log::error("sql:" . $sql . " affected 0");
        }
        // 支付失败增加一条通知
        CircleUtil::AddPayNotify(0, $uid, $order['order_id'], 8);
      } else {
        // 更新订单成功
        $sql = sprintf("update wx_pay_order set status = 1, prepayid = '%s'
          where order_id = '%s' and user_id = %d limit 1",
          $payment_no, $order['order_id'], $uid);
        Log::info("update wx_pay_order sql:" . $sql);
        $affected = DbConnect::getInstanceByName('mysql_wx_pay')->update($sql);
        if ($affected != 1) {
          Log::error("sql:" . $sql . " affected 0");
          return $ret_obj;
        }

        // 记录日志
        DbConnect::getInstanceByName('mysql_wx_pay')->insert("insert into wx_pay_log 
          (uid, amount, type, order_id, prepayid, updated_at, created_at)
          value(?, ?, 1, ?, ?, ?, ?)", [$uid, $order['amount'], 
          $order['order_id'], $payment_no, date("Y-m-d H:i:s",time()),
          date("Y-m-d H:i:s",time())]);

        // 提现成功消息通知
        CircleUtil::AddPayNotify(0, $uid, $payment_no, 8);
        $ret_obj['code'] = 0;
        $ret_obj['msg'] = 'SUCCESS';
      }
      DbConnect::getInstanceByName('mysql_wx_pay')->commit();
      return $ret_obj;
    } catch (\Exception $e) {
      Log::error("DOUserWithdraw Exception-----" . $e->getMessage());
      DbConnect::getInstanceByName('mysql_wx_pay')->rollback();
      return $ret_obj;
    }
    return $ret_obj;
  }

  // 查询收藏
  static public function QueryUserFavoritePost($uid) {
    $sql = sprintf("select list from circle_user_favorite
      where uid = %d", $uid);
    $result = DbConnect::getInstanceByName('mysql_circle')->select($sql);
    foreach($result as $node) {
      $list = $node->list;
      return json_decode($list, true);
    }
    return [];
  } 

  // 更新收藏
  static public function UpdateUserFavoritePost($uid, $list) {
    DbConnect::getInstanceByName('mysql_circle')->insert('replace into circle_user_favorite
      (uid, list, created_at, updated_at)
      value(?,?,?,?)', [$uid, json_encode($list), 
      date("Y-m-d H:i:s",time()), date("Y-m-d H:i:s",time())]);
  } 

  // 获取收藏
  static public function GetUserFavoritePostListDetail($id_list) {
    $id_list =  implode(",", $id_list);
    $sql = sprintf("select id, circel_id, user_id, content, coretext, attachment,
      unix_timestamp(created_at) as ts 
      from circle_content 
      where id in (%s) and post_id = 0 and status = 0", $id_list);


    $circel_content_list = DbConnect::getInstance()->select($sql);
    $user_id_list = [];
    $circle_list = [];
    foreach($circel_content_list as $node) {
      $user_id_list[] = $node->user_id;
      $circle_list[] = $node->circel_id;
    }
    $user_id_list = array_unique($user_id_list);
    $circle_list = array_unique($circle_list);

    $list = [];
    $circle_info_list = CircleUtil::BatchGetCircleInfo($circle_list);

    $user_info_list = CircleUtil::GetUserInfo($user_id_list);

    foreach($circel_content_list as $node) {
      if (!isset($circle_info_list[$node->circel_id])) continue;

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
      $user_info = [
        'name' => $user_info_list[$node->user_id]['name'],
        'uid' => $node->user_id,
        'icon' => $user_info_list[$node->user_id]['avatar'],
        'scheme' => $user_info_list[$node->user_id]['scheme'],
      ];
      $res_data = [
        'circleId' => $node->circel_id,
        'postId' => $node->id,
        'content' => $node->content,
        'coretext' => $node->coretext,
        'ts' => $node->ts,
        'attachments' => $attachment,
        "scheme" => "bbex://postDetail?postId=" . $node->id,
        "circleInfo" => $circle_info_list[$node->circel_id],
        "userInfo" => $user_info,
      ];
      $list[] = $res_data;
    }

    foreach($list as &$content) {
      CircleUtil::CircleContentFilter($content);
    }
    return $list;
  }


  // 获取排名圈子
  static public function GetRankCircle($uid, $type, $page, $limit) {
    $num = $limit + 1;
    $page = $limit * $page;
    if (0 == $type) {
      $sql = sprintf("select id, name, summary, image, owner, permission,
        project_id, project_name, liveactivity, review_switch, pay
        from circle_info where status = 0 and recommend > 0
        order by weights desc, recommend desc limit %d , %d", $page, $num);
    } else if (1 == $type) {
      $sql = sprintf("select id, name, summary, image, owner, permission,
        project_id, project_name, liveactivity, review_switch, pay
        from circle_info where status = 0
        order by liveactivity desc limit %d , %d", $page, $num);
    } else {
      $sql = sprintf("select id, name, summary, image, owner, permission,
        project_id, project_name, liveactivity, review_switch, pay
        from circle_info where status = 0 and project_id > 0
        order by weights desc limit %d , %d", $page, $num);
    }
    Log::info("sql:" . $sql);
    $result = DbConnect::getInstanceByName('mysql_circle')->select($sql);
    if (!$result) {
      return ['hasMore' => false, 'list' => []];
    }
    $list = [];
    $index = 0;
    foreach($result as $circel_obj) {
      if (++$index > $limit) break;

      $image = $circel_obj->image;
      if (!empty($image) && 0 != strcmp("http", substr($image, 0, 4))) {
        $image = config('app.image_host') . $image;
      }

      $circel_info = [
        'name' => $circel_obj->name,
        'desc' => $circel_obj->summary,
        'icon' => $image,
        'owner' => $circel_obj->owner,
        'lordUid' => $circel_obj->owner,
        'publishPermission' => $circel_obj->permission,
        'valuation' => $circel_obj->liveactivity,
        'join_switch' => $circel_obj->review_switch,
        'pay' => $circel_obj->pay,
        'scheme' => "bbex://circleDetail?circleId=" . $circel_obj->id,
      ];
      // 关联项目
      if ($circel_obj->project_id && $circel_obj->project_name) {
        $circel_info['tags'][] = [
          'name' => $circel_obj->project_name,
          'scheme' => "bbex://projectDetail?pid=" . $circel_obj->project_id,
        ];
      }
      $circle_user_list = CircleUtil::GetCircleUserId($circel_obj->id);
      $members = empty($circle_user_list) ? 0 : count($circle_user_list);
      if ($members > 1000) {
        $members = round($members / 1000, 2) . "K";
      }
      $circel_info['members'] = $members;
      $list[] = $circel_info;
    }
    return [
      'hasMore' => $index > $limit,
      'list'  => $list,
    ];
  }

  static public function GetCircleSimpleContent($circle_id, $num) {
    $sql = sprintf("select id, circel_id, user_id, content, coretext,
      unix_timestamp(created_at) as ts
      from circle_content where circel_id = %d and post_id = 0 and status = 0
      and content != '' 
      order by created_at desc 
      limit %d", $circle_id, $num);
    $circel_content_list = DbConnect::getInstanceByName('mysql_circle')->select($sql);

    if (!$circel_content_list) return [];

    $user_id_list = [];
    foreach($circel_content_list as $node) {
      $user_id_list[] = $node->user_id;
    }
    $user_id_list = array_unique($user_id_list);
    $user_info_list = CircleUtil::GetUserInfo($user_id_list);
    foreach($circel_content_list as $node) {
      $user_info = [
        'name' => $user_info_list[$node->user_id]['name'],
        'uid' => $node->user_id,
        'icon' => $user_info_list[$node->user_id]['avatar'],
        'scheme' => $user_info_list[$node->user_id]['scheme'],
      ];

      $res_data = [
        'circleId' => $node->circel_id,
        'postId' => $node->id,
        'content' => $node->content,
        'coretext' => $node->coretext,
        'ts' => $node->ts,
        'uid' => $node->user_id,
        "scheme" => "bbex://postDetail?postId=" . $node->id,
        'userInfo' => $user_info,
        "shareInfo" => [
          'url' => config('app.shared_url.circle') . "?circleId=" . $node->circel_id,
          ],
        ];
      $list[] = $res_data;
    }

    foreach($list as &$content) {
      CircleUtil::CircleContentFilter($content);
    }
    return $list;
  }

  static public function CircleContentFilter(&$post) {
    if ($post['content'] && !$post['coretext']) {
      $post['coretext'] = $post['content'];
      return;
    } 
    if (!$post['content'] && $post['coretext']) {
      $post['content'] = strip_tags($post['coretext']);
      return;
    }
  }

  static public function RejectWithdraw($uid, $order_id) {
    $sql = sprintf("update wx_pay_order set status = 2, prepayid = '%s', 
      err_msg = if(err_msg != '', err_msg, '审核不通过')  
      where order_id = '%s' and user_id = %d limit 1",
      $order_id, $order_id, $uid);

    Log::info("update wx_pay_order sql:" . $sql);
    $affected = DbConnect::getInstanceByName('mysql_wx_pay')->update($sql);
    if ($affected != 1) {
      Log::error("sql:" . $sql . " affected 0");
    }
    // 支付失败增加一条通知
    CircleUtil::AddPayNotify(0, $uid, $order_id, 8);
    return 0;
  }

  static public function GetUserCurrDayWithdraw($uid) {
    $sql = "select IFNULL(count(1), 0) as count from wx_pay_order
      where order_type = 1 and status = 1 and user_id = " . $uid . " and DATE_FORMAT(created_at, '%Y%m%d') = " . date('Ymd');
    $res = DbConnect::getInstanceByName('mysql_wx_pay')->select($sql);
    if ($res) {
      return $res[0]->count;
    }
    return 0;
  }
}


