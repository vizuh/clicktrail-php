<?php
spl_autoload_register(function ($class) {
    $prefix = 'ClickTrail\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = '/app/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require $path;
});

use ClickTrail\Consent\ConsentBehavior;
use ClickTrail\Consent\ConsentSnapshot;
use ClickTrail\Consent\ConsentValue;

function check(bool $c, string $m): void { if (!$c) { fwrite(STDERR, "FAIL: $m\n"); exit(1); } }

$g = fn() => ConsentValue::Granted; $d = fn() => ConsentValue::Denied; $u = fn() => ConsentValue::Unknown;

// Apointoo scenario from the plan: analytics granted, ads granted, ad_user_data granted, personalization denied
$snap = new ConsentSnapshot('cookieyes', '2026-08-24T14:32:00Z', $g(), $g(), $g(), $g(), $d(), '1.2', 'rcpt_9', ['C0002','C0004']);

check(ConsentBehavior::can($snap, 'analytics') === true, 'analytics allowed');
check(ConsentBehavior::can($snap, 'advertising_storage') === true, 'ad storage allowed');
check(ConsentBehavior::can($snap, 'ad_user_data') === true, 'enhanced conversion allowed');
check(ConsentBehavior::can($snap, 'ad_personalization') === false, 'personalization denied');

// unknown defaults to denied (marketplace-safe)
$unknown = new ConsentSnapshot('custom', '2026-08-24T14:32:00Z', $u(), $u(), $u(), $u(), $u());
foreach (['analytics','advertising_storage','ad_user_data','ad_personalization'] as $cap) {
    check(ConsentBehavior::can($unknown, $cap) === false, "unknown denied: $cap");
}
check(ConsentBehavior::suppressionReason($unknown, 'ad_user_data') ===
    'adUserData was unknown at lead capture (source: custom)', 'suppression reason');

// JSON round-trip with degraded values coerced to unknown
$r = ConsentSnapshot::fromArray(json_decode($snap->toJson(), true));
check($r->adPersonalization === ConsentValue::Denied, 'round trip denied');
check(ConsentSnapshot::fromArray(['collected_at'=>'x'])->adUserData === ConsentValue::Unknown, 'missing -> unknown');

echo "CONSENT ASSERTIONS PASSED\n";
