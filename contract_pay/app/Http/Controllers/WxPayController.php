<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Util\WxPayConfig;
use App\Util\Util;
use App\Lib\WxPayApi;
use GuzzleHttp\Client as Client;
use Illuminate\Support\Facades\Redis;

require_once __DIR__ . "/../../Lib/WxPay.Data.php";
// require_once __DIR__ . "/../../Lib/WxPayApi.php";

class WxPayController extends Controller {
  public function __construct()
  {
    date_default_timezone_set('Asia/Shanghai');
    if (env('APP_DEBUG')) {
      $Params = $this->getParams();
      Log::info("req:" . $this->getUrl() . " args:" . json_encode($Params));
    }
  }

  public function NotifyHandle() {
    $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents("php://input");
    if (!$data) {
      Log::error("NotifyHandle not find HTTP_RAW_POST_DATA");
      return "";
    }
    // Log::info("post data:" . json_encode($data));

    $config = new WxPayConfig();
    try {
      $xml = $data;
      $result = \App\Lib\WxPayNotifyResults::Init($config, $xml);
    } catch (\Exception $e){
      Log::error("NotifyHandle WxPayNotifyResults init failed");
      return "";
    }
    $data = $result->GetValues();
    if(!array_key_exists("return_code", $data) 
      ||(array_key_exists("return_code", $data) && $data['return_code'] != "SUCCESS")) {
        Log::error("not exiist return_code");
        return false;
      }
    if(!array_key_exists("transaction_id", $data)){
      Log::error("not exist transaction_id");
      return false;
    }

    try {
      $checkResult = $result->CheckSign($config);
      if($checkResult == false){
        Log::error("签名错误...");
        return false;
      }
    } catch(Exception $e) {
      Log::error(json_encode($e));
      return false;
    }
    if (array_key_exists("out_trade_no", $data)) {
      $out_trade_no = $data['out_trade_no'];
      Log::info("out_trade_no:" . $out_trade_no);
      $url = config('app.circle_notify');
      $response = (new Client())->request('GET', $url,
        ['query' => ['out_trade_no' => $out_trade_no]]);
      Log::info("notify circle resp :" . json_encode($response));
    }
    $replay = new \App\Lib\WxPayNotifyReply();
    $replay->SetReturn_code("SUCCESS");
    $replay->SetReturn_msg("OK");
    $replay->SetSign($config);
    $xml = $replay->ToXml();
    return $xml;
  }

  public function AppPayUnifiedOrder () {
    $out_trade_no = $this->getParams('out_trade_no');
    $total_fee = $this->getParams('total_fee');
    $spbill_create_ip = $this->getParams('spbill_create_ip');
    $body = $this->getParams('body');

    if (!$out_trade_no || !$total_fee || !$spbill_create_ip || !$body) {
      return $this->error();
    }

    $config = new WxPayConfig();
    $input = new \App\Lib\WxPayUnifiedOrder();
    $input->SetBody($body);
    $input->SetOut_trade_no($out_trade_no);
    $input->SetTotal_fee($total_fee);
    $input->SetGoods_tag("test");
    $input->SetNotify_url("https://api.bbex.io/WxPayBbex1025/wxpay/notify");
    $input->SetTrade_type("APP");
    $input->SetSpbill_create_ip($spbill_create_ip);//终端ip

    try {
      $result = WxPayApi::unifiedOrder($config, $input);
      // Log::info("unifiedorder:" . json_encode($result));
    } catch(Exception $e) {
      Log::error("AppPayUnifiedOrder Exception:" . json_encode($e));
      return $this->error(-1);
    }
    if($result["return_code"] == "SUCCESS" 
      && $result["result_code"] == "SUCCESS") {
        $client_req = new \App\Lib\WxClientpay();
        $client_req->SetAppid($result['appid']);
        $client_req->SePpartnerid($result['mch_id']);
        $client_req->SetPrepayid($result['prepay_id']);
        $client_req->SetPackage();
        $client_req->SetTimeStamp();
        $client_req->SetNonce_str();

        $client_req->SetSign2($config);
        $xml = $client_req->ToXml();
        // Log::info("xml:" . $xml);
        return $this->success($client_req->GetValues());
      }
    return $this->error();
  }

