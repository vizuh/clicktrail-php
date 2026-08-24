<?php

declare(strict_types=1);

namespace ClickTrail\Core;

/**
 * Everything the parser needs to classify one page touch. All effects are
 * injected: the caller reads query params, referrer and clock from the host
 * platform. Core never touches superglobals or the request directly.
 */
final class AttributionInput
{
    /**
     * @param array<string, mixed> $query         URL query params of this touch (e.g. $_GET)
     * @param string               $host          Current host name, e.g. example.com
     * @param string               $landingPage   Full URL of this touch's landing page
     * @param string|null          $referrer      Document referrer, if the platform supplies one
     * @param string|null          $touchTimestamp Millisecond ISO-8601 timestamp; caller owns the clock
     */
    public function __construct(
        public readonly array $query,
        public readonly string $host,
        public readonly string $landingPage,
        public readonly ?string $referrer = null,
        public readonly ?string $touchTimestamp = null,
    ) {
    }
}
