<?php

declare(strict_types=1);

namespace ClickTrail\Client;

/**
 * Ingestion client contract (Hugo spec: identify / track / conversion /
 * refund / consent, batch ingestion, retries with backoff, idempotency keys,
 * PSR-3 + PSR-18 integration, safe serialization).
 *
 * # DEFERRED - concrete transport (reason: P0 golden-fixture parity gate
 * against clicktrail-js must pass before wire format freezes; adapters may
 * ship their own transport until then).
 */
interface ClientInterface
{
    /** Fire-and-forget style event submission with generated idempotency key. */
    public function track(array $event): void;

    /** Attach identity to a visitor (hashed/first-party identifiers only). */
    public function identify(string $visitorId, array $traits = []): void;

    /** Conversion outcome bound to an original event or visitor. */
    public function conversion(string $eventId, array $outcome): void;

    /** Refund/cancellation feedback for commerce platforms. */
    public function refund(string $orderId, array $details = []): void;

    /** Record consent state transitions for compliance auditing. */
    public function consent(string $visitorId, string $state, string $policyVersion): void;
}
