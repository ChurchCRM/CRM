<?php

declare(strict_types=1);

use ChurchCRM\Service\RateLimiter;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$cases = [
    'IPv4 address'                   => ['203.0.113.7', '203.0.113.7'],
    'IPv4-mapped IPv6 address'       => ['::ffff:203.0.113.7', '203.0.113.7'],
    'another IPv4-mapped address'    => ['::ffff:198.51.100.20', '198.51.100.20'],
    'IPv6 address counts by its /64' => ['2001:db8:1:2:3:4:5:6', '2001:db8:1:2::/64'],
    'IPv6 loopback'                  => ['::1', '::/64'],
    'no address'                     => ['', ''],
];

foreach ($cases as $label => [$address, $expected]) {
    $_SERVER['REMOTE_ADDR'] = $address;
    Assert::same($expected, RateLimiter::clientIpKey(), $label);
}
