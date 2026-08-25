<?php

declare(strict_types=1);

namespace ClickTrail\Client;

use ClickTrail\Client\Event\EventInterface;
use ClickTrail\Client\Exception\PermanentException;
use ClickTrail\Client\Exception\RetryableException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PSR-18 batch transport. Implements nothing external by design; see
 * ClientInterface for the platform-facing contract this satisfies structurally. The P0 golden-fixture gate has passed, so the
 * envelope (schema_version 1.2.0) is frozen for this major.
 *
 * Behavior:
 *  - events queue client-side; flush() posts JSON batches to the endpoint
 *    (WordPress contract: POST {endpoint}/wp-json/clicutcl/v2/events/batch);
 *  - idempotency key per event: caller event id or generated random key,
 *    sent as `idempotency_key` inside each envelope;
 *  - 429/5xx/network -> exponential backoff retries (jittered), then
 *    RetryableException; other 4xx -> PermanentException immediately;
 *  - no telemetry leaves the process unless flush() is called (queue jobs /
 *    cron own scheduling).
 */
final class BatchClient
{
    /** @var array<int, array<string, mixed>> */
    private array $queue = [];

    public function __construct(
        private readonly string $siteId,
        private readonly string $endpoint,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?string $apiKey = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $batchSize = 20,
        private readonly int $maxRetries = 3,
        private readonly int $backoffBaseMs = 200,
    ) {
        if ($this->siteId === '' || $this->endpoint === '') {
            throw new PermanentException('siteId and endpoint are required');
        }
    }

    public function track(EventInterface $event): void
    {
        $payload = $event->toArray();
        $payload['idempotency_key'] = $event->eventId()
            ?? bin2hex(random_bytes(16));
        $payload['site_id'] = $this->siteId;
        $this->queue[] = $payload;
        if (count($this->queue) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function identify(string $visitorId, array $traits = []): void
    {
        $this->queue[] = [
            'schema_version' => \ClickTrail\Conventions\Stable::SCHEMA_VERSION,
            'source' => 'php',
            'site_id' => $this->siteId,
            'idempotency_key' => bin2hex(random_bytes(16)),
            'event' => ['name' => 'identify', 'traits' => $traits],
            'visitor_id' => $visitorId,
        ];
    }

    public function conversion(string $eventId, array $outcome): void
    {
        $this->queue[] = [
            'schema_version' => \ClickTrail\Conventions\Stable::SCHEMA_VERSION,
            'source' => 'php',
            'site_id' => $this->siteId,
            'idempotency_key' => $eventId,
            'event' => ['name' => 'conversion', 'ref' => $eventId] + $outcome,
        ];
    }

    public function refund(string $orderId, array $details = []): void
    {
        $this->track(new \ClickTrail\Client\Event\Refund($orderId, extra: $details));
    }

    public function consent(string $visitorId, string $state, string $policyVersion): void
    {
        $this->queue[] = [
            'schema_version' => \ClickTrail\Conventions\Stable::SCHEMA_VERSION,
            'source' => 'php',
            'site_id' => $this->siteId,
            'idempotency_key' => bin2hex(random_bytes(16)),
            'event' => ['name' => 'consent'],
            'visitor_id' => $visitorId,
            'consent' => ['state' => $state, 'policy_version' => $policyVersion],
        ];
    }

    /**
     * Payloads queued but not yet delivered - lets persistence layers
     * (e.g. Laravel failed-job storage) capture them verbatim.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pending(): array
    {
        return $this->queue;
    }

    /**
     * Re-queue payloads previously captured via pending() (job retry /
     * failed-event replay). No revalidation by design: payloads came from
     * this client and are already envelope-shaped.
     *
     * @param array<int, array<string, mixed>> $payloads
     */
    public function restore(array $payloads): void
    {
        foreach ($payloads as $payload) {
            if (is_array($payload)) {
                $this->queue[] = $payload;
            }
        }
    }

    /**
     * Send all queued events in batches. Returns number of batches delivered.
     */
    public function flush(): int
    {
        $batches = 0;
        while ($this->queue !== []) {
            $batch = array_splice($this->queue, 0, $this->batchSize);
            $this->deliver($batch);
            $batches++;
        }

        return $batches;
    }

    /** @param array<int, array<string, mixed>> $batch */
    private function deliver(array $batch): void
    {
        $body = (string) json_encode($batch, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $request = $this->requestFactory
            ->createRequest('POST', $this->endpoint)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-ClickTrail-Site-Id', $this->siteId)
            ->withBody($this->streamFactory->createStream($body));
        if ($this->apiKey !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->apiKey);
        }

        $attempt = 0;
        retry:
        try {
            $response = $this->http->sendRequest($request);
        } catch (\Psr\Http\Client\NetworkExceptionInterface $e) {
            if ($this->retryOrThrow(++$attempt, $e->getMessage())) {
                goto retry;
            }
        }

        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            $this->logger->debug('clicktrail batch delivered', ['events' => count($batch), 'status' => $status]);

            return;
        }
        if ($status === 429 || $status >= 500) {
            if ($this->retryOrThrow(++$attempt, "HTTP $status")) {
                goto retry;
            }
        }
        throw new PermanentException("ClickTrail ingestion rejected batch: HTTP $status");
    }

    /** Sleep with exponential backoff; true = retry again, false = raise. */
    private function retryOrThrow(int $attempt, string $reason): bool
    {
        if ($attempt > $this->maxRetries) {
            throw new RetryableException("Delivery failed after {$this->maxRetries} retries: $reason");
        }
        $base = $this->backoffBaseMs * (2 ** ($attempt - 1));
        $sleep = $base + random_int(0, intdiv($base, 2)); // jitter
        $this->logger->warning('clicktrail delivery retry', ['attempt' => $attempt, 'sleep_ms' => $sleep, 'reason' => $reason]);
        usleep($sleep * 1000);

        return true;
    }
}
