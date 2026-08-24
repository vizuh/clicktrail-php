<?php

declare(strict_types=1);

namespace ClickTrail\Core;

use ClickTrail\Conventions\Stable;

/**
 * Deterministic channel/platform classification - PHP implementation of the
 * golden-fixture contract from clicktrail-js. Pure functions only.
 *
 * Parity status is tracked in docs/FIXTURE-PARITY-LEDGER.md; rules here are
 * fixture-driven (fixtures are the executable spec).
 */
final class Classifier
{
    /** Click ID => [source, medium, channel, label]. */
    private const CLICK_ID_PLATFORMS = [
        'gclid'     => ['google', 'cpc', Stable::CHANNEL_PAID_SEARCH, 'Google Ads'],
        'wbraid'    => ['google', '', Stable::CHANNEL_UNKNOWN, 'Google'],
        'gbraid'    => ['google', '', Stable::CHANNEL_UNKNOWN, 'Google'],
        'fbclid'    => ['facebook', '', Stable::CHANNEL_UNKNOWN, 'Facebook'],
        'msclkid'   => ['bing', 'cpc', Stable::CHANNEL_PAID_SEARCH, 'Microsoft Ads'],
        'ttclid'    => ['tiktok', '', Stable::CHANNEL_UNKNOWN, 'TikTok'],
        'twclid'    => ['twitter', '', Stable::CHANNEL_UNKNOWN, 'Twitter'],
        'li_fat_id' => ['linkedin', '', Stable::CHANNEL_UNKNOWN, 'LinkedIn'],
        'sccid'     => ['snapchat', '', Stable::CHANNEL_UNKNOWN, 'Snapchat'],
        'epik'      => ['', '', Stable::CHANNEL_UNKNOWN, 'Pinterest'],
    ];

    /** Referrer host providers. Matched as dot-segments: [medium, channel, label-suffix]. */
    private const SEARCH_PROVIDERS = [
        'google'      => ['organic', Stable::CHANNEL_ORGANIC_SEARCH, 'Google Organic'],
        'bing'        => ['organic', Stable::CHANNEL_ORGANIC_SEARCH, 'Bing Organic'],
        'yahoo'       => ['organic', Stable::CHANNEL_ORGANIC_SEARCH, ''],
        'duckduckgo'  => ['organic', Stable::CHANNEL_ORGANIC_SEARCH, 'DuckDuckGo Organic'],
    ];

    private const SOCIAL_PROVIDERS = [
        'instagram' => ['social', Stable::CHANNEL_ORGANIC_SOCIAL, 'Instagram Organic'],
        'facebook'  => ['social', Stable::CHANNEL_ORGANIC_SOCIAL, 'Facebook Organic'],
        'twitter'   => ['social', Stable::CHANNEL_ORGANIC_SOCIAL, 'Twitter Organic'],
        'x'         => ['social', Stable::CHANNEL_ORGANIC_SOCIAL, 'X Organic'],
        'linkedin'  => ['social', Stable::CHANNEL_ORGANIC_SOCIAL, 'LinkedIn Organic'],
        'tiktok'    => ['social', Stable::CHANNEL_ORGANIC_SOCIAL, 'TikTok Organic'],
        't.co'      => ['social', Stable::CHANNEL_ORGANIC_SOCIAL, 'Twitter Organic'],
    ];

    /**
     * Internal when either host equals or is a subdomain of the other.
     */
    public static function isInternalReferrer(string $referrerHost, string $currentHost): bool
    {
        if ($referrerHost === '' || $currentHost === '') {
            return true;
        }
        return self::sameOrSubdomain($referrerHost, $currentHost)
            || self::sameOrSubdomain($currentHost, $referrerHost);
    }

    private static function sameOrSubdomain(string $host, string $parent): bool
    {
        $host = strtolower(trim($host, '.'));
        $parent = strtolower(trim($parent, '.'));
        if ($host === '' || $parent === '') {
            return false;
        }

        return $host === $parent || str_ends_with($host, '.' . $parent);
    }

    /**
     * Classify a referrer URL into [source, medium, channel, label] or null.
     * @return array{string,string,string,string}|null
     */
    public static function classifyReferrer(string $referrer): ?array
    {
        $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }
        // strip leading www.
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        $segments = explode('.', $host);

