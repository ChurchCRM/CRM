<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Monolog\Level;
use Monolog\LogRecord;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

// Intercept the transport before cURL can open a connection.
function curl_init(string $url): false
{
    $GLOBALS['telemetryTransportCalls']++;
    return false;
}

$GLOBALS['telemetryTransportCalls'] = 0;
putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY');
putenv('PHP_TEST_DISPOSABLE_DB');
SystemURLs::init('', [], APP_ROOT);

foreach (['' => 'production', 'invalid' => 'production', 'production' => 'production', 'demo' => 'demo', 'test' => 'test'] as $stored => $expected) {
    Assert::same($expected, TelemetryService::resolveInstallProfile($stored, false));
    Assert::same('test', TelemetryService::resolveInstallProfile($stored, true));
}
Assert::same('production', TelemetryService::installProfile());

$value = static fn (string $value) => new class($value) {
    public function __construct(private string $value) {}
    public function getValue(): string { return $this->value; }
};
$configs = new \ReflectionProperty(SystemConfig::class, 'configs');
$baseProperties = new \ReflectionMethod(TelemetryService::class, 'baseProperties');
$capture = new \ReflectionMethod(TelemetryService::class, 'capture');

foreach (['production', 'demo', 'test'] as $profile) {
    $configs->setValue(null, [
        'sLanguage' => $value('en_US'),
        'sInstallDataProfile' => $value($profile),
        'sTelemetryLevel' => $value('full'),
        'sSystemID' => $value('install-id'),
    ]);
    Assert::same($profile, TelemetryService::installProfile());
    Assert::same($profile, $baseProperties->invoke(null)['install_profile']);
    $before = $GLOBALS['telemetryTransportCalls'];
    $suppressed = in_array($profile, ['test'], true);
    Assert::same($suppressed, $capture->invoke(null, 'install_observed', $baseProperties->invoke(null)));
    Assert::same($suppressed, $capture->invoke(null, 'upgrade_completed', $baseProperties->invoke(null)));
    TelemetryService::capturePageView('/people/person');
    TelemetryService::captureLogEvent(new LogRecord(new \DateTimeImmutable(), 'app', Level::Error, 'Error'));
    TelemetryService::captureUpgradeSqlDuration('7.8.3', 10, true);
    TelemetryService::captureInstallFailure('RuntimeException');
    TelemetryService::captureUpgradeFailure('7.8.3', 'RuntimeException');
    TelemetryService::captureUpgradeSqlError('7.8.3', 10, 'Database error');
    TelemetryService::captureUpgradeInterruption('7.8.3', 10);
    Assert::same($suppressed ? 0 : 9, $GLOBALS['telemetryTransportCalls'] - $before);
}

$configs->setValue(null, ['sInstallDataProfile' => $value('production'), 'sLanguage' => $value('en_US')]);
foreach (['CHURCHCRM_DISABLE_POSTHOG_TELEMETRY', 'PHP_TEST_DISPOSABLE_DB'] as $override) {
    putenv($override . '=1');
    Assert::same('test', TelemetryService::installProfile());
    Assert::same('test', $baseProperties->invoke(null)['install_profile']);
    $before = $GLOBALS['telemetryTransportCalls'];
    TelemetryService::captureInstallFailure('RuntimeException');
    Assert::same($before, $GLOBALS['telemetryTransportCalls']);
    putenv($override);
}
Assert::same('production', TelemetryService::installProfile());

$configs->setValue(null, ['sInstallDataProfile' => new class {
    public function getValue(): string { throw new \RuntimeException('Config unavailable'); }
}]);
Assert::same('production', TelemetryService::installProfile());

$seed = file_get_contents(dirname(APP_ROOT) . '/cypress/data/seed.sql');
Assert::contains("('sInstallDataProfile','test')", $seed);
$demo = file_get_contents(APP_ROOT . '/ChurchCRM/Service/DemoDataService.php');
Assert::contains("\$this->importSystemConfig(\$force);\n            SystemConfig::setValue('sInstallDataProfile', 'demo');", $demo);
