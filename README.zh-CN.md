[English](README.md) | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**clicktrail/php-sdk**

面向 PHP 的确定性获客上下文引擎。它解析请求中观测到的数据，应用首次触点和末次触点规则，并构建规范事件。

</div>

[![CI](https://github.com/vizuh/clicktrail-php/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## 目录

- [为什么](#为什么)
- [安装](#安装)
- [归因采集](#归因采集)
- [同意控制](#同意控制)
- [事件投递](#事件投递)
- [Fixture 一致性](#fixture-一致性)
- [测试](#测试)
- [许可证](#许可证)

## 为什么

ClickTrail 保留请求中观测到的营销活动上下文，但不证明是哪次营销活动导致了线索或成交。此 SDK 集中实现确定性的首次触点和末次触点规则，并使用与 WordPress 和 GTM 集成相同的黄金 fixtures 进行验证。

## 安装

```bash
composer require clicktrail/php-sdk
```

## 归因采集

```php
use ClickTrail\Core\{AttributionInput, StoredState, TouchMerger};

// 一次付费搜索落地……
$merged = TouchMerger::observe(
    StoredState::fromJson($session['ct'] ?? null),
    new AttributionInput(
        query: $_GET,
        host: 'example.com',
        landingPage: 'https://example.com/promo',
        touchTimestamp: '2026-08-24T10:00:00.000Z',
    ),
);

$session['ct'] = $merged->toJson();
// first->source === 'google'，first->clickIds['gclid'] 已写入，
// 且这一结果在 WordPress、GTM 和 Shopware 中重放完全一致。
```

之后再来的直接访问不会改变任何东西；首触保留，已存储的末触持续存在。这就是合并规则：经过测试，而非口头承诺。

## 同意控制

```php
use ClickTrail\Consent\{ConsentBehavior, ConsentSnapshot};

if (! ConsentBehavior::can($snapshot, 'ad_user_data')) {
    // enhanced conversion 已抑制；原因已记录：
    // "adUserData was unknown at lead capture (source: cookieyes)"
}
```

未知的同意状态默认被拒绝。快照随线索一同保存，因此数月之后，转化任务仍能确切知道采集时存在哪些权限。

## 事件投递

```php
$client->track(new Sale(eventId: 'order-8241-paid', orderId: '8241', value: 199.0, currency: 'EUR'));
$client->flush(); // 批量 POST，幂等键，带退避的重试
```

失败的批次由适配器的持久层通过 `pending()` / `restore()` 捕获，诊断完成后重新投递。

## Fixture 一致性

`bin/replay-fixtures.php` 会重放 clicktrail-js 的黄金 fixtures：当前为 **12/12 个 fixture，57/57 个字段 MATCH**。一致性账本：[docs/FIXTURE-PARITY-LEDGER.md](docs/FIXTURE-PARITY-LEDGER.md)。分类器变更按定义属于 MAJOR 版本发布。

## 测试

```bash
vendor/bin/phpunit --testdox          # 完整测试套件
php bin/replay-fixtures.php <dir>     # 一致性账本
```

## 许可证

MIT。