        foreach ([self::SEARCH_PROVIDERS, self::SOCIAL_PROVIDERS] as $i => $table) {
            foreach ($table as $provider => $spec) {
                foreach ($segments as $seg) {
                    if ($seg === $provider) {
                        $label = $spec[2];
                        if ($label === '') { // yahoo-style: provider name + context
                            $label = ucfirst($provider);
                        }
                        if ($i === 0 && $label !== '' && !str_contains($label, 'Organic') && !str_contains($label, 'Ads')) {
                            $label = $label; // explicit label already set by table
                        }
                        return [$provider, $spec[0], $spec[1], $label];
                    }
                }
            }
        }

        return ['referral', 'referral', 'referral', ''];
    }

    /**
     * Infer source/medium/channel/label from the FIRST recognized click id.
     * @param array<string, string> $clickIds
     * @return array{string,string,string,string}|null
     */
    public static function classifyClickId(array $clickIds): ?array
    {
        foreach (self::CLICK_ID_PLATFORMS as $key => $spec) {
            if (isset($clickIds[$key]) && $clickIds[$key] !== '') {
                return $spec;
            }
        }

        return null;
    }

    /**
     * Channel + label for an explicit UTM touch.
     * Canonical matching is case-SENSITIVE for the enum; labels are
     * case-INSENSITIVE (fixture: utm-key-case-insensitive -> paid_other /
     * "Facebook Ads" for source fAcEbOoK).
     * Contract (fixtures google-ads vs template-macro): the paid_search ENUM
     * requires a recognized ad click id; UTM-medium-derived paid traffic
     * without one classifies as paid_other while keeping its source label.
     * @param array<string,string> $clickIds
     * @return array{string, string} [channel, label]
     */
    public static function classifyUtm(?string $source, ?string $medium, array $clickIds = []): array
    {
        $srcLower = strtolower((string) $source);
        $med = strtolower((string) $medium);

        if (in_array($med, ['cpc', 'ppc', 'paidsearch', 'paid-search'], true)) {
            $hasClickId = $clickIds !== [];
            $channel = $hasClickId ? Stable::CHANNEL_PAID_SEARCH : Stable::CHANNEL_PAID_OTHER;
            $label = match ($srcLower) {
                'google' => 'Google Ads',
                'bing' => 'Microsoft Ads',
                default => ucfirst($srcLower !== '' ? $srcLower : 'paid') . ' Ads',
            };

            return [$channel, $label];
        }
        if (in_array($med, ['paid_social', 'paidsocial'], true)) {
            $label = $srcLower === '' ? 'Paid Social'
                : match ($srcLower) {
                    'facebook', 'fb' => 'Facebook Ads',
                    'instagram' => 'Instagram Ads',
                    'linkedin' => 'LinkedIn Ads',
                    'tiktok' => 'TikTok Ads',
                    default => ucfirst($srcLower) . ' Ads',
                };
            // canonical-source check is case-sensitive: unknown casing falls to paid_other
            $canonical = ['facebook', 'instagram', 'linkedin', 'tiktok', 'twitter', 'snapchat'];
            $channel = in_array((string) $source, $canonical, true)
                ? Stable::CHANNEL_ORGANIC_SOCIAL // canonical social source with paid medium stays engine-defined
                : Stable::CHANNEL_PAID_OTHER;

            return [$channel, $label];
        }
        if ($med === 'email') {
            return [Stable::CHANNEL_EMAIL, ucfirst($srcLower !== '' ? $srcLower : 'email')];
        }
        if (in_array($med, ['organic', 'seo'], true)) {
            return [Stable::CHANNEL_ORGANIC_SEARCH, ucfirst($srcLower)];
        }
        if (in_array($med, ['social', 'social-paid', 'smo'], true)) {
            return [Stable::CHANNEL_ORGANIC_SOCIAL, ucfirst($srcLower)];
        }
        if ($srcLower === 'referral' || $med === 'referral') {
            return ['referral', 'Referral'];
        }

        return [Stable::CHANNEL_UNKNOWN, ''];
    }

    /** Values matching the {{...}} macro pattern never create fields. */
    public static function isTemplateMacro(string $value): bool
    {
        return preg_match('/^\{\{[^{}]*\}\}$/', $value) === 1;
    }
}
