<?php

declare(strict_types=1);

namespace ClickTrail\Client\Event;

/**
 * A single ingested event. Adapters construct typed events; the transport
 * serializes them into the canonical ClickTrail envelope.
 */
interface EventInterface
{
    /** Canonical event name (Stable::EVENT_* or platform-mapped name). */
    public function name(): string;

    /** Optional caller-supplied idempotency key; generated when null. */
    public function eventId(): ?string;

    /** First-party visitor identifier, when permitted by consent. */
    public function visitorId(): ?string;

    /**
     * Full payload including schema stamp. Must contain only safe,
     * consent-cleared data (no raw PII unless ad_user_data is granted).
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
