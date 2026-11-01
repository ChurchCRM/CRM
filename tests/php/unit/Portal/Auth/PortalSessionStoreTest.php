<?php

declare(strict_types=1);

use ChurchCRM\Portal\Auth\PortalSessionStore;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

ini_set('error_log', sys_get_temp_dir() . '/churchcrm-test-portal-session-rotation.log');

Assert::exception(static fn () => PortalSessionStore::regenerateId(), RuntimeException::class);

session_save_path(sys_get_temp_dir());
ini_set('session.use_cookies', '0');
ob_start();
session_start();
$before = session_id();
PortalSessionStore::regenerateId();
$after = session_id();
ob_end_flush();
Assert::notSame($before, $after, 'an active session before output is rotated');

echo "\n";
Assert::exception(static fn () => PortalSessionStore::regenerateId(), RuntimeException::class);
Assert::same($after, session_id(), 'headers already sent keeps the id');
session_destroy();
