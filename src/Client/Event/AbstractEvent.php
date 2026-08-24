<?php

declare(strict_types=1);

namespace ClickTrail\Client\Event;

use ClickTrail\Conventions\Stable;

abstract class AbstractEvent implements EventInterface
{
    public function __construct(
        protected readonly string $name,
        protected readonly ?string $eventId = null,
        protected readonly ?string $visitorId = null,
        protected readonly ?\DateTimeImmutable $occurredAt = null,
        protected readonly array $extra = [],
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function eventId(): ?string
    {
        return $this->eventId;
    }

    public function visitorId(): ?string
    {
        return $this->visitorId;
    }

    public function toArray(): array
    {
        $payload = [
            'event' => ['name' => $this->name],
            'schema_version' => Stable::SCHEMA_VERSION,
            'source' => Stable::SOURCE_PHP,
        ];
        if ($this->visitorId !== null) {
            $payload['visitor_id'] = $this->visitorId;
        }
        if ($this->occurredAt !== null) {
            $payload['event']['occurred_at'] = $this->occurredAt->format('Y-m-d\TH:i:s.v\Z');
        }
        foreach ($this->extra as $k => $v) {
            if ($v !== null) {
                $payload[$k] = $v;
            }
        }

        return $payload;
    }
}
