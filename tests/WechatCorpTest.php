<?php
namespace Nanxihang\Wechat\Tests;

use PHPUnit\Framework\TestCase;
use Nanxihang\Wechat\WechatCorp;

class WechatCorpTest extends TestCase
{
    public function testWechatCorpCanBeInstantiated()
    {
        $options = [
            'corpid' => 'test_corpid',
            'corpsecret' => 'test_corpsecret',
            'agentid' => 'test_agentid'
        ];
        
        $wechat = new WechatCorp($options);
        
        $this->assertInstanceOf(WechatCorp::class, $wechat);
    }
}