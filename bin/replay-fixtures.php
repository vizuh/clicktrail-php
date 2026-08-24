<?php

declare(strict_types=1);

/**
 * P0 golden-fixture parity gate: replays clicktrail-js JSON fixtures against
 * this PHP core and emits a MATCH/DIFF/PENDING ledger.
 *
 * Usage: php bin/replay-fixtures.php <fixtures-dir> [ledger-out.md]
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'ClickTrail\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use ClickTrail\Core\AttributionInput;
use ClickTrail\Core\Touch;
use ClickTrail\Core\StoredState;
use ClickTrail\Core\TouchMerger;
use ClickTrail\Core\TouchParser;

$fixturesDir = $argv[1] ?? null;
$ledgerPath = $argv[2] ?? null;
if ($fixturesDir === null) {
    fwrite(STDERR, "usage: php bin/replay-fixtures.php <fixtures-dir> [ledger-out.md]\n");
    exit(2);
}

function flatState(StoredState $s): array
{
    $out = [];
    $emit = static function (string $prefix, ?\ClickTrail\Core\Touch $t) use (&$out): void {
        if ($t === null) {
            return;
        }
        foreach ([
            'source' => $t->source, 'medium' => $t->medium, 'campaign' => $t->campaign,
            'content' => $t->content, 'term' => $t->term, 'landing_page' => $t->landingPage,
            'touch_timestamp' => $t->touchTimestamp,
        ] as $k => $v) {
            $out[$prefix . $k] = $v ?? '';
        }
        foreach ($t->clickIds as $ck => $cv) {
            $out[$prefix . $ck] = $cv;
            $out[$ck] = $cv; // bare key too (fixture convention)
        }
    };
    $emit('ft_', $s->first);
    $emit('lt_', $s->last);

    return $out;
}

function storedToNested(array $flat): string
{
    $mk = static function (string $p) use ($flat): ?array {
        $keys = ['source', 'medium', 'campaign', 'content', 'term', 'landing_page', 'touch_timestamp'];
        $t = [];
        $has = false;
        foreach ($keys as $k) {
            if (isset($flat[$p . $k])) { $t[$k] = $flat[$p . $k]; $has = true; }
        }
        foreach (\ClickTrail\Conventions\Stable::CLICK_ID_KEYS as $ck) {
            $v = $flat['ft_' . $ck] ?? $flat['lt_' . $ck] ?? null;
            if ($v !== null) { $t['click_ids'][$ck] = $v; $has = true; }
        }
        return $has ? $t : null;
    };
    return (string) json_encode(['first' => $mk('ft_'), 'last' => $mk('lt_')], JSON_THROW_ON_ERROR);
}

function queryOf(string $url): array
{
    $q = [];
    $query = parse_url($url, PHP_URL_QUERY) ?? '';
    foreach (explode('&', $query) as $pair) {
        if ($pair === '') { continue; }
        [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
        $q[strtolower(urldecode($k))] = urldecode($v); // last wins; keys lowercase
    }
    return $q;
}

function compare(array $got, array $expected, ?string $reason): array
{
    $match = $diff = $pending = [];
    foreach ($expected as $k => $want) {
        if ($k === '_no_touch_reason') {
            if ($reason === $want) { $match[$k] = $want; }
            else { $diff[$k] = 'want=' . $want . ' got=' . ($reason ?? '(touch created)'); }
            continue;
        }
        if (!array_key_exists($k, $got)) { $pending[$k] = 'field not produced by PHP core'; continue; }
        if ((string) $got[$k] === (string) $want) { $match[$k] = $want; }
        else { $diff[$k] = 'want=' . $want . ' got=' . $got[$k]; }
    }
    return [count($match), count($diff), count($pending), ['m' => $match, 'd' => $diff, 'p' => $pending]];
}

$rows = []; $mTot = $dTot = $pTot = 0;
foreach (glob(rtrim($fixturesDir, '/') . '/*.json') ?: [] as $file) {
    $fixture = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $name = $fixture['name'] ?? basename($file);
    $input = $fixture['input'];
    $expected = $fixture['expected'];

    $stored = StoredState::fromJson(isset($fixture['stored']) ? storedToNested($fixture['stored']) : null);
    $in = new AttributionInput(
        query: queryOf($input['url']),
        host: $input['currentHost'],
        landingPage: $input['url'],
        referrer: $input['referrer'] !== '' ? $input['referrer'] : null,
        touchTimestamp: $input['now'],
    );

    $reason = TouchParser::noTouchReason($in);
    if ($reason !== null && isset($expected['_no_touch_reason'])) {
        [$m, $d, $p, $detail] = compare([], $expected, $reason);
    } else {
        $merged = TouchMerger::observe($stored, $in);
        $got = flatState($merged);
        $probe = \ClickTrail\Core\FixtureChannelProbe::probe($in, $merged->last ?? new Touch());
        if ($probe !== null) {
            $got['_channel'] = $probe[0];
            if ($probe[1] !== null) { $got['ft_channel'] = $probe[1]; }
            if ($probe[2] !== null) { $got['lt_channel'] = $probe[2]; }
        }
        [$m, $d, $p, $detail] = compare($got, $expected, $reason);
    }

    $mTot += $m; $dTot += $d; $pTot += $p;
    $status = $d > 0 ? 'DIFF' : ($p > 0 ? 'PARTIAL' : 'MATCH');
    $rows[] = [$name, $status, $m, $d, $p, $detail];
}

printf("FIXTURES: %d | fields MATCH=%d DIFF=%d PENDING=%d\n", count($rows), $mTot, $dTot, $pTot);
foreach ($rows as [$name, $status, $m, $d, $p]) {
    printf("%-42s %-8s match=%d diff=%d pending=%d\n", $name, $status, $m, $d, $p);
}
foreach ($rows as [$name, , , , , $detail]) {
    foreach ($detail['d'] as $k => $why) { printf("  DIFF %s :: %s\n", $name, $why); }
}

if ($ledgerPath !== null) {
    $md = "# Fixture Parity Ledger (P0 gate)\n\nGenerated " . gmdate('c') .
        " by bin/replay-fixtures.php against clicktrail-js fixtures.\n\n" .
        "| Fixture | Status | Match | Diff | Pending |\n|---|---|---|---|---|\n";
    foreach ($rows as [$name, $status, $m, $d, $p]) {
        $md .= "| $name | $status | $m | $d | $p |\n";
    }
    $md .= "\n## Field-level diffs and pendings\n\n";
    foreach ($rows as [$name, , , , , $detail]) {
        foreach ($detail['d'] as $k => $why) { $md .= "- **$name** `$k`: $why\n"; }
        foreach ($detail['p'] as $k => $why) { $md .= "- **$name** `$k`: PENDING - $why\n"; }
    }
    file_put_contents($ledgerPath, $md . "\n");
    fwrite(STDERR, "ledger written: $ledgerPath\n");
}
