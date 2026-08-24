<?php

declare(strict_types=1);

namespace ClickTrail\Consent;

/**
 * Behavior matrix: maps capabilities to their required signal and evaluates
 * them against a snapshot. Unknown is denied by default; suppressed reasons
 * feed the audit trail ("conversion not sent: ad_user_data unknown at lead
 * capture").
 */
final class ConsentBehavior
{
    private const REQUIREMENTS = [
        ConsentSnapshot::CAP_ANALYTICS => 'analyticsStorage',
        ConsentSnapshot::CAP_ADVERTISING_STORAGE => 'advertisingStorage',
        ConsentSnapshot::CAP_AD_USER_DATA => 'adUserData',
        ConsentSnapshot::CAP_AD_PERSONALIZATION => 'adPersonalization',
    ];

    public static function can(ConsentSnapshot $snapshot, string $capability): bool
    {
        if (!isset(self::REQUIREMENTS[$capability])) {
            throw new \InvalidArgumentException("Unknown capability: {$capability}");
        }
        $signal = self::REQUIREMENTS[$capability];

        return $snapshot->{$signal}->isGranted();
    }

    /** Audit-trail reason when an upload must be suppressed. */
    public static function suppressionReason(ConsentSnapshot $snapshot, string $capability): ?string
    {
        if (self::can($snapshot, $capability)) {
            return null;
        }
        $signal = self::REQUIREMENTS[$capability];

        return sprintf('%s was %s at lead capture (source: %s)', $signal, $snapshot->{$signal}->value, $snapshot->source);
    }
}
