<?php

use ChurchCRM\Service\UpgradeService;
use ChurchCRM\Shepherd\MigrationBridge;

require '/var/www/html/shepherd/vendor/autoload.php';

$root = '/var/www/html/shepherd';
$db = new PDO(
    'mysql:host=' . getenv('SHEPHERD_DB_HOST') . ';port=' . (getenv('SHEPHERD_DB_PORT') ?: '3306') . ';dbname=' . getenv('SHEPHERD_DB_NAME') . ';charset=utf8mb4',
    getenv('SHEPHERD_DB_USER'),
    getenv('SHEPHERD_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
MigrationBridge::apply($db, $root);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/shepherd/livez';
$_SERVER['SCRIPT_NAME'] = '/shepherd/api/index.php';
require $root . '/Include/LoadConfigs.php';
if (!UpgradeService::upgradeDatabaseVersion()) {
    throw new RuntimeException('ChurchCRM migration did not complete.');
}
MigrationBridge::apply($db, $root);
if ($db->query('SELECT ver_version FROM version_ver ORDER BY ver_ID DESC LIMIT 1')->fetchColumn() !== '7.8.1') {
    throw new RuntimeException('Database version does not match the tested release.');
}
printf("Database version: %s\n", $db->query('SELECT ver_version FROM version_ver ORDER BY ver_ID DESC LIMIT 1')->fetchColumn());
