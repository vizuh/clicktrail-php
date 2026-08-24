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
 * 3. Direct touches keep stored state untouched; with nothing stored they
 *    become the baseline (both first and last).
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
     */
    public static function observe(StoredState $stored, AttributionInput $input): StoredState
    {
        if (!TouchParser::hasSignal($input)) {
            if ($stored->last === null) {
                $direct = TouchParser::parse($input);
                return new StoredState(first: $direct, last: $direct);
            }

            return $stored;
        }

        return self::merge($stored, TouchParser::parse($input));
    }
}
