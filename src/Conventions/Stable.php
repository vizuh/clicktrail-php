<?php

declare(strict_types=1);

namespace ClickTrail\Conventions;

/**
 * Stable attribution conventions - PHP mirror of
 * clicktrail-js packages/clicktrail/src/conventions/stable.ts.
 *
 * Naming follows the OTel convention: ATTR_${name}, EVENT_${name}.
 * Additive changes only within a major.
 */
final class Stable
{
    public const SCHEMA_VERSION = '1.2.0';
    public const CLASSIFIER_VERSION = '1.2.0';

    /** Canonical payload source stamp for PHP adapters. */
    public const SOURCE_PHP = 'php';

    // --- first-touch attributes ------------------------------------------

    public const ATTR_FIRST_SOURCE = 'attribution.first.source';
    public const ATTR_FIRST_MEDIUM = 'attribution.first.medium';
    public const ATTR_FIRST_CAMPAIGN = 'attribution.first.campaign';
    public const ATTR_FIRST_TERM = 'attribution.first.term';
    public const ATTR_FIRST_CONTENT = 'attribution.first.content';
    public const ATTR_FIRST_UTM_ID = 'attribution.first.utm_id';
    public const ATTR_FIRST_REFERRER = 'attribution.first.referrer';
    public const ATTR_FIRST_LANDING_PAGE = 'attribution.first.landing_page';
    public const ATTR_FIRST_TOUCH_TIMESTAMP = 'attribution.first.touch_timestamp';

    // --- last-touch attributes --------------------------------------------

    public const ATTR_LAST_SOURCE = 'attribution.last.source';
    public const ATTR_LAST_MEDIUM = 'attribution.last.medium';
    public const ATTR_LAST_CAMPAIGN = 'attribution.last.campaign';
    public const ATTR_LAST_TERM = 'attribution.last.term';
    public const ATTR_LAST_CONTENT = 'attribution.last.content';
    public const ATTR_LAST_UTM_ID = 'attribution.last.utm_id';
    public const ATTR_LAST_REFERRER = 'attribution.last.referrer';
    public const ATTR_LAST_LANDING_PAGE = 'attribution.last.landing_page';
    public const ATTR_LAST_TOUCH_TIMESTAMP = 'attribution.last.touch_timestamp';

    // --- ad click IDs -------------------------------------------------------

    public const CLICK_ID_KEYS = [
        'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid',
        'ttclid', 'twclid', 'li_fat_id', 'sccid', 'epik',
    ];

    // --- events --------------------------------------------------------------

    public const EVENT_PAGE_VIEW = 'page_view';
    public const EVENT_FORM_STARTED = 'form_started';
    public const EVENT_LEAD_CREATED = 'lead_created';
    public const EVENT_LEAD_QUALIFIED = 'lead_qualified';
    public const EVENT_BOOKING_CREATED = 'booking_created';
    public const EVENT_BOOKING_COMPLETED = 'booking_completed';
    public const EVENT_SALE = 'sale';
    public const EVENT_REFUND = 'refund';
    public const EVENT_CONSENT_UPDATED = 'consent_updated';

    /** @deprecated Use EVENT_LEAD_CREATED. */
    public const EVENT_LEAD_SUBMITTED = 'lead.submitted';
    /** @deprecated Use EVENT_BOOKING_CREATED. */
    public const EVENT_APPOINTMENT_BOOKED = 'appointment.booked';
    /** @deprecated Use EVENT_BOOKING_COMPLETED. */
    public const EVENT_APPOINTMENT_ATTENDED = 'appointment.attended';
    /** @deprecated Use EVENT_SALE. */
    public const EVENT_SALE_COMPLETED = 'sale.completed';
    /** @deprecated Use EVENT_REFUND. */
    public const EVENT_SALE_REFUNDED = 'sale.refunded';

    /** Normalize pre-contract names while preserving unknown host events. */
    public static function canonicalEventName(string $eventName): string
    {
        return match ($eventName) {
            'lead', 'form.submitted', 'lead.submitted', 'lead_submitted',
            'form_submission', 'lead.attribution_attached' => self::EVENT_LEAD_CREATED,
            'form.started' => self::EVENT_FORM_STARTED,
            'lead.qualified' => self::EVENT_LEAD_QUALIFIED,
            'booking', 'appointment.booked', 'appointment.requested' => self::EVENT_BOOKING_CREATED,
            'appointment.attended', 'appointment.completed' => self::EVENT_BOOKING_COMPLETED,
            'sale.completed', 'sale.recorded', 'purchase', 'revenue.recurring',
            'offline_conversion.sent' => self::EVENT_SALE,
            'sale.refunded', 'refund.issued' => self::EVENT_REFUND,
            'consent.granted', 'consent.withdrawn',
            'consent.policy_updated' => self::EVENT_CONSENT_UPDATED,
            'lead.stage_updated' => 'lead_updated',
            'lead.merged' => 'lead_merged',
            'visitor.anonymized' => 'visitor_anonymized',
            default => $eventName,
        };
    }

    /**
     * Derive a STABLE event id from a site-scoped external key, so replays
     * (webhook redelivery, Sale/Refund retries) reuse the same id across
     * runtimes. Mirror of deriveStableEventId in clicktrail-js
     * packages/core/src/ids.ts (fixture: stable-event-id-v1.json):
     * 'evt_s-' + hex(first 16 bytes of sha256(utf8(siteId . "\0" . externalKey))).
     *
     * Inputs are hashed as raw bytes; pass UTF-8 strings to match JS.
     *
     * @throws \InvalidArgumentException when siteId or externalKey is empty
     */
    public static function deriveStableEventId(string $siteId, string $externalKey): string
    {
        if ($siteId === '' || $externalKey === '') {
            throw new \InvalidArgumentException('deriveStableEventId requires non-empty siteId and externalKey.');
        }

        return 'evt_s-' . bin2hex(substr(hash('sha256', $siteId . "\0" . $externalKey, true), 0, 16));
    }

    // --- channels & labels (fixture-driven subset; see FIXTURE-PARITY-LEDGER) ---

    public const CHANNEL_PAID_SEARCH = 'paid_search';
    public const CHANNEL_PAID_OTHER = 'paid_other';
    public const CHANNEL_ORGANIC_SEARCH = 'organic_search';
    public const CHANNEL_ORGANIC_SOCIAL = 'organic_social';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_UNKNOWN = 'unknown';

    /** sc_click_id is an alias of sccid (plugin contract). */
    public const CLICK_ID_ALIASES = ['sc_click_id' => 'sccid'];

    private function __construct()
    {
    }
}
