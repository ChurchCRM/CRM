<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);
$value = static fn (string $value) => new class($value) {
    public function __construct(private string $value) {}
    public function getValue(): string { return $this->value; }
};
$configs = new \ReflectionProperty(SystemConfig::class, 'configs');
$baseProperties = new \ReflectionMethod(TelemetryService::class, 'baseProperties');

$propertiesFor = static function (array $seed) use ($configs, $value, $baseProperties): array {
    $configs->setValue(null, array_map($value, $seed + ['sLanguage' => 'en_US']));

    return $baseProperties->invoke(null);
};

// The stored value is reported as-is: the mechanism is generic, so a value
// recorded by any installer must come through unchanged.
Assert::same('softaculous', $propertiesFor(['sInstallSource' => 'softaculous'])['install_source']);
Assert::same('some-other-installer', $propertiesFor(['sInstallSource' => 'some-other-installer'])['install_source']);

// No marker (or an empty one) means no property, like church_country.
Assert::false(array_key_exists('install_source', $propertiesFor(['sInstallSource' => ''])));
Assert::false(array_key_exists('install_source', $propertiesFor([])));
