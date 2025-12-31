<?php
namespace Nanxihang\Wechat;

/**
 * https://developer.work.weixin.qq.com/document/path/90597
 */
class WechatCorp {
    public $sApiUrl;
    public $sAccessToken;
    public $sJsapiTicket;
    public $sEncodingAesKey;
    public $sCorpId;
    public $sCorpSecret;
    public $sAgentId;

    public $debug       = false;
    public $errCode     = 40001;
    public $errMsg      = "no access";
    public $logcallback;
    public $postxml;

    private $encrypt_type;

    public function __construct($arrOptions){
        $this->sApiUrl          = isset($arrOptions['api_url'])?$arrOptions['api_url']:'https://qyapi.weixin.qq.com/';
        $this->sAccessToken     = isset($arrOptions['access_token'])?$arrOptions['access_token']:'';
        $this->sEncodingAesKey  = isset($arrOptions['encodingaeskey'])?$arrOptions['encodingaeskey']:'';
        $this->sCorpId          = isset($arrOptions['corpid'])?$arrOptions['corpid']:'';
        $this->sCorpSecret      = isset($arrOptions['corpsecret'])?$arrOptions['corpsecret']:'';
        $this->sAgentId         = isset($arrOptions['agentid'])?$arrOptions['agentid']:'';
        $this->debug            = isset($arrOptions['debug'])?$arrOptions['debug']:false;
        $this->logcallback      = isset($arrOptions['logcallback'])?$arrOptions['logcallback']:false;
    }


    /**user/get
     * 重载设置缓存
     * @param string $sCachename
     * @param mixed $sValue
     * @param int $nExpired
     * @return boolean
     */
    protected function setCache($sCachename,$sValue,$nExpired){
        // 不使用缓存，直接返回 true
        return true;
    }
    /**
     * 重载获取缓存
     * @param string $sCachename
     * @return mixed
     */
    protected function getCache($sCachename){
        // 不使用缓存，直接返回 false
        return false;
    }

    /**
     * 重载清除缓存
     * @param string $sCachename
     * @return boolean
     */
    protected function removeCache($sCachename){
        // 不使用缓存，直接返回 true
        return true;
    }

    /**
     * log overwrite
     * @see Wechat::log()
     */
    protected function log($log){
        if ($this->debug) {
            if (function_exists($this->logcallback)) {
                if (is_array($log)) $log = print_r($log,true);
                return call_user_func($this->logcallback,$log);
            }
        }
        return false;
    }

    /**
     * TP用已知的jsapi_ticket签名
     *
     * @param $url
     * @param $jsapi_ticket
     * @param $appid
     * @return array|bool
     */
    public function TPgetJsSign( $sUrl , $sJsapiTicket , $sCorpid)
    {
        $timestamp = time();
        $noncestr = $this->generateNonceStr();

        $ret = strpos($sUrl,'#');
        if ($ret)
            $sUrl = substr($sUrl,0,$ret);
        $sUrl = trim($sUrl);
        if (empty($sUrl))
            return false;
        $arrdata = array("timestamp" => $timestamp, "noncestr" => $noncestr, "url" => $sUrl, "jsapi_ticket" => $sJsapiTicket);
        $sign = $this->getSignature($arrdata);
        if (!$sign)
            return false;
        $signPackage = array(
            "corpid"     => $sCorpid,
            "noncestr"  => $noncestr,
            "timestamp" => $timestamp,
            "url"       => $sUrl,
            "signature" => $sign
        );
        return $signPackage;
    }

    /**
     * 返回access_token
     *
     * @return bool
     */
    public function getAccessToken(){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        return $this->sAccessToken;
    }

    /**
     * For weixin server validation
     */
    private function checkSignature($str='')
    {
        $signature = isset($_GET["signature"])?$_GET["signature"]:'';
        $signature = isset($_GET["msg_signature"])?$_GET["msg_signature"]:$signature; //如果存在加密验证则用加密验证段
        $timestamp = isset($_GET["timestamp"])?$_GET["timestamp"]:'';
        $nonce = isset($_GET["nonce"])?$_GET["nonce"]:'';

        $token = $this->sAccessToken;
        $tmpArr = array($token, $timestamp, $nonce,$str);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }

    /**
     * For weixin server validation
     * @param bool $return 是否返回
     */
    public function valid($return=false)
    {
        $encryptStr="";
        if ($_SERVER['REQUEST_METHOD'] == "POST") {
            $postStr = file_get_contents("php://input");
            $array = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $this->encrypt_type = isset($_GET["encrypt_type"]) ? $_GET["encrypt_type"]: '';
            if ($this->encrypt_type == 'aes') { //aes加密
                $this->log($postStr);
                $encryptStr = $array['Encrypt'];
                $pc = new \Nanxihang\Wechat\Internal\Prpcrypt($this->sEncodingAesKey);
                $array = $pc->decrypt($encryptStr,$this->sCorpId);
                if (!isset($array[0]) || ($array[0] != 0)) {
                    if (!$return) {
                        die('decrypt error!');
                    } else {
                        return false;
                    }
                }
                $this->postxml = $array[1];
                if (!$this->sCorpId)
                    $this->sCorpId = $array[2];//为了没有appid的订阅号。
            } else {
                $this->postxml = $postStr;
            }
        } elseif (isset($_GET["echostr"])) {
            $echoStr = $_GET["echostr"];
            if ($return) {
                if ($this->checkSignature())
                    return $echoStr;
                else
                    return false;
            } else {
                if ($this->checkSignature())
                    die($echoStr);
                else
                    die('no access');
            }
        }

        if (!$this->checkSignature($encryptStr)) {
            if ($return)
                return false;
            else
                die('no access');
        }
        return true;
    }

