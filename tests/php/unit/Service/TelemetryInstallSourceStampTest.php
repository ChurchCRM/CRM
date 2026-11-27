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
    public function setValue(string $value): void { $this->value = $value; }
    public function getType(): string { return 'internal'; }
};
$configs = new \ReflectionProperty(SystemConfig::class, 'configs');

$setEnv = static function (?string $envValue): void {
    if ($envValue === null) {
        putenv('CHURCHCRM_INSTALL_SOURCE');
        unset($_ENV['CHURCHCRM_INSTALL_SOURCE'], $_SERVER['CHURCHCRM_INSTALL_SOURCE']);
    } else {
        putenv('CHURCHCRM_INSTALL_SOURCE=' . $envValue);
        $_SERVER['CHURCHCRM_INSTALL_SOURCE'] = $envValue;
    }
};

$stamp = static function (string $stored, ?string $envValue) use ($configs, $value, $setEnv): string {
    $item = $value($stored);
    $configs->setValue(null, ['sInstallSource' => $item]);
    $setEnv($envValue);
    TelemetryService::stampInstallSourceFromEnvironment();

    return $item->getValue();
};

// No marker yet and the environment names the channel: the value is stamped.
Assert::same('docker', $stamp('', 'docker'));
// The mechanism is generic: any provider's name stamps the same way, trimmed.
Assert::same('custom-panel', $stamp('', '  custom-panel  '));
// An existing marker always wins: the environment never overwrites it.
Assert::same('softaculous', $stamp('softaculous', 'docker'));
// No environment value (or an empty one): nothing is stamped.
Assert::same('', $stamp('', null));
Assert::same('', $stamp('', ''));

$setEnv(null);
