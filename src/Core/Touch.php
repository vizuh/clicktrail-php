<?php

declare(strict_types=1);

namespace ClickTrail\Core;

/**
 * One observed touch. Immutable value object; JSON round-trips through
 * toArray()/fromArray() so any adapter can persist it (session, cookie, DB).
 */
final class Touch
{
    /**
     * @param array<string, string> $clickIds ad click identifiers present on this touch
     */
    public function __construct(
        public readonly ?string $source = null,
        public readonly ?string $medium = null,
        public readonly ?string $campaign = null,
        public readonly ?string $content = null,
        public readonly ?string $term = null,
        public readonly ?string $utmId = null,
        public readonly ?string $referrer = null,
        public readonly ?string $landingPage = null,
        public readonly ?string $touchTimestamp = null,
        public readonly array $clickIds = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'medium' => $this->medium,
            'campaign' => $this->campaign,
            'content' => $this->content,
            'term' => $this->term,
            'utm_id' => $this->utmId,
            'referrer' => $this->referrer,
            'landing_page' => $this->landingPage,
            'touch_timestamp' => $this->touchTimestamp,
            'click_ids' => $this->clickIds === [] ? null : $this->clickIds,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            source: $data['source'] ?? null,
            medium: $data['medium'] ?? null,
            campaign: $data['campaign'] ?? null,
            content: $data['content'] ?? null,
            term: $data['term'] ?? null,
            utmId: $data['utm_id'] ?? null,
            referrer: $data['referrer'] ?? null,
            landingPage: $data['landing_page'] ?? null,
            touchTimestamp: $data['touch_timestamp'] ?? null,
            clickIds: is_array($data['click_ids'] ?? null) ? $data['click_ids'] : [],
        );
    }
}
