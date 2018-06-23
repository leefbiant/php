<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserInfo;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;


class HttpApiSvr extends Controller {

  public function index() {
    // return $this->success('sucess'); 
    return "";
  }

  public function test() {
    // return $this->success('sucess'); 
    return "sucess test";
  }

  public function setuserinfo() {
    $req_arg = $this->getParams();
    Log::debug("setuserinfo req args:" . $req_arg);
    $name = $this->getParams('name');
    $phone = $this->getParams('phone');
    $email = $this->getParams('email');
    $password = $this->getParams('password');

    if (!$phone) {
      return $this->error('phone number or email error', 101);
    }

    $user_info = new UserInfo;
    $user_info->name = $name;
    $user_info->phone = $phone;
    $user_info->email = $email;
    $user_info->password = $password;
    $user_info->save();

    $ret = UserInfo::where('phone', $phone)->get();
    $data = [];
    foreach($ret as $v) {
      $data[$v->id] = $v->toArray();
    }
    return $this->success($data);
  }

  public function getuserinfo() {
    $req_arg = $this->getParams();
    Log::debug($req_arg);

    $id = $this->getParams('id'); 
    $ret = UserInfo::where('id', $id)->get();
    $data = [];
    foreach($ret as $v) {
      $data[$v->id] = $v->toArray();
    }
    return $this->success($data);
  }

  public function deluser() {
    $req_arg = $this->getParams();

    $id = $this->getParams('id'); 
    $ret = UserInfo::where('id', $id)->delete();
    return $this->success([]);
  }

  public function updateuserinfo() {
    $req_arg = $this->getParams();
    Log::debug("updateuserinfo req args:" , $req_arg);

    $id = $this->getParams('id');
    $user_info = UserInfo::find($id);
    if (!$user_info) {
      return $this->error('not find user for id ' . $id, 102);
    }

    $name = $this->getParams('name');
    $email = $this->getParams('email');
    $password = $this->getParams('password');
    if (!$name && !$email && !$password) {
      return $this->error('nothing to do for ' . $id, 102);
    }

    if ($name) {
      $user_info->name = $name;
    }
    if ($email) {
      $user_info->email = $email;
    }
    if ($password) {
      $user_info->password = $password;
    }
    $user_info->save();
    return $this->success([]);
  }
  public function live() {
    $req_arg = $this->getParams();
    Log::debug("live req args:" , $req_arg); 
    $id = $this->getParams('id'); 
    $grade = $this->getParams('grade'); 

    $args = [];
    if ($id) {
      $args['id'] = $id;
    }
    $res = (new Client())->get('https://api.jinse.com/live/list', $args);
    $res = json_decode($res->getBody()->getContents(), true);
    if (1) {
      if ($grade && $grade > 0 && $grade <= 5) {
        $list = $res["list"][0]["lives"];
        $ret_list = [];
        if ($list) {
          foreach($list as $node) {
            if ($node["grade"] < $grade) {
              continue;
            }
            array_push($ret_list, $node);
          }
          $res["list"] = $ret_list;
        }
      } 
    } else {
      if ($grade && $grade > 0 && $grade <= 5) {
        $index  = [];
        $i = 0;
        foreach($res["list"][0]["lives"] as $node) {
          if ($node["grade"] < $grade) {
            unset($res["list"][0]["lives"][$i]);
          }
          $i += 1;
        }
      }
    } 
    // Log::debug("jinse http res json_decode:" , $res);
    return $res;
  }
};

