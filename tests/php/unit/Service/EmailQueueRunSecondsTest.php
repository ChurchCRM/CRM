<?php

declare(strict_types=1);

use ChurchCRM\Service\EmailQueueService;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$cases = [
    'no PHP limit: the command-line limit'            => [0, null, EmailQueueService::CLI_MAX_SECONDS],
    'no PHP limit: the requested limit'               => [0, EmailQueueService::WEB_MAX_SECONDS, EmailQueueService::WEB_MAX_SECONDS],
    'half of a long PHP limit is longer'              => [600, EmailQueueService::WEB_MAX_SECONDS, EmailQueueService::WEB_MAX_SECONDS],
    'half of a short PHP limit is shorter'            => [20, EmailQueueService::WEB_MAX_SECONDS, 10],
    'half of the PHP limit caps the command line too' => [120, null, 60],
    'a one-second PHP limit still allows one second'  => [1, EmailQueueService::WEB_MAX_SECONDS, 1],
];

foreach ($cases as $label => [$phpLimit, $maxSeconds, $expected]) {
    ini_set('max_execution_time', (string) $phpLimit);
    Assert::same($expected, EmailQueueService::runSeconds($maxSeconds), $label);
}
