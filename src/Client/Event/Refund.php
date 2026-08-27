<?php

declare(strict_types=1);

namespace ClickTrail\Client\Event;

use ClickTrail\Conventions\Stable;

final class Refund extends AbstractEvent
{
    public function __construct(
        protected readonly string $orderId,
        ?string $eventId = null,
        ?string $visitorId = null,
        protected readonly ?float $value = null,
        protected readonly ?string $currency = null,
        ?\DateTimeImmutable $occurredAt = null,
        array $extra = [],
    ) {
        parent::__construct(Stable::EVENT_REFUND, $eventId, $visitorId, $occurredAt, $extra);
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['event']['object_type'] = 'order';
        $payload['event']['object_id'] = $this->orderId;
        if ($this->value !== null) {
            $payload['event']['value'] = $this->value;
        }
        if ($this->currency !== null) {
            $payload['event']['currency'] = $this->currency;
        }

        return $payload;
    }
}
