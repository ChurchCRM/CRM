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

// Behind a trusted proxy each visitor has its own key, whatever the visitor sends.
putenv('CHURCHCRM_TRUSTED_PROXIES=172.18.0.0/16');
$_SERVER['REMOTE_ADDR'] = '172.18.0.5';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
Assert::same('203.0.113.7', RateLimiter::clientIpKey(), 'a visitor behind the proxy');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:db8:1:2:3:4:5:6';
Assert::same('2001:db8:1:2::/64', RateLimiter::clientIpKey(), 'an IPv6 visitor behind the proxy counts by its /64');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.1, 198.51.100.20';
Assert::same('198.51.100.20', RateLimiter::clientIpKey(), 'another visitor, who sent an address of their own');
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
Assert::same('203.0.113.9', RateLimiter::clientIpKey(), 'a peer that is not a trusted proxy');
