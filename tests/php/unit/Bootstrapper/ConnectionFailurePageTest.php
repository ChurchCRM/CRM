<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

// Nothing listens on port 1, so the first connection fails and the process prints the failure page and exits.
putenv('CRM_AUTOLOAD=' . APP_ROOT . '/vendor/autoload.php');
$script = <<<'PHP'
    require getenv('CRM_AUTOLOAD');
    ChurchCRM\Bootstrapper::init('127.0.0.1', 1, '<b>admin</b>', 'secret', 'churchcrm', '', false, []);
    PHP;
$page = (string) shell_exec(sprintf(
    '%s -d error_log=%s -r %s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg((string) tempnam(sys_get_temp_dir(), 'crm-connection-failure-')),
    escapeshellarg($script)
));

Assert::contains('Database Connection Failure', $page);
Assert::contains('as <strong>&lt;b&gt;admin&lt;/b&gt;</strong>', $page, 'the database user is shown as text');
Assert::notContains('<b>admin</b>', $page, 'markup from the settings is never written into the page');
