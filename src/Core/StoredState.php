<?php

declare(strict_types=1);

namespace ClickTrail\Core;

/**
 * First-touch / last-touch pair as persisted by an adapter. JSON in, JSON out;
 * the storage medium (session, cookie, database) belongs to the adapter.
 */
final class StoredState
{
    public function __construct(
        public readonly ?Touch $first = null,
        public readonly ?Touch $last = null,
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public static function fromJson(?string $json): self
    {
        if ($json === null || $json === '') {
            return self::empty();
        }
        try {
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::empty(); // corrupted state degrades to fresh
        }
        if (!is_array($data)) {
            return self::empty();
        }

        return new self(
            first: isset($data['first']) && is_array($data['first']) ? Touch::fromArray($data['first']) : null,
            last: isset($data['last']) && is_array($data['last']) ? Touch::fromArray($data['last']) : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'first' => $this->first?->toArray(),
            'last' => $this->last?->toArray(),
        ];
    }
}
