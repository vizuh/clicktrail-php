<?php

declare(strict_types=1);

namespace ClickTrail\Core;

use ClickTrail\Conventions\Stable;

/**
 * Deterministic touch parser. Mirrors the GTM variable / JS engine rules:
 * a touch is an attribution signal when it carries UTM parameters, an ad
 * click ID, or an external referrer. Channel classification intentionally
 * lives engine-side (classifier_version) and is not reimplemented here.
 */
final class TouchParser
{
    private const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id'];

    public static function hasSignal(AttributionInput $input): bool
    {
        foreach (self::UTM_KEYS as $key) {
            if (self::nonEmpty($input->query[$key] ?? null)) {
                return true;
            }
        }
        foreach (Stable::CLICK_ID_KEYS as $key) {
            if (self::nonEmpty($input->query[$key] ?? null)) {
                return true;
            }
        }
        return $input->referrer !== null
            && $input->referrer !== ''
            && !self::isInternalReferrer($input);
    }

    public static function parse(AttributionInput $input): Touch
    {
        $pick = static function (string $key) use ($input): ?string {
            $v = $input->query[$key] ?? null;
            return is_scalar($v) && (string) $v !== '' ? (string) $v : null;
        };

        $clickIds = [];
        foreach (Stable::CLICK_ID_KEYS as $key) {
            $v = $pick($key);
            if ($v !== null) {
                $clickIds[$key] = $v;
            }
        }

        return new Touch(
            source: $pick('utm_source'),
            medium: $pick('utm_medium'),
            campaign: $pick('utm_campaign'),
            content: $pick('utm_content'),
            term: $pick('utm_term'),
            utmId: $pick('utm_id'),
            referrer: $input->referrer,
            landingPage: $input->landingPage,
            touchTimestamp: $input->touchTimestamp,
            clickIds: $clickIds,
        );
    }

    private static function nonEmpty(mixed $value): bool
    {
        return is_scalar($value) && (string) $value !== '';
    }

    private static function isInternalReferrer(AttributionInput $input): bool
    {
        return $input->referrer === null
            || $input->host === ''
            || str_contains($input->referrer, $input->host);
    }
}
