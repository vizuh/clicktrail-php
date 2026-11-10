<?php

declare(strict_types=1);

namespace ClickTrail\Tests;

use ClickTrail\Conventions\Stable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cross-runtime parity with @vizuh/clicktrail-core deriveStableEventId.
 * Vectors are vendored verbatim from clicktrail-js/fixtures/stable-event-id-v1.json.
 */
final class StableEventIdTest extends TestCase
{
    /** @return array{algorithm: string, vectors: list<array{siteId: string, externalKey: string, eventId: string}>} */
    private static function fixture(): array
    {
        $json = file_get_contents(__DIR__ . '/fixtures/stable-event-id-v1.json');

        return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function vectors(): iterable
    {
        foreach (self::fixture()['vectors'] as $vector) {
            yield $vector['siteId'] . ' / ' . $vector['externalKey'] => [
                $vector['siteId'],
                $vector['externalKey'],
                $vector['eventId'],
            ];
        }
    }

    public function testFixtureIsTheSharedV1AlgorithmWithANonAsciiVector(): void
    {
        $fixture = self::fixture();
        self::assertSame('sha256-128-v1', $fixture['algorithm']);
        self::assertCount(3, $fixture['vectors']);
        $nonAscii = array_filter(
            $fixture['vectors'],
            static fn (array $v): bool => preg_match('/[^\x00-\x7F]/', $v['siteId'] . $v['externalKey']) === 1,
        );
        self::assertNotEmpty($nonAscii, 'fixture must keep a non-ASCII vector');
    }

    #[DataProvider('vectors')]
    public function testMatchesSharedFixtureVector(string $siteId, string $externalKey, string $expected): void
    {
        self::assertSame($expected, Stable::deriveStableEventId($siteId, $externalKey));
    }

    public function testHashesUtf8BytesNotUtf16CodeUnits(): void
    {
        // Same id as the fixture's non-ASCII vector; a UTF-16 or Latin-1 encoding would differ.
        self::assertSame(
            'evt_s-75d330cfcd7ea174b0361670e12ad586',
            Stable::deriveStableEventId("\u{00FC}n\u{00EF}code-sit\u{00E9}", "\u{043A}\u{043B}\u{044E}\u{0447}\u{2713}"),
        );
    }

    public function testFormatAndSeparation(): void
    {
        self::assertMatchesRegularExpression('/^evt_s-[0-9a-f]{32}$/', Stable::deriveStableEventId('site-1', 'booking-1:completed'));
        // '0' is a non-empty string in JS; PHP must not treat it as empty().
        self::assertMatchesRegularExpression('/^evt_s-[0-9a-f]{32}$/', Stable::deriveStableEventId('0', '0'));
        self::assertNotSame(
            Stable::deriveStableEventId('site-1', 'xXQY9Q9m8YsVb0LI'),
            Stable::deriveStableEventId('site-1', 'GSpqZXJtjd9IBZ6f'),
        );
        // The NUL separator keeps (siteId, externalKey) boundaries distinct.
        self::assertNotSame(
            Stable::deriveStableEventId('ab', 'c'),
            Stable::deriveStableEventId('a', 'bc'),
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function emptyArgs(): iterable
    {
        yield 'empty siteId' => ['', 'event-1'];
        yield 'empty externalKey' => ['site-1', ''];
    }

    #[DataProvider('emptyArgs')]
    public function testRejectsEmptyScopeComponents(string $siteId, string $externalKey): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/non-empty/');
        Stable::deriveStableEventId($siteId, $externalKey);
    }
}
