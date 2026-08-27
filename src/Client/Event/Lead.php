<?php

declare(strict_types=1);

namespace ClickTrail\Client\Event;

use ClickTrail\Conventions\Stable;

/**
 * Lead submission. IMPORTANT (consent contract): hashedEmail/hashPhone may
 * only be populated when the stored snapshot grants ad_user_data - the
 * CALLER is responsible for that gate; this class does not decide.
 */
final class Lead extends AbstractEvent
{
    public function __construct(
        ?string $eventId = null,
        ?string $visitorId = null,
        protected readonly ?string $leadId = null,
        protected readonly ?string $hashedEmail = null,
        protected readonly ?string $hashedPhone = null,
        ?\DateTimeImmutable $occurredAt = null,
        array $extra = [],
    ) {
        parent::__construct(Stable::EVENT_LEAD_CREATED, $eventId, $visitorId, $occurredAt, $extra);
    }

    public function toArray(): array
    {
        $payload = parent::toArray();
        $payload['event']['object_type'] = 'lead';
        if ($this->leadId !== null) {
            $payload['event']['object_id'] = $this->leadId;
        }
        foreach (['hashed_email' => $this->hashedEmail, 'hashed_phone' => $this->hashedPhone] as $k => $v) {
            if ($v !== null) {
                $payload[$k] = $v;
            }
        }

        return $payload;
    }
}
