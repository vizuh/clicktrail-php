<?php

declare(strict_types=1);

namespace ClickTrail\Client\Event;

use ClickTrail\Conventions\Stable;

final class Sale extends AbstractEvent
{
    public function __construct(
        ?string $eventId = null,
        ?string $visitorId = null,
        protected readonly ?string $orderId = null,
        protected readonly ?float $value = null,
        protected readonly ?string $currency = null,
        ?\DateTimeImmutable $occurredAt = null,
        array $extra = [],
    ) {
        parent::__construct(Stable::EVENT_SALE, $eventId, $visitorId, $occurredAt, $extra);
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['event']['object_type'] = 'order';
        if ($this->orderId !== null) {
            $payload['event']['object_id'] = $this->orderId;
        }
        if ($this->value !== null) {
            $payload['event']['value'] = $this->value;
        }
        if ($this->currency !== null) {
            $payload['event']['currency'] = $this->currency;
        }

        return $payload;
    }
}
