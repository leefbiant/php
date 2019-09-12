<?php
namespace App\Lib; 
use App\Util\WxPayConfig;
require_once "WxPayConfigInterface.php";
require_once "WxPay.Data.php";

use Illuminate\Support\Facades\Log;

/**
 * 
 * 接口访问类，包含所有微信支付API列表的封装，类中方法为static方法，
 * 每个接口有默认超时时间（除提交被扫支付为10s，上报超时时间为1s外，其他均为6s）
 * @author widyhu
 *
 */
class WxPayApi
{
	/**
	 * 
	 * 统一下单，WxPayUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayUnifiedOrder $inputObj
	 * @param int $timeOut
	 * @throws Exception
	 * @return 成功时返回，其他抛异常
	 */
	public static function unifiedOrder($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
    $url = config('app.unifiedOrder_url');
		//检测必填参数
		if(!$inputObj->IsOut_trade_noSet()) {
			throw new \Exception("缺少统一支付接口必填参数out_trade_no！");
		}else if(!$inputObj->IsBodySet()){
			throw new \Exception("缺少统一支付接口必填参数body！");
		}else if(!$inputObj->IsTotal_feeSet()) {
			throw new \Exception("缺少统一支付接口必填参数total_fee！");
		}else if(!$inputObj->IsTrade_typeSet()) {
			throw new \Exception("缺少统一支付接口必填参数trade_type！");
		}
		
		//关联参数
		if($inputObj->GetTrade_type() == "JSAPI" && !$inputObj->IsOpenidSet()){
			throw new \Exception("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
		}
		if($inputObj->GetTrade_type() == "NATIVE" && !$inputObj->IsProduct_idSet()){
			throw new \Exception("统一支付接口中，缺少必填参数product_id！trade_type为JSAPI时，product_id为必填参数！");
		}
		
		//异步通知url未设置，则使用配置文件中的url
		if(!$inputObj->IsNotify_urlSet() && $config->GetNotifyUrl() != ""){
			$inputObj->SetNotify_url($config->GetNotifyUrl());//异步通知url
		}

    $app_id = $inputObj->GetTrade_type() == "JSAPI" ? $config->GetAppId() : $config->GetAppId();
		
		$inputObj->SetAppid($app_id);//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		// $inputObj->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//终端ip	   	    
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		//签名
		$inputObj->SetSign($config);
		$xml = $inputObj->ToXml();
		
    Log::info("unifiedOrder xml:" . $xml);
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
    Log::info("unifiedOrder resp:" . $response);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}

  public static function GetJsApiParameters($UnifiedOrderResult)
  {
    if(!array_key_exists("appid", $UnifiedOrderResult)
      || !array_key_exists("prepay_id", $UnifiedOrderResult)
      || $UnifiedOrderResult['prepay_id'] == "")
    {
      throw new WxPayException("参数错误");
    }

    $jsapi = new WxPayJsApiPay();
    $jsapi->SetAppid($UnifiedOrderResult["appid"]);
    $timeStamp = time();
    $jsapi->SetTimeStamp("$timeStamp");
    $jsapi->SetNonceStr(WxPayApi::getNonceStr());
    $jsapi->SetPackage("prepay_id=" . $UnifiedOrderResult['prepay_id']);

    $config = new WxPayConfig();
    $jsapi->SetPaySign($jsapi->MakeSign($config));
    $jsapi->SetPrePayId($UnifiedOrderResult['prepay_id']);
    $parameters = json_encode($jsapi->GetValues());
    return $parameters;
  }
	
	/**
	 * 
	 * 查询订单，WxPayOrderQuery中out_trade_no、transaction_id至少填一个
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayOrderQuery $inputObj
	 * @param int $timeOut
	 * @throws Exception
	 * @return 成功时返回，其他抛异常
	 */
	public static function orderQuery($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/orderquery";
		$url = config('app.orderQuery_url');
		//检测必填参数
		if(!$inputObj->IsOut_trade_noSet() && !$inputObj->IsTransaction_idSet()) {
			throw new \Exception("订单查询接口中，out_trade_no、transaction_id至少填一个！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}

  // 付款到零钱

  public static function PaymentUser($config, $inputObj, $timeOut = 5) {
    $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
    $inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign2($config);//签名
		$xml = $inputObj->ToXml();
    $startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, true, $timeOut);
		$result = WxPayResults::InitNoCheck($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		return $result;
  }

  public static function GetCertficates($config, $inputObj, $timeOut = 5) {
    $url = "https://api.mch.weixin.qq.com/risk/getcertficates";
    $inputObj->SetMch_id($config->GetMerchantId());//商户号
    $inputObj->SetNonce_str(self::getNonceStr());//随机字符串

    $inputObj->SetSign($config);//签名
    $xml = $inputObj->ToXml();
    $startTimeStamp = self::getMillisecond();//请求开始时间
    // Log::info("GetCertficates req:" . $xml);
    $response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
    // Log::info("GetCertficates res:" . $response);
    $result = WxPayResults::Init($config, $response);
    self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
    return $result;
  }

  public static function UploadImage($config, $inputObj, $media, $timeOut = 5) {
    $url = "https://api.mch.weixin.qq.com/secapi/mch/uploadmedia";
    $inputObj->SetMch_id($config->GetMerchantId());//商户号
    $inputObj->SetMedia_hash($media["md5"]);//随机字符串

    $inputObj->SetSign($config);//签名

    $form_data = $inputObj->buildData($media);
    $startTimeStamp = self::getMillisecond();//请求开始时间
    $response = self::postMultipartFormData($config, $form_data, $inputObj->delimiter, $url,
      true, $timeOut);
    Log::info("UploadImage res:" . $response);
    $result = WxPayResults::Init($config, $response);
    self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
    return $result;
  }

  // 现金红包
  public static function SendCashReadEnvelopes($config, $inputObj, $timeOut = 5) {
    $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";
    $inputObj->SetWxAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign2($config);//签名
		$xml = $inputObj->ToXml();
    Log::info("SendCashReadEnvelopes req:" . $xml);
    $startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, true, $timeOut);
		$result = WxPayResults::InitNoCheck($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		return $result;
  }

	
	/**
	 * 
	 * 关闭订单，WxPayCloseOrder中out_trade_no必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayCloseOrder $inputObj
	 * @param int $timeOut
	 * @throws Exception
	 * @return 成功时返回，其他抛异常
	 */
	public static function closeOrder($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/closeorder";
		//检测必填参数
		if(!$inputObj->IsOut_trade_noSet()) {
			throw new \Exception("订单查询接口中，out_trade_no必填！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}

	/**
	 * 
	 * 申请退款，WxPayRefund中out_trade_no、transaction_id至少填一个且
	 * out_refund_no、total_fee、refund_fee、op_user_id为必填参数
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayRefund $inputObj
	 * @param int $timeOut
	 * @throws Exception
	 * @return 成功时返回，其他抛异常
	 */
	public static function refund($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
		//检测必填参数
		if(!$inputObj->IsOut_trade_noSet() && !$inputObj->IsTransaction_idSet()) {
			throw new \Exception("退款申请接口中，out_trade_no、transaction_id至少填一个！");
		}else if(!$inputObj->IsOut_refund_noSet()){
			throw new \Exception("退款申请接口中，缺少必填参数out_refund_no！");
		}else if(!$inputObj->IsTotal_feeSet()){
			throw new \Exception("退款申请接口中，缺少必填参数total_fee！");
		}else if(!$inputObj->IsRefund_feeSet()){
			throw new \Exception("退款申请接口中，缺少必填参数refund_fee！");
		}else if(!$inputObj->IsOp_user_idSet()){
			throw new \Exception("退款申请接口中，缺少必填参数op_user_id！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, true, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 * 
	 * 查询退款
	 * 提交退款申请后，通过调用该接口查询退款状态。退款有一定延时，
	 * 用零钱支付的退款20分钟内到账，银行卡支付的退款3个工作日后重新查询退款状态。
	 * WxPayRefundQuery中out_refund_no、out_trade_no、transaction_id、refund_id四个参数必填一个
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayRefundQuery $inputObj
	 * @param int $timeOut
	 * @throws Exception
	 * @return 成功时返回，其他抛异常
	 */
	public static function refundQuery($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/refundquery";
		//检测必填参数
		if(!$inputObj->IsOut_refund_noSet() &&
			!$inputObj->IsOut_trade_noSet() &&
			!$inputObj->IsTransaction_idSet() &&
			!$inputObj->IsRefund_idSet()) {
			throw new \Exception("退款查询接口中，out_refund_no、out_trade_no、transaction_id、refund_id四个参数必填一个！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 * 下载对账单，WxPayDownloadBill中bill_date为必填参数
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayDownloadBill $inputObj
	 * @param int $timeOut
	 * @throws Exception
	 * @return 成功时返回，其他抛异常
	 */
	public static function downloadBill($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/downloadbill";
		//检测必填参数
		if(!$inputObj->IsBill_dateSet()) {
			throw new \Exception("对账单接口中，缺少必填参数bill_date！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		if(substr($response, 0 , 5) == "<xml>"){
			return "";
		}
		return $response;
	}

	public static function downloadfundflow($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/pay/downloadfundflow";
		//检测必填参数
		if(!$inputObj->IsBill_dateSet()) {
			throw new \Exception("对账单接口中，缺少必填参数bill_date！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		$inputObj->SetAccountType("Basic"); //账单的资金来源账户
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		if(substr($response, 0 , 5) == "<xml>"){
			return "";
		}
		return $response;
	}

  // 查询提现订单 
  public static function QueryTransferinfo($config, $inputObj, $timeOut = 5) {
    $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/gettransferinfo";
    $inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign2($config);//签名
		$xml = $inputObj->ToXml();
    Log::info("xml:" . $xml);
    $startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, true, $timeOut);
    Log::info("response:" . $response);

		$result = WxPayResults::InitNoCheck($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		return $result;
  }
	
	/**
	 * 提交被扫支付API
	 * 收银员使用扫码设备读取微信用户刷卡授权码以后，二维码或条码信息传送至商户收银台，
	 * 由商户收银台或者商户后台调用该接口发起支付。
	 * WxPayWxPayMicroPay中body、out_trade_no、total_fee、auth_code参数必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayWxPayMicroPay $inputObj
	 * @param int $timeOut
	 */
	public static function micropay($config, $inputObj, $timeOut = 10)
	{
		$url = "https://api.mch.weixin.qq.com/pay/micropay";
		//检测必填参数
		if(!$inputObj->IsBodySet()) {
			throw new \Exception("提交被扫支付API接口中，缺少必填参数body！");
		} else if(!$inputObj->IsOut_trade_noSet()) {
			throw new \Exception("提交被扫支付API接口中，缺少必填参数out_trade_no！");
		} else if(!$inputObj->IsTotal_feeSet()) {
			throw new \Exception("提交被扫支付API接口中，缺少必填参数total_fee！");
		} else if(!$inputObj->IsAuth_codeSet()) {
			throw new \Exception("提交被扫支付API接口中，缺少必填参数auth_code！");
		}
		
		$inputObj->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//终端ip
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 * 
	 * 撤销订单API接口，WxPayReverse中参数out_trade_no和transaction_id必须填写一个
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayReverse $inputObj
	 * @param int $timeOut
	 * @throws Exception
	 */
	public static function reverse($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/secapi/pay/reverse";
		//检测必填参数
		if(!$inputObj->IsOut_trade_noSet() && !$inputObj->IsTransaction_idSet()) {
			throw new \Exception("撤销订单API接口中，参数out_trade_no和transaction_id必须填写一个！");
		}
		
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, true, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
	/**
	 * 
	 * 测速上报，该方法内部封装在report中，使用时请注意异常流程
	 * WxPayReport中interface_url、return_code、result_code、user_ip、execute_time_必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayReport $inputObj
	 * @param int $timeOut
	 * @throws Exception
	 * @return 成功时返回，其他抛异常
	 */
	public static function report($config, $inputObj, $timeOut = 1)
	{
		$url = "https://api.mch.weixin.qq.com/payitil/report";
		//检测必填参数
		if(!$inputObj->IsInterface_urlSet()) {
			throw new \Exception("接口URL，缺少必填参数interface_url！");
		} if(!$inputObj->IsReturn_codeSet()) {
			throw new \Exception("返回状态码，缺少必填参数return_code！");
		} if(!$inputObj->IsResult_codeSet()) {
			throw new \Exception("业务结果，缺少必填参数result_code！");
		} if(!$inputObj->IsUser_ipSet()) {
			throw new \Exception("访问接口IP，缺少必填参数user_ip！");
		} if(!$inputObj->IsExecute_time_Set()) {
			throw new \Exception("接口耗时，缺少必填参数execute_time_！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetUser_ip($_SERVER['REMOTE_ADDR']);//终端ip
		$inputObj->SetTime(date("YmdHis"));//商户上报时间	 
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		return $response;
	}
	
	/**
	 * 
	 * 生成二维码规则,模式一生成支付二维码
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayBizPayUrl $inputObj
	 * @param int $timeOut
	 * @throws Exception
	 * @return 成功时返回，其他抛异常
	 */
	public static function bizpayurl($config, $inputObj, $timeOut = 6)
	{
		if(!$inputObj->IsProduct_idSet()){
			throw new \Exception("生成二维码，缺少必填参数product_id！");
		}

		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetTime_stamp(time());//时间戳	 
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		
		return $inputObj->GetValues();
	}
	
	/**
	 * 
	 * 转换短链接
	 * 该接口主要用于扫码原生支付模式一中的二维码链接转成短链接(weixin://wxpay/s/XXXXXX)，
	 * 减小二维码数据量，提升扫描速度和精确度。
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayConfigInterface $config  配置对象
	 * @param WxPayShortUrl $inputObj
	 * @param int $timeOut
	 * @throws Exception
	 * @return 成功时返回，其他抛异常
	 */
	public static function shorturl($config, $inputObj, $timeOut = 6)
	{
		$url = "https://api.mch.weixin.qq.com/tools/shorturl";
		//检测必填参数
		if(!$inputObj->IsLong_urlSet()) {
			throw new \Exception("需要转换的URL，签名用原串，传输需URL encode！");
		}
		$inputObj->SetAppid($config->GetAppId());//公众账号ID
		$inputObj->SetMch_id($config->GetMerchantId());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($config);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
		$result = WxPayResults::Init($config, $response);
		self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}
	
 	/**
 	 * 
 	 * 支付结果通用通知
 	 * @param function $callback
 	 * 直接回调函数使用方法: notify(you_function);
 	 * 回调类成员函数方法:notify(array($this, you_function));
 	 * $callback  原型为：function function_name($data){}
 	 */
	public static function notify($config, $callback, &$msg)
	{
		if (!isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
			# 如果没有数据，直接返回失败
			return false;
		}

		//如果返回成功则验证签名
		try {
			//获取通知的数据
			$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
			$result = WxPayNotifyResults::Init($config, $xml);
		} catch (Exception $e){
			$msg = $e->this->getMessage();
			return false;
		}
		
		return call_user_func($callback, $result);
	}
	
	/**
	 * 
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	public static function getNonceStr($length = 32) 
	{
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {  
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		} 
		return $str;
	}
	
	/**
	 * 直接输出xml
	 * @param string $xml
	 */
	public static function replyNotify($xml)
	{
		echo $xml;
	}
	
	/**
	 * 
	 * 上报数据， 上报的时候将屏蔽所有异常流程
	 * @param WxPayConfigInterface $config  配置对象
	 * @param string $usrl
	 * @param int $startTimeStamp
	 * @param array $data
	 */
	private static function reportCostTime($config, $url, $startTimeStamp, $data)
	{
		//如果不需要上报数据
		$reportLevenl = $config->GetReportLevenl();
		if($reportLevenl == 0){
			return;
		} 
		//如果仅失败上报
		if($reportLevenl == 1 &&
			 array_key_exists("return_code", $data) &&
			 $data["return_code"] == "SUCCESS" &&
			 array_key_exists("result_code", $data) &&
			 $data["result_code"] == "SUCCESS")
		 {
		 	return;
		 }
		 
		//上报逻辑
		$endTimeStamp = self::getMillisecond();
		$objInput = new WxPayReport();
		$objInput->SetInterface_url($url);
		$objInput->SetExecute_time_($endTimeStamp - $startTimeStamp);
		//返回状态码
		if(array_key_exists("return_code", $data)){
			$objInput->SetReturn_code($data["return_code"]);
		}
		//返回信息
		if(array_key_exists("return_msg", $data)){
			$objInput->SetReturn_msg($data["return_msg"]);
		}
		//业务结果
		if(array_key_exists("result_code", $data)){
			$objInput->SetResult_code($data["result_code"]);
		}
		//错误代码
		if(array_key_exists("err_code", $data)){
			$objInput->SetErr_code($data["err_code"]);
		}
		//错误代码描述
		if(array_key_exists("err_code_des", $data)){
			$objInput->SetErr_code_des($data["err_code_des"]);
		}
		//商户订单号
		if(array_key_exists("out_trade_no", $data)){
			$objInput->SetOut_trade_no($data["out_trade_no"]);
		}
		//设备号
		if(array_key_exists("device_info", $data)){
			$objInput->SetDevice_info($data["device_info"]);
		}

    $objInput->SetUser_ip($_SERVER['SERVER_ADDR']);
		
		try{
			self::report($config, $objInput);
		} catch (Exception $e){
			//不做任何处理
		}
	}

	/**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param WxPayConfigInterface $config  配置对象
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws Exception
	 */
	private static function postXmlCurl($config, $xml, $url, $useCert = false, $second = 30)
	{		
		$ch = curl_init();
		$curlVersion = curl_version();
		$ua = "WXPaySDK/3.0.9 (".PHP_OS.") PHP/".PHP_VERSION." CURL/".$curlVersion['version']." "
		.$config->GetMerchantId();

		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);

		$proxyHost = "0.0.0.0";
		$proxyPort = 0;
		$config->GetProxy($proxyHost, $proxyPort);
		//如果有配置代理这里就设置代理
		if($proxyHost != "0.0.0.0" && $proxyPort != 0){
			curl_setopt($ch,CURLOPT_PROXY, $proxyHost);
			curl_setopt($ch,CURLOPT_PROXYPORT, $proxyPort);
		}
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
		curl_setopt($ch,CURLOPT_USERAGENT, $ua); 
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			//证书文件请放入服务器的非web目录下
			$sslCertPath = "";
			$sslKeyPath = "";
			$config->GetSSLCertPath($sslCertPath, $sslKeyPath);
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, $sslCertPath);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, $sslKeyPath);
		}
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//运行curl
		$data = curl_exec($ch);
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
      throw new \Exception("curl出错，错误码:$error");
    }
  }

	private static function postMultipartFormData($config, $form_data, $delimiter, $url, $useCert = false, $second = 30)
	{		
		$ch = curl_init();
		$curlVersion = curl_version();
		$ua = "WXPaySDK/3.0.9 (".PHP_OS.") PHP/".PHP_VERSION." CURL/".$curlVersion['version']." "
		.$config->GetMerchantId();

		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);

		$proxyHost = "0.0.0.0";
		$proxyPort = 0;
		$config->GetProxy($proxyHost, $proxyPort);
		//如果有配置代理这里就设置代理
		if($proxyHost != "0.0.0.0" && $proxyPort != 0){
			curl_setopt($ch,CURLOPT_PROXY, $proxyHost);
			curl_setopt($ch,CURLOPT_PROXYPORT, $proxyPort);
		}
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
		curl_setopt($ch,CURLOPT_USERAGENT, $ua); 
		//设置header
		// curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			$sslCertPath = "";
			$sslKeyPath = "";
			$config->GetSSLCertPath($sslCertPath, $sslKeyPath);
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, $sslCertPath);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, $sslKeyPath);
		}
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $form_data);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Content-Type: multipart/form-data; boundary=" . $delimiter
    ]);

		//运行curl
		$data = curl_exec($ch);
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
      throw new \Exception("curl出错，错误码:$error");
    }
  }


  /**
   * 获取毫秒级别的时间戳
   */
  private static function getMillisecond()
  {
    //获取毫秒的时间戳
    $time = explode ( " ", microtime () );
    $time = $time[1] . ($time[0] * 1000);
    $time2 = explode( ".", $time );
    $time = $time2[0];
    return $time;
  }

  public static function ToUrlParams($urlObj) {
    $buff = "";
    foreach ($urlObj as $k => $v)
    {
      if($k != "sign"){
        $buff .= $k . "=" . $v . "&";
      }
    }

    $buff = trim($buff, "&");
    return $buff;
  }

  public static function CreateOauthUrlForOpenid($code)
  {
    $config = new WxPayConfig();
    $urlObj["appid"] = $config->GetAppId();
    $urlObj["secret"] = $config->GetAppSecret();
    $urlObj["code"] = $code;
    $urlObj["grant_type"] = "authorization_code";
    $bizString = WxPayApi::ToUrlParams($urlObj);
    return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
  }

  public static function GetOpenidFromMp($code)
  {
    $url = WxPayApi::CreateOauthUrlForOpenid($code);
    Log::info("url:" . $url);

    $ch = curl_init();
    $curlVersion = curl_version();
    $config = new WxPayConfig();
    $ua = "WXPaySDK/3.0.9 (".PHP_OS.") PHP/".PHP_VERSION." CURL/".$curlVersion['version']." "
      .$config->GetMerchantId();

    //设置超时
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $proxyHost = "0.0.0.0";
    $proxyPort = 0;
    $config->GetProxy($proxyHost, $proxyPort);
    if($proxyHost != "0.0.0.0" && $proxyPort != 0){
      curl_setopt($ch,CURLOPT_PROXY, $proxyHost);
      curl_setopt($ch,CURLOPT_PROXYPORT, $proxyPort);
    }
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res,true);
    Log::info("data:" . json_encode($data));
    if (isset($data['openid'])) return $data['openid'];
    return null;
  }
}

