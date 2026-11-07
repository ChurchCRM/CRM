<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\TelemetryService;
use ChurchCRM\Utils\SQLImportException;
use ChurchCRM\Utils\VersionUtils;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

Assert::same('https://us.i.posthog.com/i/v0/e/', TelemetryService::POSTHOG_CAPTURE_ENDPOINT);
$acknowledgement = new ReflectionMethod(TelemetryService::class, 'receivedCaptureAcknowledgement');
Assert::true($acknowledgement->invoke(null, 'Ok', 200));
Assert::false($acknowledgement->invoke(null, 'Invalid', 400));
Assert::false($acknowledgement->invoke(null, false, 0));
$errorSanitizer = new ReflectionMethod(TelemetryService::class, 'sanitizeDatabaseError');
Assert::same("Duplicate entry '[redacted]' for key '[redacted]'", $errorSanitizer->invoke(null, "Duplicate entry 'private-value' for key 'member_email'"));
$sqlFailure = new SQLImportException('INSERT INTO private_table VALUES (1)', 'Duplicate entry \'private-value\' for key \'member_email\'');
Assert::same("Duplicate entry 'private-value' for key 'member_email'", $sqlFailure->getDatabaseError());

$configs = new ReflectionProperty(SystemConfig::class, 'configs');
$configs->setValue(null, [
    'sLanguage' => new class {
        public function getValue(): string { return 'en_US'; }
    },
]);

$firstSync = TelemetryService::buildLifecycleEvent(TelemetryService::LEVEL_ERRORS, 'install-id', '', '7.8.3');
Assert::same('install_observed', $firstSync['event']);
Assert::same('production', $firstSync['properties']['install_profile']);
Assert::same(['$lib', '$geoip_disable', 'crm_version', 'install_profile', 'locale', 'php_version', 'os_family', 'install_path_type'], array_keys($firstSync['properties']));
$normalizeRoute = new ReflectionMethod(TelemetryService::class, 'normalizePageRoute');
Assert::same('/people/person', $normalizeRoute->invoke(null, '/churchcrm/people/person?personId=42#tab', '/churchcrm'));
Assert::same('/people/person', $normalizeRoute->invoke(null, '/people/person?personId=42#tab', ''));
SystemURLs::init('/churchcrm', [], APP_ROOT);
$subdirectoryEvent = TelemetryService::buildLifecycleEvent(TelemetryService::LEVEL_ERRORS, 'install-id', '', '7.8.3');
Assert::same('subdirectory', $subdirectoryEvent['properties']['install_path_type']);
SystemURLs::init('', [], APP_ROOT);
$rootEvent = TelemetryService::buildLifecycleEvent(TelemetryService::LEVEL_ERRORS, 'install-id', '', '7.8.3');
Assert::same('root', $rootEvent['properties']['install_path_type']);

Assert::same(null, TelemetryService::buildLifecycleEvent(TelemetryService::LEVEL_WARNINGS, 'install-id', '7.8.3', '7.8.3'));
Assert::same(null, TelemetryService::buildLifecycleEvent(TelemetryService::LEVEL_NONE, 'install-id', '', '7.8.3'));
Assert::same(null, TelemetryService::buildLifecycleEvent(TelemetryService::LEVEL_ERRORS, '', '', '7.8.3'));

$upgrade = TelemetryService::buildLifecycleEvent(TelemetryService::LEVEL_ERRORS, 'install-id', '7.8.2', '7.8.3');
Assert::same('upgrade_completed', $upgrade['event']);
Assert::same('7.8.2', $upgrade['properties']['from_version']);
Assert::same('7.8.3', $upgrade['properties']['to_version']);
Assert::same(['$lib', '$geoip_disable', 'crm_version', 'install_profile', 'locale', 'php_version', 'os_family', 'install_path_type', 'from_version', 'to_version'], array_keys($upgrade['properties']));

$configValue = static fn (string $value) => new class($value) {
    public function __construct(private string $value) {}
    public function getValue(): string { return $this->value; }
    public function setValue(string $value): void { $this->value = $value; }
    public function getType(): string { return 'internal'; }
};
$configs->setValue(null, [
    'sLanguage' => $configValue('en_US'),
    'sTelemetryLevel' => $configValue(TelemetryService::LEVEL_ERRORS),
    'sSystemID' => $configValue('install-id'),
    'sTelemetryLifecycleVersion' => $configValue(''),
]);
$currentVersion = VersionUtils::getInstalledVersion();
$sentEvents = [];
$sender = static function (string $event, array $properties) use (&$sentEvents): bool {
    $sentEvents[] = ['event' => $event, 'properties' => $properties];
    return true;
};
TelemetryService::syncLifecycle($sender);
Assert::same('install_observed', $sentEvents[0]['event']);
Assert::same($currentVersion, SystemConfig::getValue('sTelemetryLifecycleVersion'));
TelemetryService::syncLifecycle($sender);
Assert::same(1, count($sentEvents));

SystemConfig::setValue('sTelemetryLifecycleVersion', '7.8.2');
TelemetryService::syncLifecycle($sender);
Assert::same('upgrade_completed', $sentEvents[1]['event']);
Assert::same('7.8.2', $sentEvents[1]['properties']['from_version']);
Assert::same($currentVersion, $sentEvents[1]['properties']['to_version']);

SystemConfig::setValue('sTelemetryLifecycleVersion', '');
SystemConfig::setValue('sTelemetryLevel', TelemetryService::LEVEL_NONE);
TelemetryService::syncLifecycle($sender);
Assert::same(2, count($sentEvents));
Assert::same('', SystemConfig::getValue('sTelemetryLifecycleVersion'));

SystemConfig::setValue('sTelemetryLevel', TelemetryService::LEVEL_ERRORS);
$notAcknowledged = static fn (): bool => false;
TelemetryService::syncLifecycle($notAcknowledged);
Assert::same('', SystemConfig::getValue('sTelemetryLifecycleVersion'));
