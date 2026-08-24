<?php

declare(strict_types=1);

namespace ClickTrail\Core;

use ClickTrail\Conventions\Stable;

/**
 * Test-support shim: derives [enum, ftLabel, ltLabel] for fixture ledger
 * comparison using the same rules TouchParser used to build the touch.
 * Not part of the public adapter API.
 */
final class FixtureChannelProbe
{
    /**
     * @return array{0:string,1:?string,2:?string}|null
     */
    public static function probe(AttributionInput $input, Touch $last): ?array
    {
        if ($last->source === null && $last->medium === null && $last->clickIds === []) {
            return null;
        }

        $q = TouchParser::normalizeQuery($input->query);
        $utmSource = isset($q['utm_source']) && !Classifier::isTemplateMacro($q['utm_source']) ? $q['utm_source'] : null;
        $utmMedium = isset($q['utm_medium']) && !Classifier::isTemplateMacro($q['utm_medium']) ? $q['utm_medium'] : null;

        if ($utmSource !== null || $utmMedium !== null) {
            [$channel, $label] = Classifier::classifyUtm($utmSource, $utmMedium, $last->clickIds);
        } elseif ($last->clickIds !== []) {
            $inferred = Classifier::classifyClickId($last->clickIds);
            [$channel, $label] = $inferred !== null ? [$inferred[2], $inferred[3]] : [Stable::CHANNEL_UNKNOWN, null];
        } else {
            $refClass = Classifier::classifyReferrer((string) $input->referrer);
            if ($refClass === null) {
                return [Stable::CHANNEL_UNKNOWN, null, null];
            }
            [$source, , $channel, $label] = $refClass;
        }

        return [$channel, $label, $label];
    }
}
