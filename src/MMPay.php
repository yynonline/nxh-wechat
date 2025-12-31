<?php
namespace Nanxihang\Wechat;

class MMPay
{

	const API_URL_PREFIX = 'https://api.mch.weixin.qq.com/mmpaymkttransfers';

	///红包接口
	const SEND_RED_PACK = '/sendredpack';
	const RED_PACK_INFO = '/gethbinfo';

	private $mch_id;
	private $app_id;
	private $app_key;


	private $postxml;
	private $_msg;


	public $errCode = 40001;
	public $errMsg = "no access";
	public $logcallback;


	public function __construct( $options )
	{

		$this->mch_id    = isset($options['mch_id'])   ? $options['mch_id']       : '';
		$this->app_id    = isset($options['app_id'])   ? $options['app_id']   : '';
		$this->app_key   = isset($options['app_key'])  ? $options['app_key']       : false;


		$this->logcallback  = isset($options['logcallback'])? $options['logcallback'] : false;
	}

	/**
	 * 获取客户端IP地址
	 * @return string
	 */
	protected function getClientIP(){
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
			$ip = $_SERVER['HTTP_X_REAL_IP'];
		} elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		}
		// 处理可能的逗号分隔的IP地址列表
		$ip = trim(explode(',', $ip)[0]);
		return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
	}

	/**
	 * 根据OPENID与用户ID进行发红包
	 *
	 * @param $sOpenId  用户OPENID
	 * @param $nUserId  用户ID
	 * @param $nAmount  对应提现的钱
	 * @param $arrConfig 配置文件 此文件分为多重
	 * 1、SSL文件配置，包含了：ssl_cert、ssl_key、ssl_ca
	 * 2、对应红包需要的祝福语（wishing）与活动名称（act_name） 备注（remark） 商户名称（send_name）
	 *
	 *
	 * @return array 返回
	 * [0,$arrData]
	 * [1,$sMsg]
	 * [9,$sMsg] 系统级开发错误比如缺少参数等，返回时需要记录本地日志
	 */
	public function send( $sOpenId , $nUserId , $nAmount , $arrConfig ,$sBillno = '') {

		list($nError , $sMsg) = $this->checkMchAppid();
		if (empty($sOpenId) || empty($nUserId)){
			return [9,'用户OpenId 或 用户ID为空'];
		}

		if($nError == 1) {
			return [9 , $sMsg];
		}
		if ($nAmount <= 0){
			return [9,'提现金额错误'];
		}

		if(strpos( $nAmount , '.' )) {
			$arrAmount = explode('.' , $nAmount);
			if(count($arrAmount) > 2) {
				return [9 , "提现金额不合法"];

			}
			if(strlen($arrAmount[1]) > 2) {
				return [9 , "提现金额最多小数点两位"];
			}
		}

		$nUserId = sprintf( "%07d", $nUserId );
		$sNoPost = $nAmount * 100;
		/*if(strpos($sNoPost , '.')) {
			return [9 , "提现金额「{$nAmount}」不合法"];
		}*/
		//10位时间戳，7位用户id,m,5位钱数字， 共计23位
        if(empty($sBillno)){
            $sBillno = strtotime(date('Y-m-d H:i:s')) . $nUserId . 'm' . $sNoPost; //商户订单号，需要唯一
        }

		if(false == isset($arrConfig['send_name']) || false == isset($arrConfig['wishing']) || false == isset($arrConfig['act_name']) ) {
			return [ 9 , '商户名称\祝福语\活动名称 不能为空' ];
		}
		$arrPost = array(
			'mch_id'        => $this->mch_id, //商户号
			'wxappid'       => $this->app_id,   //公众号 APPID
			'nonce_str'     => md5( time() ),
			'mch_billno'    => $sBillno , //商户订单号，需要唯一
			're_openid'     => $sOpenId ,
			'total_amount'  => $nAmount * 100, //付款金额单位为分
			'total_num'     => 1,
			'client_ip'     => $this->getClientIP(),

			'send_name'     => $arrConfig['send_name'], //商户名称
			'wishing'       => $arrConfig['wishing'],  //红包祝福语
			'act_name'      => $arrConfig['act_name'], //活动名称
			'remark'        => $arrConfig['remark'],   //备注
		);
		list( $nError , $arrData ) = $this->getPostData( self::API_URL_PREFIX.self::SEND_RED_PACK , $arrPost , $arrConfig );
		if( $nError != 0 ) {
			return [$nError , $arrData];
		}
		return [0 , $arrData];
	}

	/**
	 * 获取订单的详细信息
	 *
	 * @param $sBillno
	 * @param $arrConfig
	 *
	 * @return array
	 */
	public function getHbInfo( $sBillno , $arrConfig )
	{

		list($nError , $sMsg) = $this->checkMchAppid();
		if($nError == 1) {
			return [9 , $sMsg];
		}

		$arrPost = array(
			'nonce_str'  => md5(time()),
			'mch_billno' => $sBillno, //商户订单号，需要唯一 154660049042618m200
			'mch_id'     => $this->mch_id,
			'bill_type'  => 'MCHT',
			'appid'      => $this->app_id,
		);
		list( $nError , $arrData ) = $this->getPostData( self::API_URL_PREFIX.self::RED_PACK_INFO , $arrPost , $arrConfig );
		if($nError != 0) {
			return [$nError , $arrData];
		}
		return [ 0 , $arrData ];
	}

	/**
	 * 获取对应的数据
	 * @param $sURL
	 * @param $arrPost
	 * @param $arrConfig
	 * @return array
	 */
	private function getPostData( $sURL , $arrPost , $arrConfig ) {
		$arrPost['sign'] = $this->getSign( $arrPost , $this->app_key );
		$sPostXml        = $this->array2xml( $arrPost );
		list($nError , $sResXML)         = $this->http_post( $sURL, $sPostXml, $arrConfig );
		if ( $nError == 1 ) {
			return [ 9, $sResXML ];
		}
		$oContent   = simplexml_load_string( $sResXML , 'SimpleXMLElement', LIBXML_NOCDATA );
		$arrContent = json_decode( json_encode($oContent) , true);
		if (false == isset($arrContent['return_code']) ||  $arrContent['return_code'] == 'FAIL' ) {
			$arrReturn['err_code']       = 'simplexml_load_string';
			$arrReturn['err_code_des']   =  isset($arrContent['err_code_des']) ? $arrContent['err_code_des'] : '';
			$arrReturn['return_msg']     =  isset($arrContent['return_msg'])   ? $arrContent['return_msg'] : '';
			$arrReturn['result_content'] = json_encode($oContent);
			return [ 1, $arrReturn ];
		}

		//代表请求成功，但是
		if (false == isset($arrContent['result_code']) || $arrContent['result_code'] == 'FAIL' ) {
			$arrReturn['err_code']       =  $arrContent['err_code'];
			$arrReturn['err_code_des']   =  isset($arrContent['err_code_des']) ? $arrContent['err_code_des'] : '';
			$arrReturn['return_msg']     =  isset($arrContent['return_msg'])   ? $arrContent['return_msg'] : '';
			$arrReturn['result_content'] = json_encode($oContent);
			return [ 1, $arrReturn ];
		}

		return [ 0, $arrContent ];
	}

	/**
	 * 将一个数组转换为 XML 结构的字符串
	 * @param array $arr 要转换的数组
	 * @param int $level 节点层级, 1 为 Root.
	 * @return string XML 结构的字符串
	 */
	protected function array2xml($arr, $level = 1)
	{
		$s = $level == 1 ? "<xml>" : '';
		foreach ($arr as $tagname => $value) {
			if (is_numeric($tagname)) {
				$tagname = $value['TagName'];
				unset($value['TagName']);
			}
			if (!is_array($value)) {
				$s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
			} else {
				$s .= "<{$tagname}>" . $this->array2xml($value, $level + 1) . "</{$tagname}>";
			}
		}
		$s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
		return $level == 1 ? $s . "</xml>" : $s;
	}

	private function getSign( $arrPostInfo , $sSginKey ) {
		foreach ($arrPostInfo as $k => $v) {
			$tarr[] = $k . '=' . $v;
		}
		sort($tarr);
		$sign = implode('&', $tarr);
		$sign .= '&key=' . $sSginKey;
		return strtoupper(md5($sign));
	}

	private function http_post( $url , $param , $arrConfig) {
		$oCurl = curl_init();
		if (stripos($url, "https://") !== FALSE) {
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
		}
		if (is_string($param)) {
			$strPOST = $param;
		} else {
			$aPOST = array();
			foreach ($param as $key => $val) {
				$aPOST[] = $key . "=" . urlencode($val);
			}
			$strPOST = join("&", $aPOST);
		}
		curl_setopt($oCurl, CURLOPT_URL, $url);
		curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($oCurl, CURLOPT_POST, true);
		curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
		if ($arrConfig) {
			if(false == isset($arrConfig['ssl_cert_path']) || false == isset($arrConfig['ssl_key_path']) || false == isset($arrConfig['ssl_ca_path'])) {
				return [1 , "缺少 ssl 字段"];
			}

			if( false == file_exists($arrConfig['ssl_cert_path']) ||  false == file_exists($arrConfig['ssl_key_path']) || false == file_exists($arrConfig['ssl_ca_path']) ) {
				return [1 , "ssl_cert Non-existent"];
			}

			if( false == is_readable($arrConfig['ssl_cert_path']) ||  false == is_readable($arrConfig['ssl_key_path']) || false == is_readable($arrConfig['ssl_ca_path']) ) {
				return [1 , "ssl_cert unreadable"];
			}

			curl_setopt($oCurl, CURLOPT_SSLCERT , $arrConfig['ssl_cert_path']);
			curl_setopt($oCurl, CURLOPT_SSLKEY  ,  $arrConfig['ssl_key_path']);
			curl_setopt($oCurl, CURLOPT_CAINFO  ,  $arrConfig['ssl_ca_path']);
		}

		$sContent = curl_exec($oCurl);
		$aStatus = curl_getinfo($oCurl);
		curl_close($oCurl);
		if (intval($aStatus["http_code"]) == 200) {
			return [ 0 , $sContent ];
		} else {
			return [1 , "Can't connect the server"];
		}
	}

	public function checkMchAppid() {
		if(strlen($this->mch_id) <= 0) {
			return [ 1 , '商户ID不能为空'];
		}

		if(strlen($this->app_id) <= 0) {
			return [ 1 , '公众号 APPID'];
		}
		if(strlen($this->app_key) <= 0) {
			return [ 1 , '支付秘钥不能为空'];
		}

		return [ 0 , ''];
	}



}