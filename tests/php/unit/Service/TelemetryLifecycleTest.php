<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Logging\PostHogLogHandler;
use ChurchCRM\Utils\VersionUtils;
use Monolog\Level;
use Monolog\LogRecord;
use Propel\Runtime\Connection\PdoConnection;
use Propel\Runtime\Propel;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

function curl_init(string $url): object { return new \stdClass(); }
function curl_setopt_array(object $handle, array $options): bool
{
    $GLOBALS['lifecyclePayloads'][] = json_decode($options[CURLOPT_POSTFIELDS], true, 512, JSON_THROW_ON_ERROR);
    return true;
}
function curl_exec(object $handle): string { return 'Ok'; }
function curl_getinfo(object $handle, int $option): int { return 200; }

putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY');
putenv('PHP_TEST_DISPOSABLE_DB');
SystemURLs::init('', [], APP_ROOT);
SystemConfig::init();
Assert::same('install', TelemetryService::getLevel());
Assert::same(['none', 'install', 'errors', 'full'], array_map(
    static fn (string $choice): string => substr($choice, strrpos($choice, ':') + 1),
    SystemConfig::getTelemetryLevelChoices()['Choices']
));

$connection = new class extends PdoConnection {
    public function __construct() {}
    public function getAttribute(int $attribute): string { return '5.5.5-10.11.12-MariaDB-private-host'; }
    public function prepare(string $statement, array $driverOptions = [])
    {
        throw new \RuntimeException('Database unavailable');
    }
};
$container = Propel::getServiceContainer();
$container->setDefaultDatasource('telemetry');
$container->setAdapterClass('telemetry', 'mysql');
$container->setConnection('telemetry', $connection);
Assert::same('5.5.5-10.11.12-MariaDB-private-host', Propel::getConnection()->getAttribute(\PDO::ATTR_SERVER_VERSION));

Assert::same(['database_engine' => 'mariadb', 'database_version' => '10.11'], TelemetryService::databaseProperties('5.5.5-10.11.12-MariaDB-private-host'));
Assert::same(['database_engine' => 'mysql', 'database_version' => '8.4'], TelemetryService::databaseProperties('8.4.5-private-host'));
Assert::same([], TelemetryService::databaseProperties('private-host'));

