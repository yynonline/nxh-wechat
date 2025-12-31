<?php
namespace Nanxihang\Wechat\Tests;

use PHPUnit\Framework\TestCase;
use Nanxihang\Wechat\MMPay;

class MMPayTest extends TestCase
{
    public function testMMPayCanBeInstantiated()
    {
        $options = [
            'mch_id' => 'test_mch_id',
            'app_id' => 'test_app_id',
            'app_key' => 'test_app_key'
        ];
        
        $pay = new MMPay($options);
        
        $this->assertInstanceOf(MMPay::class, $pay);
    }
}