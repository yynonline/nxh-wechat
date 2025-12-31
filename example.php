<?php
// 示例文件，展示如何使用库
require_once __DIR__ . '/vendor/autoload.php';

use Nanxihang\Wechat\WechatX;
use Nanxihang\Wechat\WechatCmpt;
use Nanxihang\Wechat\WechatCorp;
use Nanxihang\Wechat\MMPay;
use Nanxihang\Wechat\WechatErrCode;
use Nanxihang\Wechat\WechatCorpErrCode;

// 创建WechatX实例
$options = [
    'appid' => '',
    'appsecret' => ''
];

$wechat = new WechatX($options);
echo "WechatX instance created successfully.\n";

// 创建WechatCmpt实例
$wechatCmpt = new WechatCmpt($options);
echo "WechatCmpt instance created successfully.\n";

$accessToken = $wechatCmpt->getAccessToken();
var_dump($accessToken);

// 创建WechatCorp实例
$corpOptions = [
    'corpid' => 'your_corpid',
    'corpsecret' => 'your_corpsecret',
    'agentid' => 'your_agentid'
];

$wechatCorp = new WechatCorp($corpOptions);
echo "WechatCorp instance created successfully.\n";

// 创建MMPay实例
$payOptions = [
    'mch_id' => 'your_mch_id',
    'app_id' => 'your_app_id',
    'app_key' => 'your_app_key'
];

$mmpay = new MMPay($payOptions);
echo "MMPay instance created successfully.\n";

// 测试错误码
$errText = WechatErrCode::getErrText(40001);
echo "Error code 40001: " . ($errText ?: 'Not found') . "\n";

echo "All classes loaded successfully!\n";