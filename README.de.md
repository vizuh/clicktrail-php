[English](README.md) | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**clicktrail/php-sdk**

Deterministische Kampagnen-Attribution für PHP — derselbe Engine-Vertrag hinter ClickTrails WordPress-, GTM- und CMS-Integrationen.

</div>

[![CI](https://github.com/vizuh/clicktrail-php/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Index

- [Warum](#warum)
- [Installation](#installation)
- [Attributionserfassung](#attributionserfassung)
- [Consent-Gating](#consent-gating)
- [Auslieferung](#auslieferung)
- [Fixture-Parität](#fixture-parität)
- [Tests](#tests)
- [Lizenz](#lizenz)

## Warum

Die meisten Tracking-Pakete speichern, was eine Seite angezeigt hat. ClickTrail beweist, welche Kampagne den Lead oder Verkauf erzeugt hat: deterministische First-Touch-/Last-Touch-Merge-Regeln, Feld für Feld gegen dieselben Golden Fixtures validiert, die auch unser WordPress-Plugin und unsere GTM-Templates steuern.

## Installation

```bash
composer require clicktrail/php-sdk
```

## Attributionserfassung

```php
use ClickTrail\Core\{AttributionInput, StoredState, TouchMerger};

// Eine Paid-Search-Landingpage...
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
// first->source === 'google', first->clickIds['gclid'] gesetzt,
// und genau dieses Ergebnis läuft in WordPress, GTM und Shopware identisch nach.
```

Ein direkter Besuch danach ändert nichts — der First Touch bleibt, der gespeicherte Last Touch besteht fort. Das ist die Merge-Regel: getestet, nicht versprochen.

## Consent-Gating

```php
use ClickTrail\Consent\{ConsentBehavior, ConsentSnapshot};

if (! ConsentBehavior::can($snapshot, 'ad_user_data')) {
    // Enhanced Conversion unterdrückt; Grund protokolliert:
    // "adUserData was unknown at lead capture (source: cookieyes)"
}
```

Unbekanntes Consent wird standardmäßig verweigert. Snapshots reisen mit dem Lead, sodass der Conversion-Worker Monate später genau weiß, welche Berechtigungen bei der Erfassung galten.

## Auslieferung

```php
$client->track(new Sale(eventId: 'order-8241-paid', orderId: '8241', value: 199.0, currency: 'EUR'));
$client->flush(); // gebatchter POST, Idempotency-Keys, Retries mit Backoff
```

Fehlgeschlagene Batches werden von der Persistenzschicht Ihres Adapters über `pending()` / `restore()` aufgefangen und nach der Diagnose erneut zugestellt.

## Fixture-Parität

`bin/replay-fixtures.php` spielt die Golden Fixtures von clicktrail-js ab: aktuell **12/12 Fixtures, 57/57 Felder MATCH**. Ledger: [docs/FIXTURE-PARITY-LEDGER.md](docs/FIXTURE-PARITY-LEDGER.md). Änderungen am Klassifikator sind per Definition MAJOR-Releases.

## Tests

```bash
vendor/bin/phpunit --testdox          # komplette Suite
php bin/replay-fixtures.php <dir>     # Paritäts-Ledger
```

## Lizenz

MIT.
