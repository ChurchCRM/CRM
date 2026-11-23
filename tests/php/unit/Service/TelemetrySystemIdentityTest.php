<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\SystemIdentity;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';
(new \ReflectionProperty(TelemetryService::class, 'logDedupeDirectory'))->setValue(null, sys_get_temp_dir() . '/crm-telemetry-test-' . bin2hex(random_bytes(6)));

function curl_init(string $url): object { return new \stdClass(); }
function curl_setopt_array(object $handle, array $options): bool
{
    $GLOBALS['identityPayloads'][] = json_decode($options[CURLOPT_POSTFIELDS], true, 512, JSON_THROW_ON_ERROR);
    return true;
}
function curl_exec(object $handle): string { return 'Ok'; }
function curl_getinfo(object $handle, int $option): int { return 200; }

putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY');
putenv('PHP_TEST_DISPOSABLE_DB');
SystemURLs::init('', [], APP_ROOT);

$path = new \ReflectionProperty(SystemIdentity::class, 'path');
$usePath = static function (string $file) use ($path): void {
    @unlink($file);
    $path->setValue(null, $file);
};
$directory = sys_get_temp_dir() . '/crm-system-id-test-' . bin2hex(random_bytes(6));
mkdir($directory);

// Install: a new identity before any configuration, kept for every later read.
$usePath($directory . '/fresh.php');
$minted = SystemIdentity::ensure();
Assert::match('%h%-%h%-%h%-%h%-%h%', $minted);
Assert::same($minted, SystemIdentity::read());
Assert::same($minted, SystemIdentity::ensure('database-copy'));

// The file is PHP that only returns the ID, so a direct request prints nothing.
Assert::same($minted, require $directory . '/fresh.php');

// Upgrade: a configured install never makes a new ID before its database copy is known.
$usePath($directory . '/upgrade.php');
touch($directory . '/Config.php');
Assert::null(SystemIdentity::ensure());
Assert::false(file_exists($directory . '/upgrade.php'));

// The file adopts the existing sSystemID; a lost file is rewritten from it.
Assert::same('existing-system-id', SystemIdentity::ensure('existing-system-id'));
unlink($directory . '/upgrade.php');
Assert::same('existing-system-id', SystemIdentity::ensure('existing-system-id'));
Assert::same('existing-system-id', SystemIdentity::read());

// Restore: the file takes the restored database's ID; a dump without a valid ID keeps this server's.
$usePath($directory . '/restore.php');
SystemIdentity::ensure('new-server-id');
SystemIdentity::adopt('restored-church-id');
Assert::same('restored-church-id', SystemIdentity::read());
foreach (['', "x'; phpinfo(); '"] as $invalid) {
    SystemIdentity::adopt($invalid);
    Assert::same('restored-church-id', SystemIdentity::read());
}
$usePath($directory . '/upgrade.php');
SystemIdentity::ensure('existing-system-id');

// Database down: no configuration at all, install failures still carry the file ID.
$configs = new \ReflectionProperty(SystemConfig::class, 'configs');
$configs->setValue(null, null);
$GLOBALS['identityPayloads'] = [];
TelemetryService::captureInstallFailure('database_unreachable');
TelemetryService::captureRequirementsFailure(['cURL']);
Assert::same(['existing-system-id', 'existing-system-id'], array_column($GLOBALS['identityPayloads'], 'distinct_id'));

// Demo to production: one identity while install_profile changes; the file wins over a different database copy.
$value = static fn (string $value) => new class($value) {
    public function __construct(private string $value) {}
    public function getValue(): string { return $this->value; }
};
$GLOBALS['identityPayloads'] = [];
foreach (['demo', 'production'] as $index => $profile) {
    $configs->setValue(null, [
        'sLanguage' => $value('en_US'),
        'sTelemetryLevel' => $value('install'),
        'sInstallDataProfile' => $value($profile),
        'sSystemID' => $value('restored-database-id'),
    ]);
    TelemetryService::captureVersionMismatch('7.9.' . $index, 'database_newer');
}
Assert::same(['existing-system-id', 'existing-system-id'], array_column($GLOBALS['identityPayloads'], 'distinct_id'));
Assert::same(['demo', 'production'], array_column(array_column($GLOBALS['identityPayloads'], 'properties'), 'install_profile'));

// A folder the web server cannot write: the database copy, then the document root hash.
$usePath($directory . '/missing/SystemID.php');
Assert::same('restored-database-id', SystemIdentity::ensure('restored-database-id'));
Assert::null(SystemIdentity::ensure());
$configs->setValue(null, null);
$GLOBALS['identityPayloads'] = [];
TelemetryService::captureInstallFailure('schema_import_failed');
Assert::match('install-%h%', $GLOBALS['identityPayloads'][0]['distinct_id']);
