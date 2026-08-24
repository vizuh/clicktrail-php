<?php

declare(strict_types=1);

namespace ClickTrail\Core;

use ClickTrail\Conventions\Stable;

/**
 * Builds the canonical ClickTrail payload for server-side events
 * (schema_version-stamped, flat event envelope).
 */
final class PayloadSerializer
{
    /**
     * @param array<string, mixed> $event   name plus optional id/object_type/object_id/value/currency
     * @param array<string, mixed> $extra   optional attribution object, page context, custom properties
     * @return array<string, mixed>
     */
    public function serialize(
        string $siteId,
        array $event,
        StoredState $attribution,
        array $extra = [],
        string $source = Stable::SOURCE_PHP,
    ): array {
        $payload = [
            'schema_version' => Stable::SCHEMA_VERSION,
            'source' => $source,
            'site_id' => $siteId,
            'event' => $event,
        ];

        if ($attribution->first !== null || $attribution->last !== null) {
            $payload['attribution'] = [
                'first' => $attribution->first?->toArray(),
                'last' => $attribution->last?->toArray(),
            ];
        }

        foreach ($extra as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
