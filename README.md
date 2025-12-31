# WeChat SDK for PHP

[![Latest Stable Version](https://poser.pugx.org/nanxihang/wechat/v/stable)](https://packagist.org/packages/nanxihang/wechat)
[![Total Downloads](https://poser.pugx.org/nanxihang/wechat/downloads)](https://packagist.org/packages/nanxihang/wechat)
[![License](https://poser.pugx.org/nanxihang/wechat/license)](https://packagist.org/packages/nanxihang/wechat)

一个功能完整的微信SDK，支持微信公众平台、企业微信和微信支付功能。

## 功能特性

- ✅ 公众号消息接收与回复
- ✅ OAuth2.0网页授权
- ✅ JSSDK支持
- ✅ 自定义菜单管理
- ✅ 模板消息推送
- ✅ 企业微信支持
- ✅ 微信支付（JSAPI、扫码、APP支付）
- ✅ 消息加解密
- ✅ 素材管理
- ✅ 用户管理
- ✅ 数据统计

## 环境要求

- PHP >= 5.6.0
- cURL扩展
- OpenSSL扩展
- SimpleXML扩展
- JSON扩展

## 安装

使用Composer安装：

```bash
composer require nanxihang/wechat
```

## 快速开始

## 错误码说明

SDK提供了完整的错误码常量：

```php
use Nanxihang\Wechat\ErrCode;

echo ErrCode::$OK; // 0
echo ErrCode::$ERROR_SYSTEM; // 90001
```

企业微信错误码：

```php
use Nanxihang\Wechat\QyErrCode;

echo QyErrCode::$OK; // 0
echo QyErrCode::$ERROR_INVALID_APPID; // 40013
```

## 目录结构

```
src/
├── Internal/
│   ├── ErrorCode.php          # 错误码定义
│   ├── PKCS7Encoder.php       # PKCS7编码器
│   ├── Prpcrypt.php           # 加密解密类
│   └── QyErrorCode.php        # 企业微信错误码
├── errCode.php               # 公众号错误码常量
├── qyerrCode.php             # 企业微信错误码常量
└── WechatX.php              # 主SDK类
tests/
├── bootstrap.php            # 测试引导文件
├── WechatTest.php          # 主类测试
└── phpunit.xml             # PHPUnit配置
examples/
├── basic_usage.php         # 基础使用示例
├── corp_usage.php          # 企业微信示例
└── payment_usage.php       # 支付示例
```

## 版本历史

查看 [CHANGELOG.md](CHANGELOG.md) 文件了解版本更新详情。

## 许可证

MIT License