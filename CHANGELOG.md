# 更新日志

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- 完整的企业微信支持
- 微信支付功能（JSAPI、扫码、APP支付）
- 消息加解密功能
- OAuth2.0网页授权
- JSSDK支持
- 自定义菜单管理
- 模板消息推送
- 素材管理功能
- 用户管理API
- 数据统计接口

### Changed
- 重构为Composer兼容的PSR-4标准结构
- 添加完整的命名空间支持
- 优化错误处理机制
- 改进代码注释和文档

### Fixed
- 修复Prpcrypt类引用问题
- 解决count()函数类型检查警告
- 修正文件命名规范问题
- 修复自动加载路径配置

## [1.0.0] - 2024-01-01

### Added
- 初始版本发布
- 基础微信公众号功能
- 消息接收与回复
- access_token管理
- 基础API调用封装