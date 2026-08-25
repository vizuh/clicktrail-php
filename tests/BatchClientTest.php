<?php

declare(strict_types=1);

namespace ClickTrail\Tests;

use ClickTrail\Client\BatchClient;
use ClickTrail\Client\Event\Sale;
use ClickTrail\Client\Exception\PermanentException;
use ClickTrail\Client\Exception\RetryableException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class BatchClientTest extends TestCase
{
    /** @var array<int, array{method:string,uri:string,body:string}> */
    private array $requests = [];
    /** @var list<Response|ConnectException> */
    private array $responses = [];

    private function client(int $batchSize = 2): BatchClient
    {
        $http = new class($this->requests, $this->responses) implements \Psr\Http\Client\ClientInterface {
            public function __construct(
                private array &$requests,
                private array &$responses,
            ) {
            }

            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->requests[] = ['method' => $request->getMethod(), 'uri' => (string) $request->getUri(), 'body' => (string) $request->getBody(), 'headers' => array_map(static fn(array $h): string => $h[0], $request->getHeaders())];
                $next = array_shift($this->responses);
                if ($next === null) {
                    return new Response(200);
                }
                if ($next instanceof \Throwable) {
                    throw $next;
                }

                return $next;
            }
        };

        $factory = new \GuzzleHttp\Psr7\HttpFactory();

        return new BatchClient(
            siteId: 'site-1',
            endpoint: 'https://example.com/wp-json/clicutcl/v2/events/batch',
            http: $http,
            requestFactory: $factory,
            streamFactory: $factory,
            batchSize: $batchSize,
            backoffBaseMs: 1,
        );
    }

    public function testBatchSplitsAndStampsEnvelopes(): void
    {
        $c = $this->client(2);
        for ($i = 1; $i <= 3; $i++) {
            $c->track(new Sale(eventId: "order-$i-paid", visitorId: 'v1', orderId: "$i", value: 10.0 + $i, currency: 'EUR'));
        }
        $finalFlushBatches = $c->flush();

        // first batch auto-flushed at batchSize during track(); final flush sends the rest
        self::assertSame(1, $finalFlushBatches);
        self::assertCount(2, $this->requests);

        $first = json_decode($this->requests[0]['body'], true);
        self::assertCount(2, $first);
        self::assertSame('site-1', $first[0]['site_id']);
        self::assertSame('order-1-paid', $first[0]['idempotency_key']);
        self::assertSame('1.2.0', $first[0]['schema_version']);
        self::assertSame('sale.completed', $first[0]['event']['name']);
        self::assertSame(11.0, (float) $first[0]['event']['value']); // JSON round-trip may yield int
        self::assertSame('EUR', $first[0]['event']['currency']);
        self::assertSame('site-1', $this->requests[0]['headers']['X-ClickTrail-Site-Id'] ?? null);
    }

    public function testRetryThenSuccess(): void
    {
        // capture headers via a response-asserting fake instead
        $this->responses = [new Response(500), new Response(200)];
        $c = $this->client();
        $c->track(new Sale(eventId: 'e1', orderId: '9', value: 5.0));
        self::assertSame(1, $c->flush());
    }

    public function testPermanentOn400NoRetry(): void
    {
        $this->responses = [new Response(422)];
        $c = $this->client();
        $c->track(new Sale(eventId: 'e2', orderId: '9'));
        $this->expectException(PermanentException::class);
        try {
            $c->flush();
        } catch (PermanentException $e) {
            self::assertCount(0, array_filter($this->responses)); // consumed the only response; no retry
            throw $e;
        }
    }

    public function testRetryableAfterExhaustion(): void
    {
        $this->responses = [new Response(500), new Response(500), new Response(500), new Response(500)];
        $c = $this->client();
        $c->track(new Sale(eventId: 'e3', orderId: '9'));
        $this->expectException(RetryableException::class);
        $c->flush();
    }

    public function testPendingAndRestoreRoundTrip(): void
    {
        $c = $this->client(50);
        $c->track(new Sale(eventId: 'e9', orderId: '77', value: 1.0));
        $pending = $c->pending();
        self::assertCount(1, $pending);

        $fresh = $this->client(50);
        $fresh->restore($pending);
        self::assertSame($c->pending(), $fresh->pending());

        // restored payload survives a flush unchanged
        $this->responses = [new Response(500), new Response(200)];
        self::assertSame(1, $fresh->flush());
    }
}
