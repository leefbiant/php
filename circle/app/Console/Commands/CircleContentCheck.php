<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Log;
use Illuminate\Support\Facades\DB;
use App\Util\CirclePush as Push;
use GuzzleHttp\Client as Client;

class CircleContentCheck extends Command {

  protected $name = 'circle_content:check';
  protected $description = 'check';
  public function handle()
  {
    // for text
    $res_list = [];
    $content_obj = CircleContentCheck::GetCirlceContent();
    if ($content_obj) {
      $content_list = [];
      foreach ($content_obj as $key => $val) {
        $content_list[] = $val;
      }
      $url = config('app.circle_check.text');
      $response = (new Client())->post($url, ['form_params' => ['content_list' => json_encode($content_list)]]);
      $contents = json_decode($response->getBody()->getContents(), true);
      $contents = $contents['data'];
      foreach ($contents as $node) {
        $obj = [
          'id' => $node['dataId'],
          'status' => 0,
          'text_verify_st' => 1,
        ];
        foreach ($node['results'] as $res) {
          Log::info("results:" , $res);
          if ($res['label'] == "politics" || $res['label'] == "terrorism" || $res['label'] == "abuse") {
            Log::info("CircleContentCheck content id:" . $obj['id'] . " content:" . $content_obj[$obj['id']]['content'] . " suggestion:" . $res['suggestion']);
            $obj['text_verify_st'] = 2;
            $obj['status'] = 1;
          }
        }
        $res_list[$obj['id']] = $obj;
      }
    }
    CircleContentCheck::UpdateCircleContent($res_list);

    // for image
    $cnt = 10;
    do {
      $res_list = [];
      $content_obj = CircleContentCheck::GetCirlceImage();
      if ($content_obj) {
        $url = config('app.circle_check.image');
        $response = (new Client())->post($url, ['form_params' => ['image_list' => json_encode($content_obj)]]);
        $contents = json_decode($response->getBody()->getContents(), true);
        $contents = $contents['data'];
        $obj = [];
        foreach ($contents as $node) {
          $obj = [
            'id' => $node['dataId'],
            'status' => 0,
            'img_verify_st' => 1,
          ];
          if (isset($node['results'])) {
            foreach ($node['results'] as $res) {
              Log::info("results:" , $res);
              if ($res['label'] == "politics" || $res['label'] == "terrorism" || $res['label'] == "abuse") {
                Log::info("CircleContentCheck image :" . json_encode($obj) . " suggestion:" . $res['suggestion']);
                $obj['img_verify_st'] = 2;
                $obj['status'] = 1;
              }
            }
          }
          CircleContentCheck::UpdateCircleImage($obj);
        }
      } else {
        break;
      }
    } while ($cnt--);
  }
  static public function GetCirlceContent() {
    Log::info("start GetCirlceContent");
    $obj = DB::connection('mysql_circle')->select('select id, content
      from circle_content
      where text_verify_st = 0 and content != "" order by id asc limit 100');
    $content_obj = [];
    foreach($obj as $node) {
      $content_obj[$node->id] = [ 
        'id' => $node->id,
        'content' => $node->content,
      ]; 
    }
    return $content_obj;
  }

  static public function UpdateCircleContent($res_list) {
    $affected  = 0;
    try {
      foreach ($res_list as $key => $val) {
        $sql = "";
        if ($val['status'] == 1 && $val['text_verify_st'] == 2) {
          $sql = "update circle_content set status = 1, text_verify_st = 2 where id = " . $key . " limit 1";
        } else {
          $sql = "update circle_content set text_verify_st = 1 where id = " . $key . " limit 1";
        }
        Log::info("UpdateCircleContent sql:" . $sql);
        $affected  = DB::connection('mysql_circle')->update($sql);
      }
    } catch (\Exception $e) {
      Log::error("BeReviewCircleUser Exception-----" . $e->getMessage());
    }
  }

   
  static public function GetCirlceImage() {
    Log::info("start GetCirlceImage");
    $obj = DB::connection('mysql_circle')->select('select id, attachment
      from circle_content
      where img_verify_st = 0 and attachment != "" order by id asc limit 1');
    $content_obj = [];
    foreach($obj as $node) {
      $image_array = json_decode($node->attachment, true);
      $image = "";
      foreach($image_array as $image_obj) {
        $image = $image_obj['originalUrl'];
        if (0 != strcmp("http", substr($image, 0, 4))) {
          $image = config('app.image_host') . $image;
        }
      }
      if ($image) {
        $content_obj[] = [ 
          'id' => $node->id,
          'image' => $image,
        ]; 
      }
    }
    if ($obj && !$content_obj) {
      $sql = "update circle_content set img_verify_st = 1 where id = " . $obj[0]->id . " limit 1";  
      DB::connection('mysql_circle')->update($sql);
    }
    return $content_obj;
  }

  static public function UpdateCircleImage($val) {
    $affected  = 0;
    try {
      $sql = "";
      if ($val['status'] == 1 && $val['img_verify_st'] == 2) {
        $sql = "update circle_content set status = 1, img_verify_st = 2 where id = " . $val['id'] . " limit 1";
      } else {
        $sql = "update circle_content set img_verify_st = 1 where id = " . $val['id'] . " limit 1";
      }
      Log::info("UpdateCircleImage sql:" . $sql);
      $affected  = DB::connection('mysql_circle')->update($sql);
    } catch (\Exception $e) {
      Log::error("UpdateCircleImage Exception-----" . $e->getMessage());
    }
  }
}
