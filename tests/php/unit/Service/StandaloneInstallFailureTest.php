<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';
require APP_ROOT . '/errors/template.php';

Assert::same(TelemetryService::POSTHOG_KEY, STANDALONE_POSTHOG_KEY);
Assert::same(TelemetryService::POSTHOG_CAPTURE_ENDPOINT . '?ip=0', STANDALONE_POSTHOG_URL);

putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY=1');
Assert::false(\standaloneReportInstallFailure('config_invalid'));
putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY');
