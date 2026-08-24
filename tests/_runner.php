<?php
spl_autoload_register(function ($class) {
    $prefix = 'ClickTrail\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = '/app/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require $path;
});

use ClickTrail\Core\AttributionInput;
use ClickTrail\Core\StoredState;
use ClickTrail\Core\TouchMerger;
use ClickTrail\Core\TouchParser;

function check(bool $cond, string $msg): void {
    if (!$cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); }
}

$ts1 = '2026-08-24T10:00:00.000Z';
$ts3 = '2026-08-24T11:00:00.000Z';
$paid = function (string $ts, string $path = '/promo') use ($ts1) { if ($ts === '') { $ts = $ts1; } return new AttributionInput(
    ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'summer', 'gclid' => 'XYZ1'],
    'example.com', 'https://example.com' . $path, null, $ts); };

// T1 paid search landing
$s = TouchMerger::observe(StoredState::empty(), $paid(''));
check($s->first->source === 'google', 'T1 source');
check(($s->first->clickIds['gclid'] ?? '') === 'XYZ1', 'T1 gclid');
check($s->first->touchTimestamp === $s->last->touchTimestamp, 'T1 first==last ts');

// T2 direct visit preserves first
$direct = new AttributionInput([], 'example.com', 'https://example.com/pricing', null, $ts3);
$m2 = TouchMerger::observe($s, $direct);
check($m2->first->touchTimestamp === $ts1, 'T2 first preserved');
check($m2->last->campaign === 'summer', 'T2 stored last persists');

// T3 new signal updates last not first
$fb = new AttributionInput(['utm_source' => 'facebook', 'utm_medium' => 'paid_social', 'fbclid' => 'F1'],
    'example.com', 'https://example.com/x', null, $ts3);
$m3 = TouchMerger::observe($m2, $fb);
check($m3->first->source === 'google' && $m3->last->source === 'facebook', 'T3 first untouched last updated');

// T4 referrer classification
check(TouchParser::hasSignal(new AttributionInput([], 'example.com', 'u', 'https://news.example.org/a')) === true, 'T4 external is signal');
check(TouchParser::hasSignal(new AttributionInput([], 'example.com', 'u', 'https://example.com/menu')) === false, 'T4 internal ignored');

// T5 JSON round-trip + corruption guard
check(StoredState::fromJson('{bad')->first === null, 'T5 corrupt degrades');
$r = StoredState::fromJson($m3->toJson());
check($r->last->clickIds['fbclid'] === 'F1', 'T5 round trip');

echo "PHP CORE ASSERTIONS PASSED\n";
