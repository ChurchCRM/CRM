<?php

declare(strict_types=1);

use ChurchCRM\Plugin\ApprovedPluginRegistry;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$entry = static fn (string $id, array $extra = []): array => [
    'id'          => $id,
    'name'        => $id,
    'version'     => '1.0.0',
    'downloadUrl' => "https://example.test/$id.zip",
    'sha256'      => str_repeat('a', 64),
    'risk'        => 'low',
    'riskSummary' => 'Test entry',
] + $extra;

$fixtures = sys_get_temp_dir() . '/churchcrm-registry-' . getmypid();
@mkdir($fixtures);
file_put_contents($fixtures . '/registry.json', json_encode(['plugins' => [
    $entry('no-homepage'),
    $entry('https-homepage', ['homepage' => 'https://example.test/plugin']),
    $entry('http-homepage', ['homepage' => 'http://example.test/plugin']),
    $entry('script-homepage', ['homepage' => 'javascript:alert(1)']),
    $entry('relative-homepage', ['homepage' => '//example.test/plugin']),
    $entry('array-homepage', ['homepage' => ['https://example.test']]),
]]));
putenv('CHURCHCRM_PLUGIN_FIXTURES=' . $fixtures);

$_SESSION = [];
ApprovedPluginRegistry::fetchRemoteRegistry();
$accepted = array_keys($_SESSION['RemotePluginRegistry']);

unlink($fixtures . '/registry.json');
rmdir($fixtures);

Assert::same(['no-homepage', 'https-homepage'], $accepted);
