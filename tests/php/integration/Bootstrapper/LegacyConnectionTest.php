<?php

declare(strict_types=1);

use ChurchCRM\Bootstrapper;
use ChurchCRM\Utils\FunctionsUtils;
use Propel\Runtime\Propel;
use Tester\Assert;

require dirname(__DIR__, 2) . '/integration/bootstrap.php';

$dsn = getenv('UPGRADE_TEST_DSN');
$user = getenv('UPGRADE_TEST_USER');
$password = getenv('UPGRADE_TEST_PASSWORD');
preg_match('/host=([^;]+)/', $dsn, $host);
preg_match('/port=(\d+)/', $dsn, $port);

$admin = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$name = 'legacy_connection_' . bin2hex(random_bytes(6));
$admin->exec("CREATE DATABASE `$name`");
$connections = static fn (): int => (int) $admin
    ->query("SELECT COUNT(*) FROM information_schema.processlist WHERE db = '$name'")
    ->fetchColumn();

try {
    Bootstrapper::init($host[1], $port[1] ?? null, $user, $password, $name, '', false, []);
    Assert::same(1, $connections(), 'a request that uses only the ORM holds one connection');

    FunctionsUtils::runQuery('SELECT 1');
    Assert::same(2, $connections(), 'legacy code opens the mysqli connection when it first needs it');

    Assert::same(Bootstrapper::getMysqliConnection(), Bootstrapper::getMysqliConnection(), 'later calls reuse that connection');
    FunctionsUtils::runQuery('SELECT 1');
    Assert::same(2, $connections(), 'a second legacy query opens nothing new');
} finally {
    Propel::getServiceContainer()->closeConnections();
    $admin->exec("DROP DATABASE IF EXISTS `$name`");
}