$value = static fn (string $value) => new class($value) {
    public function __construct(private string $value) {}
    public function getValue(): string { return $this->value; }
    public function setValue(string $value): void { $this->value = $value; }
    public function getType(): string { return 'internal'; }
};
$configs = new \ReflectionProperty(SystemConfig::class, 'configs');
$capture = new \ReflectionMethod(TelemetryService::class, 'capture');
$handler = new PostHogLogHandler();
foreach (['production', 'demo', 'test'] as $profile) {
    foreach (['none', 'install', 'errors', 'warnings', 'full'] as $level) {
        $configs->setValue(null, [
            'sTelemetryLevel' => $value($level),
            'sInstallDataProfile' => $value($profile),
            'sSystemID' => $value('church-guid'),
            'sTelemetryLifecycleVersion' => $value('7.8.2'),
            'sChurchCountry' => $value('US'),
            'sLanguage' => $value('en_US'),
        ]);
        $GLOBALS['lifecyclePayloads'] = [];
        $normalized = $level === 'warnings' ? 'errors' : $level;
        Assert::same($normalized, TelemetryService::getLevel());
        foreach (['', '7.8.2'] as $covered) {
            $event = TelemetryService::buildLifecycleEvent($normalized, 'church-guid', $covered, '7.8.4');
            if ($event !== null) {
                $capture->invoke(null, $event['event'], $event['properties']);
            }
        }
        TelemetryService::captureUpgradeFailure('7.8.4', 'PrivateException');
        $expected = $profile === 'test' ? 0 : ($level === 'none' ? 1 : 3);
        Assert::count($expected, $GLOBALS['lifecyclePayloads']);
        foreach ($GLOBALS['lifecyclePayloads'] as $payload) {
            Assert::same('church-guid', $payload['distinct_id']);
            $properties = $payload['properties'];
            $keys = ['crm_version', 'install_profile', 'church_country', 'database_engine', 'database_version'];
            if ($payload['event'] !== 'install_observed') {
                $keys[] = 'from_version';
                Assert::same('7.8.2', $properties['from_version']);
            }
            if ($payload['event'] === 'upgrade_failed') {
                $keys[] = 'failure_reason';
                $keys[] = 'upgrade_version';
                Assert::same('other', $properties['failure_reason']);
                Assert::same('7.8.4', $properties['upgrade_version']);
                Assert::false(array_key_exists('error_detail', $properties));
                Assert::false(array_key_exists('upgrade_script', $properties));
            }
            $keys = array_merge($keys, ['$process_person_profile', '$geoip_disable']);
            Assert::same($keys, array_keys($properties));
            Assert::true($properties['$geoip_disable']);
            Assert::false($properties['$process_person_profile']);
            Assert::same('US', $properties['church_country']);
            Assert::same($profile, $properties['install_profile']);
            Assert::same($payload['event'] === 'upgrade_failed' ? VersionUtils::getInstalledVersion() : '7.8.4', $properties['crm_version']);
            Assert::same('mariadb', $properties['database_engine']);
            Assert::same('10.11', $properties['database_version']);
        }

        $GLOBALS['lifecyclePayloads'] = [];
        (new \ReflectionProperty(\ChurchCRM\Service\TelemetryService::class, 'logDedupeDirectory'))->setValue(null, sys_get_temp_dir() . '/crm-telemetry-test-' . bin2hex(random_bytes(6)));
        TelemetryService::capturePageView('/people/person');
        $handler->handle(new LogRecord(new \DateTimeImmutable(), 'app', Level::Warning, 'Warning'));
        $handler->handle(new LogRecord(new \DateTimeImmutable(), 'app', Level::Error, 'Error'));
        TelemetryService::captureLogEvent(new LogRecord(new \DateTimeImmutable(), 'app', Level::Error, 'Direct error'));
        $events = $profile === 'test' || in_array($level, ['none', 'install'], true)
            ? [] : ($level === 'full' ? ['page_view', 'log_error', 'log_error', 'log_error'] : ['log_error', 'log_error', 'log_error']);
        Assert::same($events, array_column($GLOBALS['lifecyclePayloads'], 'event'));
        TelemetryService::captureLogEvent(new LogRecord(new \DateTimeImmutable(), 'app', Level::Error, 'Direct error'));
        Assert::same($events, array_column($GLOBALS['lifecyclePayloads'], 'event'));
    }
}

$mutable = static fn (string $v) => new class($v) {
    public function __construct(private string $v) {}
    public function getValue(): string { return $this->v; }
    public function setValue(string $v): void { $this->v = $v; }
    public function getType(): string { return 'internal'; }
};
$configs->setValue(null, [
    'sTelemetryLevel' => $mutable('install'),
    'sInstallDataProfile' => $mutable('production'),
    'sSystemID' => $mutable('church-guid'),
    'sTelemetryLifecycleVersion' => $mutable('7.8.2'),
    'sTelemetryUpgradeFailureKey' => $mutable(''),
]);
$GLOBALS['lifecyclePayloads'] = [];
TelemetryService::captureUpgradeFailure('7.8.4', 'no_upgrade_path');
TelemetryService::captureUpgradeFailure('7.8.4', 'no_upgrade_path');
TelemetryService::captureUpgradeFailure('7.8.4', 'Some free text from an exception');
Assert::same(['no_upgrade_path', 'other'], array_column(array_column($GLOBALS['lifecyclePayloads'], 'properties'), 'failure_reason'));

$GLOBALS['lifecyclePayloads'] = [];
TelemetryService::captureUpgradeSqlError('7.8.4', 10, "Duplicate entry 'secret@example.org' for key 'person.email'");
TelemetryService::captureUpgradeInterruption('7.8.4', 10);
Assert::same(['upgrade_failed', 'upgrade_failed'], array_column($GLOBALS['lifecyclePayloads'], 'event'));
Assert::same(['sql_error', 'script_interrupted'], array_column(array_column($GLOBALS['lifecyclePayloads'], 'properties'), 'failure_reason'));
Assert::same('7.8.4', $GLOBALS['lifecyclePayloads'][0]['properties']['upgrade_version']);
Assert::false(array_key_exists('error_detail', $GLOBALS['lifecyclePayloads'][0]['properties']));
Assert::false(array_key_exists('upgrade_script', $GLOBALS['lifecyclePayloads'][0]['properties']));
Assert::false(array_key_exists('error_detail', $GLOBALS['lifecyclePayloads'][1]['properties']));