    /**
     * 获取access_token
     * @param string $sCorpId 如在类初始化时已提供，则可为空
     * @param string $sCorpSecret 如在类初始化时已提供，则可为空
     * @param string $sAccessToken 手动指定access_token，非必要情况不建议用
     */
    public function checkAuth($sCorpId='',$sCorpSecret='',$sAccessToken=''){
        if (!$sCorpId || !$sCorpSecret) {
            $sCorpId        = $this->sCorpId;
            $sCorpSecret    = $this->sCorpSecret;
        }
        if ($sAccessToken) { //手动指定token，优先使用
            $this->sAccessToken = $sAccessToken;
            return $this->sAccessToken;
        }

        $authname = 'wechat_access_token'.$sCorpId;
        if ($rs = $this->getCache($authname))  {
            $this->sAccessToken = $rs;
            return $rs;
        }
        $result = $this->http_get( $this->sApiUrl . 'cgi-bin/gettoken?'.'corpid='.$sCorpId.'&corpsecret='.$sCorpSecret );
        if ($result) {
            $json = json_decode($result,true);
            if (!$json || ( isset($json['errcode']) && $json['errcode'] !=0 ) ) {
                $this->errCode = $json['errcode'];
                $this->errMsg  = $json['errmsg'];
                return false;
            }
            $this->sAccessToken = $json['access_token'];
            $expire = $json['expires_in'] ? intval($json['expires_in'])-100 : 3600;
            $this->setCache( $authname , $this->sAccessToken , $expire );
            return $this->sAccessToken;
        }
        return false;
    }

    /**
     * 删除验证数据
     * @param string $sCorpId
     */
    public function resetAuth($sCorpId=''){
        if (!$sCorpId) $sCorpId = $this->sCorpId;
        $this->sAccessToken = '';
        $authname = 'wechat_access_token'.$sCorpId;
        $this->removeCache($authname);
        return true;
    }

    /**
     * 删除JSAPI授权TICKET
     * @param string $sCorpId 用于多个appid时使用
     */
    public function resetJsTicket($sCorpId=''){
        if (!$sCorpId) $sCorpId = $this->sCorpId;
        $this->sJsapiTicket = '';
        $authname = 'wechat_jsapi_ticket'.$sCorpId;
        $this->removeCache($authname);
        return true;
    }

    /**
     * 获取JSAPI授权TICKET
     * @param string $sCorpId 用于多个appid时使用,可空
     * @param string $sJsapiTicket 手动指定jsapi_ticket，非必要情况不建议用
     */
    public function getJsTicket($sCorpId = '',$sJsapiTicket = ''){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        if (!$sCorpId) $sCorpId = $this->sCorpId;
        if ($sJsapiTicket) { //手动指定token，优先使用
            $this->sJsapiTicket = $sJsapiTicket;
            return $this->sJsapiTicket;
        }
        $authname = 'wechat_jsapi_ticket'.$sCorpId;
        if ($rs = $this->getCache($authname))  {
            $this->sJsapiTicket = $rs;
            return $rs;
        }
        $result = $this->http_get($this->sApiUrl.'cgi-bin/ticket/get?'.'access_token='.$this->sAccessToken.'&type=agent_config');
        if ($result)
        {
            $json = json_decode($result,true);
            if (!$json || ( !empty($json['errcode']) && $json['errcode'] != 0 )) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            $this->sJsapiTicket = $json['ticket'];
            $expire = $json['expires_in'] ? intval($json['expires_in'])-100 : 3600;
            $this->setCache($authname,$this->sJsapiTicket,$expire);
            return $this->sJsapiTicket;
        }
        return false;
    }

    /**
     * 获取JSAPI get_jsapi_ticket 授权TICKET
     *
     * https://qydev.weixin.qq.com/wiki/index.php?title=%E5%BE%AE%E4%BF%A1JS-SDK%E6%8E%A5%E5%8F%A3
     *
     * 生成签名之前必须先了解一下jsapi_ticket，jsapi_ticket是企业号号用于调用微信JS接口的临时票据。正常情况下，jsapi_ticket的有效期为7200秒，通过access_token来获取。由于获取jsapi_ticket的api调用次数非常有限，频繁刷新jsapi_ticket会导致api调用受限，影响自身业务，开发者必须在自己的服务全局缓存jsapi_ticket。
     *
     * @param string $sCorpId 用于多个appid时使用,可空
     * @param string $sJsapiTicket 手动指定jsapi_ticket，非必要情况不建议用
     */
    public function getJsApiTicket($sCorpId = '',$sJsapiTicket = '') {

        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        if (!$sCorpId) $sCorpId = $this->sCorpId;
        if ($sJsapiTicket) { //手动指定token，优先使用
            $this->sJsapiTicket = $sJsapiTicket;
            return $this->sJsapiTicket;
        }
        $authname = 'wechat_get_jsapi_ticket'.$sCorpId;
        if ($rs = $this->getCache($authname))  {
            $this->sJsapiTicket = $rs;
            return $rs;
        }
        $result = $this->http_get($this->sApiUrl.'cgi-bin/get_jsapi_ticket?'.'access_token='.$this->sAccessToken);
        if ($result)
        {
            $json = json_decode($result,true);
            if (!$json || ( !empty($json['errcode']) && $json['errcode'] != 0 )) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            $this->sJsapiTicket = $json['ticket'];
            $expire = $json['expires_in'] ? intval($json['expires_in'])-100 : 3600;
            $this->setCache($authname,$this->sJsapiTicket,$expire);
            return $this->sJsapiTicket;
        }
        return false;
    }


