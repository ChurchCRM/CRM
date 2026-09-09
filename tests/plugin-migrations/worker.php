<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PluginMigrationQuery;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Plugin\PluginMigrationManager;

$mode = $argv[2] ?? 'restart';
if (in_array($mode, ['before-ddl', 'after-ddl', 'after-success'], true)) {
    define('MIGRATION_TEST_FAULT_PHASE', $mode);
}
require __DIR__ . '/bootstrap.php';

$workspace = $argv[1] ?? '';
if (!preg_match('/^churchcrm-migrations-[a-f0-9]{16}$/D', basename($workspace)) || !is_dir($workspace)) {
    throw new RuntimeException('Expected the isolated migration test workspace.');
}
SystemURLs::init('', ['http://localhost'], $workspace);
$_SESSION['RemotePluginRegistry'] = json_decode(file_get_contents($workspace . '/registry.json'), true, 32, JSON_THROW_ON_ERROR);

if (defined('MIGRATION_TEST_FAULT_PHASE')) {
    PluginManager::init($workspace . '/plugins');
    PluginManager::enablePlugin('migration-example');
    throw new RuntimeException('Fault boundary was not reached.');
} elseif ($mode === 'lock') {
    PluginMigrationManager::withLock('migration-example', static function (): void {
        echo "locked\n";
        fflush(STDOUT);
        fgets(STDIN);
    });
} else {
    PluginManager::init($workspace . '/plugins');
    if (PluginManager::getPlugin('migration-example') === null) {
        throw new RuntimeException('A fresh PHP process did not boot the ready fixture.');
    }
    echo 'applied=' . PluginMigrationQuery::create()->count() . "\n";
}
