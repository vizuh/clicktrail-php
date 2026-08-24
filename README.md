# clicktrail-php

Deterministic attribution core + PHP SDK for ClickTrail.
Packagist: `clicktrail/php-sdk` (repo: `clicktrail-php`).

Same architecture and contract as [`clicktrail-js`](https://github.com/vizuh/clicktrail-js):

```
conventions (meaning) -> core engine (pure functions) -> adapters (frameworks & CMS marketplaces)
```

Design rules inherited from the JS engine:

1. The core is **deterministic**: same inputs -> same output. Time, IDs,
   storage, consent and network are injected by callers, never requested.
2. Every payload carries `schema_version` and `classifier_version`
   (currently `1.2.0`, byte-equal to the JS engine).
3. Classifier behavior changes are major semver, by definition.
   Golden-fixture parity against `clicktrail-js` is the release gate
   (`packages/clicktrail/fixtures/` replay, MATCH/DIFF ledger).

## What ships here (layer 1 - framework foundation)

- Attribution conventions (`ClickTrail\Conventions\Stable`)
- Deterministic touch parsing + first/last-touch merge laws (`Core`)
- Canonical payload serialization with version stamps
- Ingestion client **contracts** (interface + exception taxonomy);
  concrete transport is `# DEFERRED` until the parity gate passes

Layer-2 consumers (marketplace plugins):

| Adapter | Platform | Distribution |
|---|---|---|
| clicktrail-october | October CMS 3 | official marketplace |
| clicktrail-craft | Craft CMS 5 | Plugin Store |
| clicktrail-shopware | Shopware 6.6 | Shopware Store |
| clicktrail-drupal | Drupal 11 | drupal.org |
| clicktrail-filament | Filament 3 | community directory |

Framework packages queued: `clicktrail/psr-middleware`, `clicktrail/laravel`,
`clicktrail/symfony-bundle`.

## Quick example

```php
use ClickTrail\Core\AttributionInput;
use ClickTrail\Core\TouchParser;
use ClickTrail\Core\TouchMerger;
use ClickTrail\Core\StoredState;

$input = new AttributionInput(
    query: $_GET,
    host: 'example.com',
    landingPage: 'https://example.com/promo',
    referrer: $_SERVER['HTTP_REFERER'] ?? null,
    touchTimestamp: '2026-08-24T10:00:00.000Z', // caller owns the clock
);

$stored = StoredState::fromJson($session['clicktrail_attribution'] ?? null);
$merged = TouchParser::hasSignal($input)
    ? TouchMerger::merge($stored, TouchParser::parse($input))
    : $stored;

$session['clicktrail_attribution'] = $merged->toJson();
```

Merge laws: first touch is written once and never overwritten within a major;
last touch follows the newest non-direct signal; click IDs guard first-touch.

## License

MIT - see [LICENSE](LICENSE).
