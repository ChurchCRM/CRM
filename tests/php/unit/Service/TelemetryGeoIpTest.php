<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Monolog\Level;
use Monolog\LogRecord;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

// Inspect serialized requests without opening a network connection.
function curl_init(string $url): object
{
    Assert::same(TelemetryService::POSTHOG_CAPTURE_ENDPOINT, $url);
    return new \stdClass();
}

function curl_setopt_array(object $handle, array $options): bool
{
    $GLOBALS['telemetryPayloads'][] = json_decode($options[CURLOPT_POSTFIELDS], true, 512, JSON_THROW_ON_ERROR);
    return true;
}

function curl_exec(object $handle): string { return 'Ok'; }
function curl_getinfo(object $handle, int $option): int { return 200; }

putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY');
putenv('PHP_TEST_DISPOSABLE_DB');
SystemURLs::init('', [], APP_ROOT);
$GLOBALS['telemetryPayloads'] = [];
$value = static fn (string $value) => new class($value) {
    public function __construct(private string $value) {}
    public function getValue(): string { return $this->value; }
};
$configs = new \ReflectionProperty(SystemConfig::class, 'configs');
$configs->setValue(null, [
    'sLanguage' => $value('en_US'),
    'sTelemetryLevel' => $value('full'),
    'sSystemID' => $value('install-id'),
]);

TelemetryService::capturePageView('/people/person');
TelemetryService::captureLogEvent(new LogRecord(new \DateTimeImmutable(), 'app', Level::Error, 'Error'));
$capture = new \ReflectionMethod(TelemetryService::class, 'capture');
foreach (['' => 'install_observed', '7.8.2' => 'upgrade_completed'] as $coveredVersion => $event) {
    $lifecycle = TelemetryService::buildLifecycleEvent('full', 'install-id', $coveredVersion, '7.8.3');
    Assert::same($event, $lifecycle['event']);
    Assert::true($capture->invoke(null, $event, $lifecycle['properties']));
}
TelemetryService::captureUpgradeSqlDuration('7.8.3', 10, true);
TelemetryService::captureUpgradeSqlDuration('7.8.3', 10, false);

$configs->setValue(null, [
    'sLanguage' => $value('en_US'),
    'sTelemetryLevel' => $value('none'),
    'sSystemID' => $value('install-id'),
]);
TelemetryService::captureInstallFailure('RuntimeException');
TelemetryService::captureUpgradeFailure('7.8.3', 'RuntimeException');
TelemetryService::captureUpgradeSqlError('7.8.3', 10, 'Database error');
TelemetryService::captureUpgradeInterruption('7.8.3', 10);

$payloads = $GLOBALS['telemetryPayloads'];
Assert::same(['page_view', 'log_error', 'install_observed', 'upgrade_completed', 'upgrade_sql_completed', 'upgrade_sql_failed', 'install_failed', 'upgrade_failed', 'upgrade_sql_failed', 'upgrade_script_interrupted'], array_column($payloads, 'event'));
foreach ($payloads as $payload) {
    Assert::true($payload['properties']['$geoip_disable']);
    Assert::false(array_key_exists('$ip', $payload['properties']));
    Assert::same(TelemetryService::POSTHOG_KEY, $payload['api_key']);
}
foreach (array_slice($payloads, 6) as $payload) {
    Assert::false($payload['properties']['$process_person_profile']);
    Assert::same('churchcrm-aggregate-failures', $payload['distinct_id']);
}
