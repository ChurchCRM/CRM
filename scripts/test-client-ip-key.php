<?php

/**
 * scripts/test-client-ip-key.php — the address a sign-in throttle counts
 * (#10197).
 *
 * Usage:
 *   php scripts/test-client-ip-key.php
 *   npm run test:php
 *
 * RateLimiter::clientIpKey() counts an IPv4 client by its address and an IPv6
 * client by its /64. An IPv4-mapped IPv6 address (::ffff:a.b.c.d, which a
 * dual-stack server reports for IPv4 clients) must count as its IPv4 address:
 * its /64 is all zeros, so every mapped client would otherwise share one key
 * and one client's failures would lock out the rest. No database is needed.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 *   2  — vendor autoloader missing
 */

declare(strict_types=1);

use ChurchCRM\Service\RateLimiter;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing {$autoload} — run `composer install` in src/ first.\n");
    exit(2);
}
require $autoload;

$cases = [
    'IPv4 address'                   => ['203.0.113.7', '203.0.113.7'],
    'IPv4-mapped IPv6 address'       => ['::ffff:203.0.113.7', '203.0.113.7'],
    'another IPv4-mapped address'    => ['::ffff:198.51.100.20', '198.51.100.20'],
    'IPv6 address counts by its /64' => ['2001:db8:1:2:3:4:5:6', '2001:db8:1:2::/64'],
    'IPv6 loopback'                  => ['::1', '::/64'],
    'no address'                     => ['', ''],
];

$passed = 0;
$failed = 0;
foreach ($cases as $label => [$address, $expected]) {
    $_SERVER['REMOTE_ADDR'] = $address;
    $actual = RateLimiter::clientIpKey();
    $n = $passed + $failed + 1;
    if ($actual === $expected) {
        $passed++;
        echo "ok {$n} - {$label}\n";
        continue;
    }
    $failed++;
    echo "not ok {$n} - {$label}  # expected {$expected}, got {$actual}\n";
}

$total = $passed + $failed;
echo "1..{$total}\n";
echo "# {$total} cases, {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
