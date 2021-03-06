<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;
use App\Models\NewsLive;
use App\Library\UploadClient;

class news_live extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news_live:jinse';
    protected $down_tmp_path = '/var/www/http_api/tmp_images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'jinse news live';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {
      Log::debug("news live run.....:");
      $max_id = $this->GetLastNewsId();

      $args = ['limit' => 30];
      if ($max_id && $max_id > 0) {
        $args['id'] = $max_id;
        $args['flag'] = 'up';
      }
      Log::debug("args :", $args);

      $grade = 4;
      $res = (new Client())->get('https://api.jinse.com/live/list', $args);
      $res = json_decode($res->getBody()->getContents(), true);
      $laset_max_id = $max_id;
      if ($grade && $grade > 0 && $grade <= 5) {
        $i = 0;
        foreach($res["list"][0]["lives"] as $node) {
          if (isset($node['id']) && $node['id'] > $max_id) {
            $max_id = $node['id'];
          }
          if ($node["grade"] < $grade) {
            unset($res["list"][0]["lives"][$i]);
          }
          $i += 1;
        }
      }
      Log::debug("max top_id:", [$max_id]);

      foreach($res["list"][0]["lives"] as $node) {
        try {
          if (isset($node['id']) && $node['id'] <= $laset_max_id) {
            Log::info("node id:" . $node['id'] . " < maxid:" . $laset_max_id);
            continue;
          }
          $news_info = New NewsLive;
          $title = "";
          $content = "";
          $this->ParseContent($node['content'], $title, $content);

          if (empty($title) || empty($content)) {
            Log::error("err not find title:" . $title . " or content:" . $content);
            continue;
          }
          $news_info->title = $title;
          $news_info->content = $content;
          $news_info->news_id = "jinse_" . (string)$node['id'];
          $news_info->source = "金色财经";
          $news_info->level = isset($node['grade'])?$node['grade']:3;
          $news_info->news_time = time();
          $news_info->publish_time = time();
          $news_info->source_link = empty($node['link'])?$node['link']:"";
          $news_info->related_items = "";
          $news_info->weights = $news_info->level;
          $news_info->status = 0;
          $img = "";
          if (isset($node['images'])) {
            foreach($node['images'] as $imgs) {
              if (isset($imgs['url']) && !empty($imgs['url'])) {
                $img = $imgs['url'];
                $down_file = "";
                $this->DownloadFile($img, $down_file);
                if ($down_file) {
                  $upfile_mgr = new UploadClient([
                    'url' => 'http://ali-test1/1h3479ewxq/staticUpload',
                    'port' => 3000,
                  ]);
                  $img = $upfile_mgr->one($down_file);
                  Log::info("upload file:" . $img);
                }
                if ($img) {
                  $img = "https://static.icostreet.cn" . $img;
                }
                break;
              }
            }
          }
          // $img = "https://img.jinse.com/902563_rate.png";
          // $down_file = "";
          // $this->DownloadFile($img, $node['id'], $down_file);
          // if ($down_file) {
          //   $upfile_mgr = new UploadClient([
          //     'url' => 'http://ali-test1/1h3479ewxq/staticUpload',
          //     'port' => 3000,
          //   ]);
          //   $img = $upfile_mgr->one($down_file);
          //   Log::info("upload file:" . $img);
          // }
          // if ($img) {
          //   $img = "https://static.icostreet.cn" . $img;
          // }

          $news_info->image_link = $img;
          $news_info->save();
        } catch (\Exception $err) {
          Log::error("Exception-----" . $err->getMessage());
        }
        $this->SetLastNewsId($max_id);
      }
    }

    function ParseContent($base_content, &$title, &$content) {
      $start_pos = strpos($base_content, "【");
      $end_pos = strpos($base_content, "】");
      do {
        if (empty($start_pos)) {
          if ($start_pos == 0) break;
          Log::error("not find start of content");
          return;
        }
      } while(0);

      if (!$end_pos) {
        Log::error("not find end of content");
        return;
      }

      $title = substr($base_content, 0, $end_pos);
      $title = str_replace("【", "", $title);
      $content = substr($base_content, $end_pos);
      $content = str_replace("】", "", $content);

      $title = trim($title, " \t\r\n");
      $content = trim($content, " \t\r\n");
      return;
    }

    function DownloadFile($images_url, $id, &$down_file) {
      try {
        $file_name = strrchr($images_url, '/');
        if (!$file_name) return;
        $down_file = $this->down_tmp_path . $file_name;
        if (file_exists($down_file)) {
          $ext = $this->get_extension($file_name);
          $down_file = $this->down_tmp_path . "/" . (string)$id . "_" . (string)time() . "." . $ext;
        }
        $res = (new Client())->get($images_url);
        $fp = fopen($down_file, "w");
        fwrite($fp, $res->getBody()->getContents());
        fclose($fp);
        Log::info("download file:" . $down_file);
      } catch (\Exception $err) {
        Log::error("Exception-----" . $err->getMessage());
      }
    }

    function get_extension($filename){
        return pathinfo($filename,PATHINFO_EXTENSION);
    }

    public function GetLastNewsId() {
      $last_news_id = Redis::get('bbex:news_live:jinse:last_id');
      return $last_news_id;
    }

    public function SetLastNewsId($id) {
      Redis::set('bbex:news_live:jinse:last_id', $id);
    }
}
