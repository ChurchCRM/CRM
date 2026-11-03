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

$propertiesFor = static function (string $country) use ($configs, $value, $baseProperties): array {
    $configs->setValue(null, ['sLanguage' => $value('en_US'), 'sChurchCountry' => $value($country)]);

    return $baseProperties->invoke(null);
};

Assert::same('US', $propertiesFor('US')['church_country']);
Assert::same('US', $propertiesFor('United States')['church_country']);
Assert::false(array_key_exists('church_country', $propertiesFor('')));
Assert::false(array_key_exists('church_country', $propertiesFor('Atlantis')));

$configs->setValue(null, ['sLanguage' => $value('en_US')]);
Assert::false(array_key_exists('church_country', $baseProperties->invoke(null)));
