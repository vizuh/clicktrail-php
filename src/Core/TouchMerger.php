<?php

declare(strict_types=1);

namespace ClickTrail\Core;

/**
 * First-touch / last-touch merge laws (mirror of the JS engine):
 *
 * 1. A signal touch always becomes the new last touch.
 * 2. First touch is written once and never overwritten within a major.
 *    The click-ID-aware guard from the core merge law applies: a stored
 *    first touch with a click ID is never replaced by a later organic touch.
 * 3. Signal-less landings never create touches; they leave stored state
 *    untouched (fixture law: no synthetic direct baseline).
 */
final class TouchMerger
{
    public static function merge(StoredState $stored, Touch $signal): StoredState
    {
        $first = $stored->first ?? $signal;
        return new StoredState(first: $first, last: $signal);
    }

    /**
     * Convenience for adapters: parse + conditional merge in one call.
     * Fixture law: a signal-less landing with nothing stored creates NO touch
     * at all (no synthetic direct baseline); internal referrers report
     * internal_referrer.
     */
    public static function observe(StoredState $stored, AttributionInput $input): StoredState
    {
        if (!TouchParser::hasSignal($input)) {
            return $stored;
        }

        return self::merge($stored, TouchParser::parse($input));
    }
}
