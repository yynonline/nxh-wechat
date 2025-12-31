<?php
namespace Nanxihang\Wechat\Tests;

use PHPUnit\Framework\TestCase;
use Nanxihang\Wechat\WechatCmpt;

class WechatCmptTest extends TestCase
{
    public function testWechatCmptCanBeInstantiated()
    {
        $options = [
            'token' => 'test_token',
            'appid' => 'test_appid',
            'appsecret' => 'test_appsecret'
        ];
        
        $wechat = new WechatCmpt($options);
        
        $this->assertInstanceOf(WechatCmpt::class, $wechat);
    }
}