  public function AppPayUrderquery () {
    $out_trade_no = $this->getParams('out_trade_no');
    if (!$out_trade_no) {
      return $this->error();
    }
    $queryOrderInput = new \App\Lib\WxPayOrderQuery();
    $queryOrderInput->SetOut_trade_no($out_trade_no);
    $config = new WxPayConfig();

    try{
      $result = WxPayApi::orderQuery($config, $queryOrderInput);
    } catch(Exception $e) {
      Log::ERROR(json_encode($e));
      return $this->error();
    }
    Log::info("orderQuery:" . json_encode($result));
    if($result["return_code"] == "SUCCESS" 
      && $result["result_code"] == "SUCCESS")
    {
      if($result["trade_state"] == "SUCCESS"){
        return $this->success([
          'succCode' => '0',
          'trade_state' => 'SUCCESS'
        ]);
      }
      //用户支付中
      else if($result["trade_state"] == "USERPAYING"){
        return $this->success([
          'succCode' => '1',
          'trade_state' => 'USERPAYING',
        ]);
      }
      // 未支付
      else if($result["trade_state"] == "NOTPAY"){
        return $this->success([
          'succCode' => '2',
          'trade_state' => 'NOTPAY',
        ]);
      }
    }

    if($result["err_code"] == "ORDERNOTEXIST") {
        return $this->success([
          'succCode' => '-1',
        ]);
    } 
    return $this->success([
      'succCode' => '-2',
    ]);
  }

  // 付款到用户
  public function AppPaymentUser() {
    $out_trade_no = $this->getParams('out_trade_no');
    $spbill_create_ip = $this->getParams('spbill_create_ip');
    $opend_id = $this->getParams('openid');
    $check_name = $this->getParams('check_name');
    $real_name = $this->getParams('real_name', 1);
    $amount = $this->getParams('amount');
    $desc = $this->getParams('desc');

    if (!$out_trade_no || !$spbill_create_ip || !$opend_id || !$amount || !$desc) {
      return $this->error();
    }

    if ($check_name && !$real_name) {
      return $this->error();
    }
    $config = new WxPayConfig();
    $config->SetSignType("MD5");
    $input = new \App\Lib\WxPaymentUser();
    $input->SetOut_trade_no($out_trade_no);
    $input->SetOpenid($opend_id);
    $input->SetCheckname($check_name);
    if ($check_name) {
      $input->SetRealName($real_name);
    }
    $input->SetAmount($amount);
    $input->SetDesc($desc);
    $input->SetSpbill_create_ip($spbill_create_ip);

    $result = [];
    try {
      $result = WxPayApi::PaymentUser($config, $input);
      Log::info("PaymentUser:" . json_encode($result));
    } catch(Exception $e) {
      Log::error(json_encode($e));
      return $this->error();
    }
    return $this->success($result);
  }

  public function downloadBill() {
    $bill_date = $this->getParams('bill_date');
    $type = $this->getParams('type', "ALL"); // ALL, SUCCESS, REFUND, REVOKED
    if (!$bill_date) {
      return $this->error();
    }
    $input = new \App\Lib\WxPayDownloadBill();
    $input->SetBill_date($bill_date);
    $input->SetBill_type($type);
    $config = new WxPayConfig();   

    $result = [];
    try {
      $result = WxPayApi::downloadBill($config, $input);
    } catch(Exception $e) {
      Log::error(json_encode($e));
    }
    if ($result) {
      $result_list = explode(PHP_EOL, $result);
      foreach($result_list as $line) {
        Log::info("downloadBill:" . $line);
      }
    }
    return $this->success([$result]);
  }

  public function downloadFundFlow() {
    $bill_date = $this->getParams('bill_date');
    $type = $this->getParams('type', "ALL"); // ALL, SUCCESS, REFUND, REVOKED
    if (!$bill_date) {
      return $this->error();
    }
    $input = new \App\Lib\WxPayDownloadBill();
    $input->SetBill_date($bill_date);
    $input->SetBill_type($type);
    $config = new WxPayConfig();   

    $result = [];
    try {
      $result = WxPayApi::downloadBill($config, $input);
    } catch(Exception $e) {
      Log::error(json_encode($e));
    }
    if ($result) {
      $result_list = explode(PHP_EOL, $result);
      foreach($result_list as $line) {
        Log::info("downloadFundFlow:" . $line);
      }
    }
    return $this->success([$result]);
  }

  // 查询提现订单
  public function QueryTransferinfo() {
    $partner_trade_no = $this->getParams('partner_trade_no');

    $input = new \App\Lib\WxPayTransferinfo();
    $input->SetOut_trade_no($partner_trade_no);
    $config = new WxPayConfig();   
    $config->SetSignType("MD5");

    $result = [];
    try {
      $result = WxPayApi::QueryTransferinfo($config, $input);
    } catch(Exception $e) {
      Log::error(json_encode($e));
      return $this->error();
    }
    return $this->success($result);
  }

