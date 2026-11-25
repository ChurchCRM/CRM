<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';
require APP_ROOT . '/errors/template.php';

Assert::same(\POSTHOG_PROJECT_KEY, TelemetryService::POSTHOG_KEY);
Assert::same(\POSTHOG_PROJECT_HOST, TelemetryService::POSTHOG_ENDPOINT);

putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY=1');
Assert::false(\standaloneReportInstallFailure('config_invalid'));
putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY');

// The reporter also stays silent for the shared development system ID: plant the
// ID file it reads, assert, then put back whatever was there before.
$systemIdFile = APP_ROOT . '/Include/SystemID.php';
$previousSystemId = is_file($systemIdFile) ? file_get_contents($systemIdFile) : null;
file_put_contents($systemIdFile, "<?php\n\nreturn 'dev-churchcrm-testing';\n");
Assert::false(\standaloneReportInstallFailure('config_invalid'));
if ($previousSystemId === null) {
    unlink($systemIdFile);
} else {
    file_put_contents($systemIdFile, $previousSystemId);
}

$_SERVER['HTTP_HOST'] = 'church.example.org';
$body = \buildGitHubIssueBody('Configuration Error', 'Missing ' . APP_ROOT . '/Include/Config.php at https://church.example.org/x on 10.0.0.5');
foreach ([APP_ROOT . '/', 'church.example.org', '10.0.0.5', php_uname('n') . ' '] as $private) {
    Assert::notContains($private, $body);
}
Assert::contains('[path]/Include/Config.php', $body);
