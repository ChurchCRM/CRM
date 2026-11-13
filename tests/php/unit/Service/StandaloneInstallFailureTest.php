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