  public function WxPayJsUnifiedOrder () {
    $out_trade_no = $this->getParams('out_trade_no');
    $total_fee = $this->getParams('total_fee');
    $spbill_create_ip = $this->getParams('spbill_create_ip');
    $code = $this->getParams('code');
    $openid = $this->getParams('openid');
    $body = $this->getParams('body');

    if (!$out_trade_no || !$total_fee || !$spbill_create_ip || !$body) {
      return $this->error();
    }

    if (!$openid && !$code) {
      return $this->error();
    }

    if (!$openid &&  $code) {
      $openid = WxPayApi::GetOpenidFromMp($code);
    }
    if (!$openid) return $this->error();

    Log::info("openid:" . $openid);
    $config = new WxPayConfig();
    $input = new \App\Lib\WxPayUnifiedOrder();
    $input->SetBody($body);
    $input->SetOut_trade_no($out_trade_no);
    $input->SetTotal_fee($total_fee);
    $input->SetGoods_tag("test");
    $input->SetNotify_url("https://api.bbex.io/WxPayBbex1025/contract_pay/notify");
    $input->SetTrade_type("JSAPI");
    $input->SetOpenid($openid);
    $input->SetSpbill_create_ip($spbill_create_ip);//终端ip

    try {
      $result = WxPayApi::unifiedOrder($config, $input);
    } catch(Exception $e) {
      Log::error("AppPayUnifiedOrder Exception:" . json_encode($e));
      return $this->error(-1);
    }
    if($result["return_code"] == "SUCCESS" && $result["result_code"] == "SUCCESS") {
      $res = WxPayApi::GetJsApiParameters($result);
      Log::info("WxPayJsUnifiedOrder res:" . $res);  
      return $this->success(json_decode($res, true));
    }
    return $this->error();
  }

  public function SendCashReadEnvelopes() {
    $mch_billno = $this->getParams('mch_billno');
    $send_name = $this->getParams('send_name');
    $re_openid = $this->getParams('re_openid');
    $total_amount = $this->getParams('total_amount');
    $wishing = $this->getParams('wishing');
    $client_ip = $this->getParams('client_ip');
    $act_name = $this->getParams('act_name');
    $remark = $this->getParams('remark');

    $config = new WxPayConfig();
    $config->SetSignType("MD5");
    $input = new \App\Lib\CashReadEnvelopes();
    $input->SetMchBillNo($mch_billno);
    $input->SetMchName($send_name);
    $input->SetOpenId($re_openid);
    $input->SetAmount($total_amount);
    $input->SetWishing($wishing);
    $input->SetClientIp($client_ip);
    $input->SetActName($act_name);
    $input->SetRemark($remark);
    $input->SetTotalNum();

    $result = [];
    try {
      $result = WxPayApi::SendCashReadEnvelopes($config, $input);
      Log::info("SendCashReadEnvelopes:" . json_encode($result));
    } catch(Exception $e) {
      Log::error(json_encode($e));
      return $this->error();
    }
    return $this->success($result);
  }

  public function GetCertficates() {
    $config = new WxPayConfig();
    $config->SetSignType("HMAC-SHA256");
    $input = new \App\Lib\WxPayGetCertficates(); 
    $result = [];
    try {
      $result = WxPayApi::GetCertficates($config, $input);
      Log::info("GetCertficates:" . json_encode($result));
      $certificates = $result["certificates"];
      $certificates = json_decode($certificates, true);
      $data = $certificates["data"];
      foreach($data as $node) {
        $serial_no = $node["serial_no"];
        $encrypt_certificate = $node["encrypt_certificate"];
        $associated_data = $encrypt_certificate["associated_data"];
        $ciphertext = $encrypt_certificate["ciphertext"];
        $nonce = $encrypt_certificate["nonce"];

        $pem = Util::decodePem($ciphertext, $nonce, $associated_data);
        return $this->success([
          "serial_no" => $serial_no,
          "pem" => $pem,
        ]); 
      }
    } catch(Exception $e) {
      Log::error(json_encode($e));
      return $this->error();
    }
    return $this->success($result);
  }

  public function UploadFileTest() {
    $config = new WxPayConfig();
    $config->SetSignType("HMAC-SHA256");
    $input = new \App\Lib\UploadFileTest(); 
    $result = [];

    try {
      $filepath = "/root/work/contract_pay/storage/upload/1.jpeg";
      Log::info("file path:" . $filepath);

      $media = [
        'filename' => basename($filepath),
        'filelength' => filesize($filepath),
        'upload' => file_get_contents($filepath),
      ];
      $media['md5'] = md5($media['upload']);

      Log::info("file media:" . " filename:" . $media["filename"] . " filelength:" . $media["filelength"]);
      $result = WxPayApi::UploadImage($config, $input, $media);
      Log::info("UploadFileTest sucess");
    } catch(Exception $e) {
      Log::error(json_encode($e));
      return $this->error();
    }
    return $this->success($result);
  }

  public function UploadFile() {
    $file = $this->GetRequest()->file('file');
    if (!$file) {
      Log::info("not find file");
      return $this->error(1, "not find file");
    }
    $storage_path = config('app.upload_dir');
    $filename = $file->getClientOriginalName();
    $filesize = $file->getClientSize();
    $realpath = $file->getRealPath();
    Log::info("update temp file:" . $realpath . " filename:" . $filename . " filesize:" . $filesize . " storage_path:" . $storage_path);
    $file->move($storage_path, $filename);
    return $this->success();
  }

  public function getEncryptTest() {
    return $this->success([
      'data' => Util::getEncrypt("111")]);
  }
}