    /**
     * 获取JsApi使用签名
     * @param string $url 网页的URL，自动处理#及其后面部分
     * @param string $timestamp 当前时间戳 (为空则自动生成)
     * @param string $noncestr 随机串 (为空则自动生成)
     * @param string $appid 用于多个appid时使用,可空
     * @return array|bool 返回签名字串
     */
    public function getJsSign($url, $timestamp=0, $noncestr='', $appid=''){
        if (!$this->sJsapiTicket && !$this->getJsTicket($appid) || !$url) return false;
        if (!$timestamp)
            $timestamp = time();
        if (!$noncestr)
            $noncestr = $this->generateNonceStr();
        $ret = strpos($url,'#');
        if ($ret)
            $url = substr($url,0,$ret);
        $url = trim($url);
        if (empty($url))
            return false;
        $arrdata = array("agentid"=>$this->sAgentId,"timestamp" => $timestamp, "noncestr" => $noncestr, "url" => $url, "jsapi_ticket" => $this->sJsapiTicket);
        $sign = $this->getSignature($arrdata);
        if (!$sign)
            return false;
        $signPackage = array(
            "corpid"    => $this->sCorpId,
            "agentid"   => $this->sAgentId,
            "nonceStr"  => $noncestr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $sign
        );
        return $signPackage;
    }

    /**
     * 微信api不支持中文转义的json结构
     * @param array $arr
     */
    static function json_encode($arr) {
        if (count($arr) == 0) return "[]";
        $parts = array ();
        $is_list = false;
        //Find out if the given array is a numerical array
        $keys = array_keys ( $arr );
        $max_length = count ( $arr ) - 1;
        if (($keys [0] === 0) && ($keys [$max_length] === $max_length )) { //See if the first key is 0 and last key is length - 1
            $is_list = true;
            for($i = 0; $i < count ( $keys ); $i ++) { //See if each key correspondes to its position
                if ($i != $keys [$i]) { //A key fails at position check.
                    $is_list = false; //It is an associative array.
                    break;
                }
            }
        }
        foreach ( $arr as $key => $value ) {
            if (is_array ( $value )) { //Custom handling for arrays
                if ($is_list)
                    $parts [] = self::json_encode ( $value ); /* :RECURSION: */
                else
                    $parts [] = '"' . $key . '":' . self::json_encode ( $value ); /* :RECURSION: */
            } else {
                $str = '';
                if (! $is_list)
                    $str = '"' . $key . '":';
                //Custom handling for multiple data types
                if (!is_string ( $value ) && is_numeric ( $value ) && $value<2000000000)
                    $str .= $value; //Numbers
                elseif ($value === false)
                    $str .= 'false'; //The booleans
                elseif ($value === true)
                    $str .= 'true';
                else
                    $str .= '"' . addslashes ( $value ) . '"'; //All other things
                // :TODO: Is there any more datatype we should be in the lookout for? (Object?)
                $parts [] = $str;
            }
        }
        $json = implode ( ',', $parts );
        if ($is_list)
            return '[' . $json . ']'; //Return numerical JSON
        return '{' . $json . '}'; //Return associative JSON
    }

    /**
     * 获取签名
     * @param array $arrdata 签名数组
     * @param string $method 签名方法
     * @return boolean|string 签名值
     */
    public function getSignature($arrdata,$method="sha1") {
        if (!function_exists($method)) return false;
        ksort($arrdata);
        $paramstring = "";
        foreach($arrdata as $key => $value)
        {
            if(strlen($paramstring) == 0)
                $paramstring .= $key . "=" . $value;
            else
                $paramstring .= "&" . $key . "=" . $value;
        }
        $Sign = $method($paramstring);
        return $Sign;
    }