$GLOBALS['lifecyclePayloads'] = [];
TelemetryService::captureInstallFailure('schema_import_failed');
TelemetryService::captureInstallFailure('SQLSTATE[42000] church data');
Assert::same(['install_failed', 'install_failed'], array_column($GLOBALS['lifecyclePayloads'], 'event'));
Assert::same(['schema_import_failed', 'other'], array_column(array_column($GLOBALS['lifecyclePayloads'], 'properties'), 'failure_reason'));
Assert::match('install-%h%', $GLOBALS['lifecyclePayloads'][0]['distinct_id']);
Assert::same($GLOBALS['lifecyclePayloads'][0]['distinct_id'], $GLOBALS['lifecyclePayloads'][1]['distinct_id']);
Assert::false(array_key_exists('database_error', $GLOBALS['lifecyclePayloads'][0]['properties']));

$configs->setValue(null, ['sTelemetryLevel' => $value('none'), 'sInstallDataProfile' => $value('production')]);
$GLOBALS['lifecyclePayloads'] = [];
TelemetryService::captureInstallFailure('schema_import_failed');
Assert::match('install-%h%', $GLOBALS['lifecyclePayloads'][0]['distinct_id']);

$_SERVER['HTTP_HOST'] = 'stmarys.example.org:8080';
Assert::same(
    'Failed [url] on [host] in [path]/Include/x.php from [ip]',
    TelemetryService::scrubHostDetails('Failed https://stmarys.example.org/cms/v2?id=5 on stmarys.example.org in ' . SystemURLs::getDocumentRoot() . '/Include/x.php from 10.1.2.3'),
);
unset($_SERVER['HTTP_HOST']);

$configs->setValue(null, [
    'sTelemetryLevel' => $value('install'),
    'sTelemetryAskedVersion' => $value(''),
    'sInstallDataProfile' => $value('test'),
]);
$app = \Slim\Factory\AppFactory::create();
require APP_ROOT . '/admin/routes/api/system/telemetry-consent.php';
foreach (['install', 'errors', 'full', 'none'] as $choice) {
    $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('POST', '/api/system/telemetry-consent');
    $request->getBody()->write(json_encode(['level' => $choice], JSON_THROW_ON_ERROR));
    $response = $app->handle($request);
    Assert::same(200, $response->getStatusCode());
    Assert::same($choice, SystemConfig::getValue('sTelemetryLevel'));
    Assert::same(VersionUtils::getInstalledVersion(), SystemConfig::getValue('sTelemetryAskedVersion'));
}
$sent = 0;
TelemetryService::syncLifecycle(static function () use (&$sent): bool { $sent++; return true; });
Assert::same(0, $sent);

putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY=1');
$demoConfig = json_decode(file_get_contents(APP_ROOT . '/admin/demo/config.json'), true, 512, JSON_THROW_ON_ERROR);
$demoSettings = [];
foreach (array_unique(array_merge(array_keys($demoConfig), ['sInstallDataProfile', 'sTelemetryLevel', 'bEnabledSundaySchool', 'bEnabledFinance', 'bEnabledEvents', 'bPortalShowVolunteer', 'sLogLevel'])) as $key) {
    $demoSettings[$key] = $value('');
}
$configs->setValue(null, $demoSettings);
$demoImport = new \ReflectionMethod(DemoDataService::class, 'importSystemConfig');
$demoService = new DemoDataService();
$demoImport->invoke($demoService);
Assert::same('', SystemConfig::getValue('sTelemetryLevel'));
SystemConfig::setValue('sInstallDataProfile', 'demo');
Assert::same('test', TelemetryService::installProfile());
putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY');
Assert::same('demo', TelemetryService::installProfile());
