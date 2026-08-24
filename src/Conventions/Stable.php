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
    public const EVENT_LEAD_SUBMITTED = 'lead.submitted';
    public const EVENT_APPOINTMENT_BOOKED = 'appointment.booked';
    public const EVENT_APPOINTMENT_ATTENDED = 'appointment.attended';
    public const EVENT_SALE_COMPLETED = 'sale.completed';
    public const EVENT_SALE_REFUNDED = 'sale.refunded';

    private function __construct()
    {
    }
}
