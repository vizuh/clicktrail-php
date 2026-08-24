<?php

declare(strict_types=1);

namespace ClickTrail\Core;

use ClickTrail\Conventions\Stable;

/**
 * Deterministic touch parser. Contract rules (golden fixtures):
 *  - query keys lowercased before lookup; values verbatim;
 *  - duplicated parameters: last occurrence wins;
 *  - values matching the {{...}} macro pattern never create fields;
 *  - sc_click_id is an alias of sccid;
 *  - a touch is a signal when it has UTMs, an ad click ID, or an external
 *    referrer; internal = either host equals or is subdomain of the other;
 *  - click IDs imply their platform when UTMs are absent
 *    (gclid additionally implies medium=cpc).
 *
 * Channel classification lives in Classifier (engine parity via fixtures);
 * labels/enums are stamped by the engine, not invented here.
 */
final class TouchParser
{
    private const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id'];

    /**
     * Normalized query: lowercase keys, last occurrence wins, macros dropped.
     * @param array<string, mixed> $query
     * @return array<string, string>
     */
    public static function normalizeQuery(array $query): array
    {
        $out = [];
        foreach ($query as $k => $v) {
            if (!is_scalar($v)) {
                continue;
            }
            $key = strtolower((string) $k);
            $val = (string) $v;
            if ($val === '') {
                continue;
            }
            $out[$key] = $val; // last wins
        }
        foreach (Stable::CLICK_ID_ALIASES as $alias => $canonical) {
            if (isset($out[$alias])) {
                $out[$canonical] ??= $out[$alias];
            }
        }

        return $out;
    }

    /** @param array<string, string> $normalized */
    private static function pickValid(array $normalized, string $key): ?string
    {
        $v = $normalized[$key] ?? null;
        if ($v === null || Classifier::isTemplateMacro($v)) {
            return null;
        }

        return $v;
    }

    public static function hasSignal(AttributionInput $input): bool
    {
        $q = self::normalizeQuery($input->query);
        foreach ([...self::UTM_KEYS, ...Stable::CLICK_ID_KEYS] as $key) {
            if (self::pickValid($q, $key) !== null) {
                return true;
            }
        }
        if ($input->referrer !== null && $input->referrer !== '') {
            $refHost = strtolower((string) parse_url($input->referrer, PHP_URL_HOST));
            if (!Classifier::isInternalReferrer($refHost, $input->host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reason there is no touch for this input, or null when a touch exists.
     */
    public static function noTouchReason(AttributionInput $input): ?string
    {
        if (self::hasSignal($input)) {
            return null;
        }
        if ($input->referrer !== null && $input->referrer !== '') {
            return 'internal_referrer';
        }

        return 'direct_no_state';
    }

    public static function parse(AttributionInput $input): Touch
    {
        $q = self::normalizeQuery($input->query);

        $pickUtm = fn(string $k): ?string => self::pickValid($q, $k);

        $clickIds = [];
        foreach (Stable::CLICK_ID_KEYS as $key) {
            $v = self::pickValid($q, $key);
            if ($v !== null) {
                $clickIds[$key] = $v;
            }
        }

        $source = $pickUtm('utm_source');
        $medium = $pickUtm('utm_medium');

        if ($source === null && $clickIds !== []) {
            $inferred = Classifier::classifyClickId($clickIds);
            if ($inferred !== null) {
                [$inferredSource, $inferredMedium] = $inferred;
                // gclid carries medium=cpc; other platforms leave medium empty (fixture contract)
                $source = $inferredSource !== '' ? $inferredSource : null;
                $medium = $inferredMedium !== '' ? $inferredMedium : null;
            }
        }

        if ($source === null && $medium === null && $clickIds === []) {
            $refClass = null;
            if ($input->referrer !== null && $input->referrer !== ''
                && !Classifier::isInternalReferrer(
                    strtolower((string) parse_url($input->referrer, PHP_URL_HOST)),
                    $input->host,
                )) {
                $refClass = Classifier::classifyReferrer($input->referrer);
            }
            if ($refClass !== null) {
                [$source, $medium] = $refClass;
            }
        }

        return new Touch(
            source: $source,
            medium: $medium,
            campaign: $pickUtm('utm_campaign'),
            content: $pickUtm('utm_content'),
            term: $pickUtm('utm_term'),
            utmId: $pickUtm('utm_id'),
            referrer: $input->referrer ?: null,
            landingPage: $input->landingPage,
            touchTimestamp: $input->touchTimestamp,
            clickIds: $clickIds,
        );
    }
}
