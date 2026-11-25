<?php

declare(strict_types=1);

use ChurchCRM\Utils\RequestUtils;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$proxies = '10.0.0.0/8, 2001:db8:ffff::/48';

$cases = [
    'a direct visitor is the peer'                        => ['203.0.113.7', null, $proxies, '203.0.113.7'],
    'an untrusted peer cannot choose its address'         => ['203.0.113.7', '198.51.100.1', $proxies, '203.0.113.7'],
    'a trusted proxy gives the client'                    => ['10.0.0.2', '203.0.113.7', $proxies, '203.0.113.7'],
    'a client cannot prepend an address of its choosing'  => ['10.0.0.2', '198.51.100.1, 203.0.113.7', $proxies, '203.0.113.7'],
    'a chain of trusted proxies is walked past'           => ['10.0.0.2', '203.0.113.7, 10.1.2.3, 10.4.5.6', $proxies, '203.0.113.7'],
    'a mapped IPv4 peer matches an IPv4 range'            => ['::ffff:10.0.0.2', '203.0.113.7', $proxies, '203.0.113.7'],
    'a mapped IPv4 proxy in the chain is walked past'     => ['10.0.0.2', '203.0.113.7, ::ffff:10.1.2.3', $proxies, '203.0.113.7'],
    'an IPv6 client behind a trusted IPv6 proxy'          => ['2001:db8:ffff::1', '2001:db8:1:2::9', $proxies, '2001:db8:1:2::9'],
    'a trusted proxy without the header is the peer'      => ['10.0.0.2', null, $proxies, '10.0.0.2'],
    'an empty header is the peer'                         => ['10.0.0.2', '', $proxies, '10.0.0.2'],
    'only trusted proxies in the header is the peer'      => ['10.0.0.2', '10.9.9.9', $proxies, '10.0.0.2'],
    'something that is not an address ends at the peer'   => ['10.0.0.2', '203.0.113.7, unknown', $proxies, '10.0.0.2'],
    'no trusted proxies configured'                       => ['10.0.0.2', '203.0.113.7', '', '10.0.0.2'],
    'a malformed proxy list trusts nothing'               => ['10.0.0.2', '203.0.113.7', '10.0.0.0/99, not-an-ip', '10.0.0.2'],
    'no peer address'                                     => [null, '203.0.113.7', $proxies, ''],
];

foreach ($cases as $label => [$peer, $forwardedFor, $trusted, $expected]) {
    $server = array_filter(['REMOTE_ADDR' => $peer, 'HTTP_X_FORWARDED_FOR' => $forwardedFor], static fn (?string $value): bool => $value !== null);
    Assert::same($expected, RequestUtils::clientAddress($server, $trusted), $label);
}

$trustCases = [
    'a mapped IPv4 address in an IPv4 range'      => ['::ffff:172.18.0.5', '172.18.0.0/16', true],
    'a mapped IPv4 address outside an IPv4 range' => ['::ffff:172.19.0.5', '172.18.0.0/16', false],
    'a mapped IPv4 address in an IPv6 range'      => ['::ffff:172.18.0.5', '::ffff:0:0/96', true],
    'an IPv6 address is not an IPv4 address'      => ['2001:db8::ac12:5', '172.18.0.5', false],
];

foreach ($trustCases as $label => [$ip, $trusted, $expected]) {
    Assert::same($expected, RequestUtils::isTrustedProxy($ip, $trusted), $label);
}
