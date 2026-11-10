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

ClickTrail is the open-source attribution handoff layer that keeps observed acquisition context attached to conversion records inside the stack you own. This PHP package carries the same deterministic first-touch / last-touch contract into PHP applications, validated field-by-field against the golden fixtures shared with the WordPress plugin and GTM templates.

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

When a retry or webhook redelivery must dedupe across runtimes, derive the event id from a site-scoped key instead of inventing one: `Stable::deriveStableEventId('site-1', 'order:8241:paid')` returns the same `evt_s-…` id as `deriveStableEventId` in `@vizuh/clicktrail-core` (shared vectors in `tests/fixtures/stable-event-id-v1.json`). Pass it as the event's `eventId`; events without one still get a random idempotency key.

## Fixture parity

`bin/replay-fixtures.php` replays the clicktrail-js golden fixtures: currently **12/12 fixtures, 57/57 fields MATCH**. Ledger: [docs/FIXTURE-PARITY-LEDGER.md](docs/FIXTURE-PARITY-LEDGER.md). Classifier changes are MAJOR releases, by definition.

## Testing

```bash
vendor/bin/phpunit --testdox          # full suite
php bin/replay-fixtures.php <dir>     # parity ledger
```

## License

MIT.
