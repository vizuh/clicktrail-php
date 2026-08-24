<?php

declare(strict_types=1);

namespace ClickTrail\Tests;

use ClickTrail\Core\AttributionInput;
use ClickTrail\Core\StoredState;
use ClickTrail\Core\TouchMerger;
use ClickTrail\Core\TouchParser;
use PHPUnit\Framework\TestCase;

final class MergeLawsTest extends TestCase
{
    private const TS1 = '2026-08-24T10:00:00.000Z';
    private const TS3 = '2026-08-24T11:00:00.000Z';

    private function paidSearchInput(string $ts = self::TS1, string $path = '/promo'): AttributionInput
    {
        return new AttributionInput(
            query: ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'summer', 'gclid' => 'XYZ1'],
            host: 'example.com',
            landingPage: 'https://example.com' . $path,
            referrer: null,
            touchTimestamp: $ts,
        );
    }

    public function testPaidSearchLandingCapturesUtmAndClickId(): void
    {
        $state = TouchMerger::observe(StoredState::empty(), $this->paidSearchInput());

        self::assertNotNull($state->first);
        self::assertSame('google', $state->first->source);
        self::assertSame('XYZ1', $state->first->clickIds['gclid']);
        self::assertSame(self::TS1, $state->first->touchTimestamp);
        self::assertSame($state->first->touchTimestamp, $state->last?->touchTimestamp);
    }

    public function testDirectVisitPreservesFirstTouch(): void
    {
        $afterAd = TouchMerger::observe(StoredState::empty(), $this->paidSearchInput());
        $direct = new AttributionInput([], 'example.com', 'https://example.com/pricing', null, self::TS3);

        $merged = TouchMerger::observe($afterAd, $direct);

        self::assertSame($afterAd->first->touchTimestamp, $merged->first->touchTimestamp);
        // stored last persists across direct visits
        self::assertSame('summer', $merged->last->campaign);
    }

    public function testNewSignalUpdatesLastButNotFirst(): void
    {
        $afterAd = TouchMerger::observe(StoredState::empty(), $this->paidSearchInput());
        $facebook = new AttributionInput(
            ['utm_source' => 'facebook', 'utm_medium' => 'paid_social', 'fbclid' => 'F1'],
            'example.com',
            'https://example.com/x',
            null,
            self::TS3,
        );

        $merged = TouchMerger::observe($afterAd, $facebook);

        self::assertSame('google', $merged->first->source);
        self::assertSame('facebook', $merged->last->source);
        self::assertSame('F1', $merged->last->clickIds['fbclid']);
    }

    public function testExternalReferrerIsSignalButInternalIsNot(): void
    {
        self::assertTrue(TouchParser::hasSignal(new AttributionInput(
            [], 'example.com', 'https://example.com/y', 'https://news.example.org/article', self::TS1,
        )));
        self::assertFalse(TouchParser::hasSignal(new AttributionInput(
            [], 'example.com', 'https://example.com/z', 'https://example.com/menu', self::TS1,
        )));
    }

    public function testCorruptedStoredJsonDegradesToFreshState(): void
    {
        $state = StoredState::fromJson('{not json');
        self::assertNull($state->first);
        self::assertNull($state->last);
    }

    public function testStoredStateRoundTripsThroughJson(): void
    {
        $state = TouchMerger::observe(StoredState::empty(), $this->paidSearchInput());
        $restored = StoredState::fromJson($state->toJson());

        self::assertSame($state->first->source, $restored->first->source);
        self::assertSame($state->last->clickIds, $restored->last->clickIds);
    }
}
