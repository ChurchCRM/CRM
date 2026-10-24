<?php

/**
 * scripts/test-portal-session-rotation.php — a portal sign-in never keeps an
 * un-rotated session id (member sign-in design §5).
 *
 * Usage:
 *   php scripts/test-portal-session-rotation.php
 *   npm run test:php
 *
 * PortalSessionStore::regenerateId() runs before PortalSessionStarter saves a
 * principal. When the id cannot be rotated (no session, or output already
 * sent) it must throw, so the caller saves nothing.
 *
 * It needs `composer install` in src/ but no database, web server or Config.php.
 *
 * Exit codes: 0 every case passed, 1 a case failed, 2 vendor autoloader missing.
 */

declare(strict_types=1);

use ChurchCRM\Portal\Auth\PortalSessionStore;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "src/vendor/autoload.php is missing; run composer install in src/\n");
    exit(2);
}
require $autoload;
ini_set('error_log', sys_get_temp_dir() . '/churchcrm-test-portal-session-rotation.log');

$results = [];

function rotation(): string
{
    try {
        PortalSessionStore::regenerateId();

        return 'rotated';
    } catch (RuntimeException) {
        return 'refused';
    }
}

$results[] = ['no active session is refused', rotation() === 'refused'];

session_save_path(sys_get_temp_dir());
ini_set('session.use_cookies', '0');
ob_start();
session_start();
$before = session_id();
$outcome = rotation();
ob_end_flush();
$results[] = ['an active session before output is rotated', $outcome === 'rotated' && session_id() !== $before];

echo "\n";
$before = session_id();
$outcome = rotation();
$results[] = ['headers already sent is refused and keeps the id', $outcome === 'refused' && session_id() === $before];
session_destroy();

$failures = 0;
foreach ($results as $i => [$name, $ok]) {
    if (!$ok) {
        $failures++;
    }
    printf("%s %d - %s\n", $ok ? 'ok' : 'not ok', $i + 1, $name);
}

exit($failures === 0 ? 0 : 1);
