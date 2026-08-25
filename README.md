[English](README.md) | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**clicktrail/php-sdk**

Deterministic campaign attribution for PHP — the same engine contract behind ClickTrail's WordPress, GTM and CMS integrations.

</div>

[![CI](https://github.com/vizuh/clicktrail-php/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Index

- [Why](#why)
- [Installation](#installation)
- [Attribution capture](#attribution-capture)
- [Consent gating](#consent-gating)
- [Delivery](#delivery)
- [Fixture parity](#fixture-parity)
- [Testing](#testing)
- [License](#license)

## Why

Most tracking packages store what a page showed. ClickTrail proves which campaign created the lead or sale: deterministic first-touch / last-touch merge laws, validated field-by-field against the same golden fixtures that govern our WordPress plugin and GTM templates.

## Installation

```bash
composer require clicktrail/php-sdk
```

## Attribution capture

```php
use ClickTrail\Core\{AttributionInput, StoredState, TouchMerger};

// A paid-search landing...
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
// first->source === 'google', first->clickIds['gclid'] set,
// and this exact result replays identically in WordPress, GTM and Shopware.
```

A direct visit afterwards changes nothing — first touch stays, stored last touch persists. That is the merge law, tested, not promised.

## Consent gating

```php
use ClickTrail\Consent\{ConsentBehavior, ConsentSnapshot};

if (! ConsentBehavior::can($snapshot, 'ad_user_data')) {
    // enhanced conversion suppressed; reason recorded:
    // "adUserData was unknown at lead capture (source: cookieyes)"
}
```

Unknown consent is denied by default. Snapshots travel with the lead, so months later the conversion worker knows exactly which permissions existed at capture.

## Delivery

```php
$client->track(new Sale(eventId: 'order-8241-paid', orderId: '8241', value: 199.0, currency: 'EUR'));
$client->flush(); // batched POST, idempotency keys, backoff retries
```

Failed batches are captured by your adapter's persistence layer via `pending()` / `restore()` and replayed after diagnosis.

## Fixture parity

`bin/replay-fixtures.php` replays the clicktrail-js golden fixtures: currently **12/12 fixtures, 57/57 fields MATCH**. Ledger: [docs/FIXTURE-PARITY-LEDGER.md](docs/FIXTURE-PARITY-LEDGER.md). Classifier changes are MAJOR releases, by definition.

## Testing

```bash
vendor/bin/phpunit --testdox          # full suite
php bin/replay-fixtures.php <dir>     # parity ledger
```

## License

MIT.
