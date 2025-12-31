<?php
namespace Nanxihang\Wechat\Tests;

use PHPUnit\Framework\TestCase;
use Nanxihang\Wechat\WechatX;

class WechatXTest extends TestCase
{
    public function testWechatXCanBeInstantiated()
    {
        $options = [
            'token' => 'test_token',
            'appid' => 'test_appid',
            'appsecret' => 'test_appsecret'
        ];
        
        $wechat = new WechatX($options);
        
        $this->assertInstanceOf(WechatX::class, $wechat);
    }
    
    public function testGetAccessTokenMethodExists()
    {
        $options = [
            'token' => 'test_token',
            'appid' => 'test_appid',
            'appsecret' => 'test_appsecret'
        ];
        
        $wechat = new WechatX($options);
        
        $this->assertTrue(method_exists($wechat, 'getAccessToken'));
    }
}