    /**
     * 生成随机字串
     * @param number $length 长度，默认为16，最长为32字节
     * @return string
     */
    public function generateNonceStr($length=16){
        // 密码字符集，可任意添加你需要的字符
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for($i = 0; $i < $length; $i++)
        {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }

    /**
     * oauth 授权跳转接口
     * @param string $sCallback 回调URI
     * @param string $sState 重定向后会带上state参数，企业可以填写a-zA-Z0-9的参数值，长度不可超过128个字节
     * @param string $sScope 应用授权作用域,企业自建应用固定填写：snsapi_base。政务微信使用：snsapi_privateinfo
     * @return string
     */
    public function getOauthRedirect($sCallback , $sState = '' , $sScope = 'snsapi_base'){
        return 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$this->sCorpId.'&agentid='.$this->sAgentId.'&redirect_uri='.urlencode($sCallback).'&response_type=code&scope='.$sScope.'&state='.$sState.'#wechat_redirect';
    }

    /**
     * 获取访问用户身份
     *
     * @param $sCode
     * @return bool|mixed
     */
    public function getUserInfo($sCode){
        if (!$this->sAccessToken && !$this->checkAuth()) {
            return false;
        }
        $result = $this->http_get($this->sApiUrl.'cgi-bin/user/getuserinfo?'.'access_token='.$this->sAccessToken.'&code='.$sCode);
        if ($result)
        {
            $json = json_decode($result,true);
            if ( isset($json['errcode']) && $json['errcode'] !=0 ) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 使用user_ticket获取成员详情
     * https://developer.work.weixin.qq.com/document/path/95833
     *
     * @param $sUserTicket
     * @return bool|mixed
     */
    public function getUserDetail($sUserTicket){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $result = $this->http_post($this->sApiUrl.'cgi-bin/user/getuserdetail?'.'access_token='.$this->sAccessToken,self::json_encode(array('user_ticket'=>$sUserTicket)));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     *  使用用户手机号，获取用户ID
     *  https://developer.work.weixin.qq.com/document/path/95402
     *
     * @param $sMobile
     *
     * @return bool|mixed
     */
    public function getUserId( $sMobile ){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $result = $this->http_post($this->sApiUrl.'cgi-bin/user/getuserid?'.'access_token='.$this->sAccessToken,self::json_encode(array('mobile'=>$sMobile)));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 读取成员
     *
     * @param $sUserId
     * @return bool|mixed
     */
    public function getUser($sUserId){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $result = $this->http_get($this->sApiUrl.'cgi-bin/user/get?'.'access_token='.$this->sAccessToken.'&userid='.$sUserId);
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 创建部门
     *
     * @param $sName
     * @param $nParentid
     * @param string $nOrder
     * @param string $nId
     * @return bool|mixed
     */
    public function createDepartment($sName,$nParentid,$nOrder = '',$nId = ''){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $arrData = [];
        $arrData['name'] = $sName;
        $arrData['parentid'] = $sName;
        if(strlen($nOrder) > 0){
            $arrData['order'] = $nOrder;
        }
        if(strlen($nId) > 0) {
            $arrData['id'] = $nId;
        }

        $result = $this->http_post($this->sApiUrl.'cgi-bin/department/create?'.'access_token='.$this->sAccessToken,self::json_encode($arrData));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 更新部门
     *
     * @param $nId
     * @param $sName
     * @param $nParentid
     * @param string $nOrder
     * @return bool|mixed
     */
    public function updateDepartment($nId,$sName,$nParentid,$nOrder = ''){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $arrData = [];
        $arrData['name'] = $sName;
        $arrData['parentid'] = $sName;
        if(strlen($nOrder) > 0){
            $arrData['order'] = $nOrder;
        }
        if(strlen($nId) > 0) {
            $arrData['id'] = $nId;
        }

        $result = $this->http_post($this->sApiUrl.'cgi-bin/department/update?'.'access_token='.$this->sAccessToken,self::json_encode($arrData));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 删除部门
     *
     * @param $nId
     * @return bool|mixed
     */
    public function deleteDepartment($nId){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $result = $this->http_get($this->sApiUrl.'cgi-bin/department/delete?'.'access_token='.$this->sAccessToken.'&id='.$nId);
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 获取部门列表
     *
     * @param int $nId
     * @return bool|mixed
     */
    public function getDepartmentList($nId = 0){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $result = $this->http_get($this->sApiUrl.'cgi-bin/department/list?'.'access_token='.$this->sAccessToken.'&id='.$nId);
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 上传临时素材，有效期为3天(认证后的订阅号可用)
     * 注意：上传大文件时可能需要先调用 set_time_limit(0) 避免超时
     * 注意：数组的键值任意，但文件名前必须加@，使用单引号以避免本地路径斜杠被转义
     * 注意：临时素材的media_id是可复用的！
     * @param array $data {"media":'@Path\filename.jpg'}
     * @param type 类型：图片:image 语音:voice 视频:video 缩略图:thumb
     * @return boolean|array
     */
    public function uploadMedia($data, $type){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        //原先的上传多媒体文件接口使用 self::UPLOAD_MEDIA_URL 前缀
        $result = $this->http_post($this->sApiUrl.'cgi-bin/media/upload?'.'access_token='.$this->sAccessToken.'&type='.$type,$data,true);
        if ($result)
        {
            $json = json_decode($result,true);
            if (!$json || (!empty($json['errcode'])  && $json['errcode'] != 0 )) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 上传图片，本接口所上传的图片不占用公众号的素材库中图片数量的5000个的限制。图片仅支持jpg/png格式，大小必须在1MB以下。 (认证后的订阅号可用)
     * 注意：上传大文件时可能需要先调用 set_time_limit(0) 避免超时
     * 注意：数组的键值任意，但文件名前必须加@，使用单引号以避免本地路径斜杠被转义
     * @param array $data {"media":'@Path\filename.jpg'}
     *
     * @return boolean|array
     */
    public function uploadImg($data){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        //原先的上传多媒体文件接口使用 self::UPLOAD_MEDIA_URL 前缀
        $result = $this->http_post($this->sApiUrl.'cgi-bin/media/uploadimg?'.'access_token='.$this->sAccessToken,$data,true);
        if ($result)
        {
            $json = json_decode($result,true);
            if (!$json || (!empty($json['errcode'])  && $json['errcode'] != 0 )) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 获取临时素材
     * @param string $media_id 媒体文件id
     * @param boolean $is_video 是否为视频文件，默认为否
     * @return raw data
     */
    public function getMedia($media_id,$is_video=false){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        //原先的上传多媒体文件接口使用 self::UPLOAD_MEDIA_URL 前缀
        //如果要获取的素材是视频文件时，不能使用https协议，必须更换成http协议
        $result = $this->http_get($this->sApiUrl.'cgi-bin/media/get?'.'access_token='.$this->sAccessToken.'&media_id='.$media_id);
        if ($result)
        {
            if (is_string($result)) {
                $json = json_decode($result,true);
                if (isset($json['errcode']) && $json['errcode'] != 0) {
                    $this->errCode = $json['errcode'];
                    $this->errMsg = $json['errmsg'];
                    return false;
                }
            }
            return $result;
        }
        return false;
    }

    /**
     * 获取高清语音素材
     * @param string $media_id 媒体文件id
     * @param boolean $is_video 是否为视频文件，默认为否
     * @return raw data
     */
    public function getMediaJssdk($media_id,$is_video=false){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        //原先的上传多媒体文件接口使用 self::UPLOAD_MEDIA_URL 前缀
        //如果要获取的素材是视频文件时，不能使用https协议，必须更换成http协议
        $result = $this->http_get($this->sApiUrl.'cgi-bin/media/get/jssdk?'.'access_token='.$this->sAccessToken.'&media_id='.$media_id);
        if ($result)
        {
            if (is_string($result)) {
                $json = json_decode($result,true);
                if (isset($json['errcode']) && $json['errcode'] != 0 ) {
                    $this->errCode = $json['errcode'];
                    $this->errMsg = $json['errmsg'];
                    return false;
                }
            }
            return $result;
        }
        return false;
    }

    /**
     * 发送文本消息
     *
     * @param $arrToUser
     * @param $arrToParty
     * @param $arrToTag
     * @param $sContent
     * @param int $nSafe
     * @return bool|mixed
     */
    public function sendMessageText($arrToUser,$arrToParty,$arrToTag,$sContent,$nSafe = 0){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $arrData = [];
        if(strlen($arrToUser) > 0 || count($arrToUser) > 0){
            if(is_array($arrToUser)){
                $arrData['touser'] = implode("|", $arrToUser);
            }else{
                $arrData['touser'] = $arrToUser;
            }
        }
        if(strlen($arrToParty) > 0 || count($arrToParty) > 0){
            if(is_array($arrToUser)){
                $arrData['toparty'] = implode("|", $arrToParty);
            }else{
                $arrData['toparty'] = $arrToParty;
            }
        }
        if(strlen($arrToTag) > 0 || count($arrToTag) > 0){
            if(is_array($arrToUser)){
                $arrData['totag'] = implode("|", $arrToTag);
            }else{
                $arrData['totag'] = $arrToTag;
            }
        }
        $arrData['msgtype'] = 'text';
        $arrData['text']['content'] = $sContent;
        $arrData['safe'] = $nSafe;

        $result = $this->http_post($this->sApiUrl.'cgi-bin/message/send?'.'access_token='.$this->sAccessToken,self::json_encode($arrData));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode'])&& $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 发送图片消息
     *
     * @param $arrToUser
     * @param $arrToParty
     * @param $arrToTag
     * @param $sMediaId
     * @param int $nSafe
     * @return bool|mixed
     */
    public function sendMessageImage($arrToUser,$arrToParty,$arrToTag,$sMediaId,$nSafe = 0){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $arrData = [];
        if(strlen($arrToUser) > 0 || count($arrToUser) > 0){
            if(is_array($arrToUser)){
                $arrData['touser'] = implode("|", $arrToUser);
            }else{
                $arrData['touser'] = $arrToUser;
            }
        }
        if(strlen($arrToParty) > 0 || count($arrToParty) > 0){
            if(is_array($arrToUser)){
                $arrData['toparty'] = implode("|", $arrToParty);
            }else{
                $arrData['toparty'] = $arrToParty;
            }
        }
        if(strlen($arrToTag) > 0 || count($arrToTag) > 0){
            if(is_array($arrToUser)){
                $arrData['totag'] = implode("|", $arrToTag);
            }else{
                $arrData['totag'] = $arrToTag;
            }
        }
        $arrData['msgtype'] = 'image';
        $arrData['image']['media_id'] = $sMediaId;
        $arrData['safe'] = $nSafe;

        $result = $this->http_post($this->sApiUrl.'cgi-bin/message/send?'.'access_token='.$this->sAccessToken,self::json_encode($arrData));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 发送语音消息
     *
     * @param $arrToUser
     * @param $arrToParty
     * @param $arrToTag
     * @param $sMediaId
     * @param int $nSafe
     * @return bool|mixed
     */
    public function sendMessageVoice($arrToUser,$arrToParty,$arrToTag,$sMediaId,$nSafe = 0){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $arrData = [];
        if(strlen($arrToUser) > 0 || count($arrToUser) > 0){
            if(is_array($arrToUser)){
                $arrData['touser'] = implode("|", $arrToUser);
            }else{
                $arrData['touser'] = $arrToUser;
            }
        }
        if(strlen($arrToParty) > 0 || count($arrToParty) > 0){
            if(is_array($arrToUser)){
                $arrData['toparty'] = implode("|", $arrToParty);
            }else{
                $arrData['toparty'] = $arrToParty;
            }
        }
        if(strlen($arrToTag) > 0 || count($arrToTag) > 0){
            if(is_array($arrToUser)){
                $arrData['totag'] = implode("|", $arrToTag);
            }else{
                $arrData['totag'] = $arrToTag;
            }
        }
        $arrData['msgtype'] = 'voice';
        $arrData['voice']['media_id'] = $sMediaId;
        $arrData['safe'] = $nSafe;

        $result = $this->http_post($this->sApiUrl.'cgi-bin/message/send?'.'access_token='.$this->sAccessToken,self::json_encode($arrData));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 发送视频消息
     *
     * @param $arrToUser
     * @param $arrToParty
     * @param $arrToTag
     * @param $sMediaId
     * @param int $nSafe
     * @return bool|mixed
     */
    public function sendMessageVideo($arrToUser,$arrToParty,$arrToTag,$sMediaId,$sTitle,$sDescription,$nSafe = 0){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $arrData = [];
        if(strlen($arrToUser) > 0 || count($arrToUser) > 0){
            if(is_array($arrToUser)){
                $arrData['touser'] = implode("|", $arrToUser);
            }else{
                $arrData['touser'] = $arrToUser;
            }
        }
        if(strlen($arrToParty) > 0 || count($arrToParty) > 0){
            if(is_array($arrToUser)){
                $arrData['toparty'] = implode("|", $arrToParty);
            }else{
                $arrData['toparty'] = $arrToParty;
            }
        }
        if(strlen($arrToTag) > 0 || count($arrToTag) > 0){
            if(is_array($arrToUser)){
                $arrData['totag'] = implode("|", $arrToTag);
            }else{
                $arrData['totag'] = $arrToTag;
            }
        }
        $arrData['msgtype'] = 'video';
        $arrData['video']['media_id']       = $sMediaId;
        $arrData['video']['title']          = $sTitle;
        $arrData['video']['description']    = $sDescription;
        $arrData['safe'] = $nSafe;

        $result = $this->http_post($this->sApiUrl.'cgi-bin/message/send?'.'access_token='.$this->sAccessToken,self::json_encode($arrData));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 发送文件消息
     *
     * @param $arrToUser
     * @param $arrToParty
     * @param $arrToTag
     * @param $sMediaId
     * @param int $nSafe
     * @return bool|mixed
     */
    public function sendMessageFile($arrToUser,$arrToParty,$arrToTag,$sMediaId,$nSafe = 0){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $arrData = [];
        if(strlen($arrToUser) > 0 || count($arrToUser) > 0){
            if(is_array($arrToUser)){
                $arrData['touser'] = implode("|", $arrToUser);
            }else{
                $arrData['touser'] = $arrToUser;
            }
        }
        if(strlen($arrToParty) > 0 || count($arrToParty) > 0){
            if(is_array($arrToUser)){
                $arrData['toparty'] = implode("|", $arrToParty);
            }else{
                $arrData['toparty'] = $arrToParty;
            }
        }
        if(strlen($arrToTag) > 0 || count($arrToTag) > 0){
            if(is_array($arrToUser)){
                $arrData['totag'] = implode("|", $arrToTag);
            }else{
                $arrData['totag'] = $arrToTag;
            }
        }
        $arrData['msgtype'] = 'file';
        $arrData['file']['media_id'] = $sMediaId;
        $arrData['safe'] = $nSafe;

        $result = $this->http_post($this->sApiUrl.'cgi-bin/message/send?'.'access_token='.$this->sAccessToken,self::json_encode($arrData));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 发送文本卡片消息
     *
     * @param $arrToUser
     * @param $arrToParty
     * @param $arrToTag
     * @param $sMediaId
     * @param int $nSafe
     * @return bool|mixed
     */
    public function sendMessageNews($arrToUser,$arrToParty,$arrToTag,$sTitle,$sDescription,$sUrl,$sPicurl){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $arrData = [];
        if(strlen($arrToUser) > 0 || count($arrToUser) > 0){
            if(is_array($arrToUser)){
                $arrData['touser'] = implode("|", $arrToUser);
            }else{
                $arrData['touser'] = $arrToUser;
            }
        }
        if(strlen($arrToParty) > 0 || count($arrToParty) > 0){
            if(is_array($arrToUser)){
                $arrData['toparty'] = implode("|", $arrToParty);
            }else{
                $arrData['toparty'] = $arrToParty;
            }
        }
        if(strlen($arrToTag) > 0 || count($arrToTag) > 0){
            if(is_array($arrToUser)){
                $arrData['totag'] = implode("|", $arrToTag);
            }else{
                $arrData['totag'] = $arrToTag;
            }
        }
        $arrData['msgtype'] = 'news';
        $arrData['articles']['title']          = $sTitle;
        if(strlen($sDescription) > 0){
            $arrData['articles']['description']    = $sDescription;
        }
        $arrData['url'] = $sUrl;
        if(strlen($sPicurl) > 0){
            $arrData['articles']['picurl']    = $sPicurl;
        }

        $result = $this->http_post($this->sApiUrl.'cgi-bin/message/send?'.'access_token='.$this->sAccessToken,self::json_encode($arrData));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * 发送markdown消息
     *
     * @param $arrToUser
     * @param $arrToParty
     * @param $arrToTag
     * @param $sMediaId
     * @param int $nSafe
     * @return bool|mixed
     */
    public function sendMessageMarkdown($arrToUser,$arrToParty,$arrToTag,$sContent,$nSafe = 0){
        if (!$this->sAccessToken && !$this->checkAuth()) return false;
        $arrData = [];
        if(strlen($arrToUser) > 0 || count($arrToUser) > 0){
            if(is_array($arrToUser)){
                $arrData['touser'] = implode("|", $arrToUser);
            }else{
                $arrData['touser'] = $arrToUser;
            }
        }
        if(strlen($arrToParty) > 0 || count($arrToParty) > 0){
            if(is_array($arrToUser)){
                $arrData['toparty'] = implode("|", $arrToParty);
            }else{
                $arrData['toparty'] = $arrToParty;
            }
        }
        if(strlen($arrToTag) > 0 || count($arrToTag) > 0){
            if(is_array($arrToUser)){
                $arrData['totag'] = implode("|", $arrToTag);
            }else{
                $arrData['totag'] = $arrToTag;
            }
        }
        $arrData['msgtype'] = 'markdown';
        $arrData['markdown']['content'] = $sContent;
        $arrData['safe'] = $nSafe;

        $result = $this->http_post($this->sApiUrl.'cgi-bin/message/send?'.'access_token='.$this->sAccessToken,self::json_encode($arrData));
        if ($result)
        {
            $json = json_decode($result,true);
            if (isset($json['errcode']) && $json['errcode'] != 0) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }

    /**
     * GET 请求
     * @param string $url
     */
    public function http_get($url){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }

    /**
     * POST 请求
     * @param string $url
     * @param array $param
     * @param boolean $post_file 是否文件上传
     * @return string content
     */
    public function http_post($url,$param,$post_file=false){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (PHP_VERSION_ID >= 50500 && class_exists('\CURLFile')) {
            $is_curlFile = true;
        } else {
            $is_curlFile = false;
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                curl_setopt($oCurl, CURLOPT_SAFE_UPLOAD, false);
            }
        }
        if (is_string($param)) {
            $strPOST = $param;
        }elseif($post_file) {
            if($is_curlFile) {
                foreach ($param as $key => $val) {
                    if (substr($val, 0, 1) == '@') {
                        $param[$key] = new \CURLFile(realpath(substr($val,1)));
                    }
                }
            }
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach($param as $key=>$val){
                $aPOST[] = $key."=".urlencode($val);
            }
            $strPOST =  join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_POST,true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }

    //错误编码转换
    private function codeToText($sCode = ''){
        if(strlen($sCode) <= 0){
            return '';
        }
        $sCode = (string)$sCode;
        $arrCode['-1']          = '系统繁忙';
        $arrCode['0']           = '请求成功';
        $arrCode['40001']       = '不合法的secret参数';
        $arrCode['40003']       = '无效的UserID';
        $arrCode['40004']       = '不合法的媒体文件类型';
        $arrCode['40005']       = '不合法的type参数';
        $arrCode['40006']       = '不合法的文件大小';
        $arrCode['40007']       = '不合法的media_id参数';
        $arrCode['40008']       = '不合法的msgtype参数';
        $arrCode['40009']       = '上传图片大小不是有效值';
        $arrCode['40011']       = '上传视频大小不是有效值 视频大小的系统限制，参考”上传的媒体文件部分“';
        $arrCode['40013']       = '不合法的CorpID';
        $arrCode['40014']       = '不合法的access_token';
        $arrCode['40016']       = '不合法的按钮个数';
        $arrCode['40017']       = '不合法的按钮类型';
        $arrCode['40018']       = '不合法的按钮名字长度';
        $arrCode['40019']       = '不合法的按钮KEY长度';
        $arrCode['40020']       = '不合法的按钮URL长度';
        $arrCode['40022']       = '不合法的子菜单级数';
        $arrCode['40023']       = '不合法的子菜单按钮个数';
        $arrCode['40024']       = '不合法的子菜单按钮类型';
        $arrCode['40025']       = '不合法的子菜单按钮名字长度';
        $arrCode['40026']       = '不合法的子菜单按钮KEY长度';
        $arrCode['40027']       = '不合法的子菜单按钮URL长度';
        $arrCode['40029']       = '不合法的oauth_code';
        $arrCode['40031']       = '不合法的UserID列表';
        $arrCode['40032']       = '不合法的UserID列表长度';
        $arrCode['40033']       = '不合法的请求字符';
        $arrCode['40035']       = '不合法的参数';
        $arrCode['40050']       = 'chatid不存在';
        $arrCode['40054']       = '不合法的子菜单url域名';
        $arrCode['40055']       = '不合法的菜单url域名';
        $arrCode['40056']       = '不合法的agentid';
        $arrCode['40057']       = '不合法的callbackurl或者callbackurl验证失败';
        $arrCode['40058']       = '不合法的参数 传递参数不符合系统要求';
        $arrCode['40059']       = '不合法的上报地理位置标志位';
        $arrCode['40063']       = '参数为空';
        $arrCode['40066']       = '不合法的部门列表';
        $arrCode['40068']       = '不合法的标签ID';
        $arrCode['40070']       = '指定的标签范围结点全部无效';
        $arrCode['40071']       = '不合法的标签名字';
        $arrCode['40072']       = '不合法的标签名字长度';
        $arrCode['40073']       = '不合法的openid';
        $arrCode['40074']       = 'news消息不支持保密消息类型';
        $arrCode['40078']       = '不合法的auth_code参数';
        $arrCode['40086']       = '不合法的第三方应用appid';
        $arrCode['40088']       = 'jobid不存在';
        $arrCode['40089']       = '批量任务的结果已清理';
        $arrCode['40091']       = 'secret不合法';
        $arrCode['40093']       = '不合法的jsapi_ticket参数';
        $arrCode['40094']       = '不合法的URL';
        $arrCode['41001']       = '缺少access_token参数';
        $arrCode['41002']       = '缺少corpid参数';
        $arrCode['41004']       = '缺少secret参数';
        $arrCode['41006']       = '缺少media_id参数';
        $arrCode['41008']       = '缺少auth code参数';
        $arrCode['41009']       = '缺少userid参数';
        $arrCode['41010']       = '缺少url参数';
        $arrCode['41011']       = '缺少agentid参数';
        $arrCode['41033']       = '缺少 description 参数';
        $arrCode['41016']       = '缺少title参数';
        $arrCode['41019']       = '缺少department 参数';
        $arrCode['41017']       = '缺少tagid参数';
        $arrCode['41021']       = '缺少suite_id参数';
        $arrCode['41025']       = '缺少permanent_code参数';
        $arrCode['42001']       = 'access_token已过期';
        $arrCode['42007']       = 'pre_auth_code已过期';
        $arrCode['42009']       = 'suite_access_token已过期';
        $arrCode['44001']       = '多媒体文件为空';
        $arrCode['44004']       = '文本消息content参数为空';
        $arrCode['45001']       = '多媒体文件大小超过限制';
        $arrCode['45002']       = '消息内容大小超过限制';
        $arrCode['45004']       = '应用description参数长度不符合系统限制';
        $arrCode['45007']       = '语音播放时间超过限制';
        $arrCode['45008']       = '图文消息的文章数量不符合系统限制';
        $arrCode['45022']       = '应用name参数长度不符合系统限制';
        $arrCode['45024']       = '帐号数量超过上限';
        $arrCode['45032']       = '图文消息author参数长度超过限制';
        $arrCode['46003']       = '菜单未设置';
        $arrCode['46004']       = '指定的用户不存在';
        $arrCode['48002']       = 'API接口无权限调用';
        $arrCode['48003']       = '不合法的suite_id';
        $arrCode['48004']       = '授权关系无效';
        $arrCode['48005']       = 'API接口已废弃';
        $arrCode['50001']       = 'redirect_url未登记可信域名';
        $arrCode['50002']       = '成员不在权限范围';
        $arrCode['50003']       = '应用已禁用';
        $arrCode['60001']       = '部门长度不符合限制';
        $arrCode['60003']       = '部门ID不存在';
        $arrCode['60004']       = '父部门不存在';
        $arrCode['60005']       = '部门下存在成员';
        $arrCode['60006']       = '部门下存在子部门';
        $arrCode['60007']       = '不允许删除根部门';
        $arrCode['60008']       = '部门已存在';
        $arrCode['60009']       = '部门名称含有非法字符';
        $arrCode['60010']       = '部门存在循环关系';
        $arrCode['60011']       = '指定的成员/部门/标签参数无权限';
        $arrCode['60012']       = '不允许删除默认应用';
        $arrCode['60028']       = '不允许修改第三方应用的主页 URL';
        $arrCode['60102']       = 'UserID已存在';
        $arrCode['60103']       = '手机号码不合法';
        $arrCode['60104']       = '手机号码已存在';
        $arrCode['60105']       = '邮箱不合法';
        $arrCode['60106']       = '邮箱已存在';
        $arrCode['60107']       = '微信号不合法';
        $arrCode['60110']       = '用户所属部门数量超过限制';
        $arrCode['60111']       = 'UserID不存在';
        $arrCode['60112']       = '成员name参数不合法';
        $arrCode['60123']       = '无效的部门id';
        $arrCode['60124']       = '无效的父部门id';
        $arrCode['60125']       = '非法部门名字';
        $arrCode['60127']       = '缺少department参数';
        $arrCode['80001']       = '可信域名不正确，或者无ICP备案';
        $arrCode['81001']       = '部门下的结点数超过限制（3W）';
        $arrCode['81002']       = '部门最多15层';
        $arrCode['81011']       = '无权限操作标签';
        $arrCode['81013']       = 'UserID、部门ID、标签ID全部非法或无权限';
        $arrCode['81014']       = '标签添加成员，单次添加user或party过多';
        $arrCode['82001']       = '指定的成员/部门/标签全部无效';
        $arrCode['82002']       = '不合法的PartyID列表长度';
        $arrCode['82003']       = '不合法的TagID列表长度';
        $arrCode['84014']       = '成员票据过期';
        $arrCode['84015']       = '成员票据无效 确认user_ticket参数来源是否正确。';
        $arrCode['84019']       = '缺少templateid参数';
        $arrCode['84020']       = 'templateid不存在';
        $arrCode['84021']       = '缺少register_code参数';
        $arrCode['84022']       = '无效的register_code参数';
        $arrCode['84023']       = '不允许调用设置通讯录同步完成接口';
        $arrCode['85002']       = '包含不合法的词语';
        $arrCode['85004']       = '每单位每个月设置的可信域名不可超过20个';
        $arrCode['85005']       = '可信域名未通过所有权校验';
        $arrCode['86001']       = '参数 chatid 不合法';
        $arrCode['86003']       = '参数 chatid 不存在';
        $arrCode['86216']       = '存在非法会话成员ID';
        $arrCode['86217']       = '会话发送者不在会话成员列表中';
        $arrCode['86220']       = '指定的会话参数不合法';
        $arrCode['91040']       = '获取ticket的类型无效';
        $arrCode['301002']      = '无权限操作指定的应用';
        $arrCode['301005']      = '不允许删除创建者';
        $arrCode['301012']      = '参数 position';
        $arrCode['301013']      = '参数 telephone 不合法';
        $arrCode['301014']      = '参数 english_name 不合法';
        $arrCode['301015']      = '参数 mediaid 不合法';
        $arrCode['301016']      = '上传语音文件不符合';
        $arrCode['301017']      = '上传语音文件仅支持AMR格式';
        $arrCode['301021']      = '参数 userid 无效';
        $arrCode['301023']      = 'useridlist非法或超过限额';
        $arrCode['302003']      = '批量导入任务的文件中userid有重复';
        $arrCode['302004']      = '组织架构不合法（1不是一棵树，2 多个一样的partyid，3 partyid空，4 partyid name 空，5 同一个父节点下有两个子节点 部门名字一样 可能是以上情况，请一一排查）';
        $arrCode['302005']      = '批量导入系统失败，请重新尝试导入';
        $arrCode['302006']      = '批量导入任务的文件中partyid有重复';
        $arrCode['302007']      = '批量导入任务的文件中，同一个部门下有两个子部门名字一样';
        $arrCode['302009']      = '非法的groupid';
        $arrCode['302012']      = '重复的order';
        $arrCode['302015']      = '缺少groupid';
        $arrCode['302016']      = '应用位于多个分组';
        $arrCode['302017']      = '非法的应用列表';
        $arrCode['302018']      = '非法的工作台展示类型';
        $arrCode['302019']      = '非法的回调token';
        $arrCode['302020']      = '非法的回调aeskey';
        $arrCode['400002']      = '初始化密码不合法';
        $arrCode['4400012']     = '手机号冲突';
        $arrCode['4400013']     = 'email冲突';
        $arrCode['4400014']     = '自定义字段的值冲突';
        $arrCode['4400015']     = '帐号冲突';
        $arrCode['4400016']     = '英文名字冲突';
        $arrCode['4400017']     = '申请验证码频率限制';
        $arrCode['4400018']     = '自定义字段格式不对';
        $arrCode['4400019']     = '帐号格式不正确';
        $arrCode['4400020']     = '邮箱格式不正确';
        $arrCode['4400021']     = '手机号格式不正确';
        $arrCode['4400022']     = '英文名字格式不正确';
        $arrCode['4400023']     = '自定义字段格式不正确';
        $arrCode['4400027']     = '英名字字段格式不正确';
        $arrCode['4400028']     = '名字字段格式不正确';
        $arrCode['4400029']     = '座机字段格式不正确';
        $arrCode['4400030']     = '职位字段格式不正确';
        $arrCode['4500000']     = '参数错误';
        $arrCode['4500002']     = '获取用户信息错误';
        $arrCode['4500004']     = 'api不可调用';
        $arrCode['4600000']     = '解散群失败';
        $arrCode['4600001']     = '获取群信息失败';
        $arrCode['4600002']     = '删除群成员失败';
        $arrCode['4600003']     = '删除文件失败';
        $arrCode['4600004']     = '加密userid失败';
        $arrCode['4600006']     = '获取群成员失败';
        $arrCode['500006']      = 'license过期';
        $arrCode['500007']      = '人数超过授权人数上线';

        return isset($arrCode[$sCode]) ? $arrCode[$sCode] : '';
    }
}
?>