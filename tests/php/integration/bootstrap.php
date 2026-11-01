<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (getenv('PHP_TEST_ISOLATED') !== '1' || realpath(APP_ROOT) === realpath(dirname(__DIR__, 3) . '/src')) {
    throw new RuntimeException('Run integration tests through npm run test:php:integration for an isolated application copy.');
}
if (getenv('PHP_TEST_DISPOSABLE_DB') !== '1') {
    throw new RuntimeException('Set PHP_TEST_DISPOSABLE_DB=1 for a disposable database server.');
}
$dsn = getenv('UPGRADE_TEST_DSN');
if (!$dsn || !str_starts_with($dsn, 'mysql:') || !preg_match('/(?:^mysql:|;)host=[^;]+/', $dsn) || preg_match('/(?:^|;)\s*dbname\s*=/i', $dsn)) {
    throw new RuntimeException('Set UPGRADE_TEST_DSN to mysql:host=...;port=... without a database name.');
}
if (!getenv('UPGRADE_TEST_USER') || !getenv('UPGRADE_TEST_PASSWORD')) {
    throw new RuntimeException('Set UPGRADE_TEST_USER and a nonempty UPGRADE_TEST_PASSWORD.');
}
