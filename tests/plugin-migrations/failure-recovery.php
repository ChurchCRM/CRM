<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Config;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\model\ChurchCRM\Map\ConfigTableMap;
use ChurchCRM\model\ChurchCRM\Map\PluginMigrationTableMap;
use ChurchCRM\Plugin\PluginInstaller;
use ChurchCRM\Plugin\ApprovedPluginRegistry;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Plugin\PluginMigrationManager;
use ChurchCRM\Plugin\PluginMigrationManifest;
use ChurchCRM\Plugin\PluginMetadata;
use ChurchCRM\Plugins\MigrationExample\MigrationExamplePlugin;
use ChurchCRM\Plugins\MigrationExample\Model\EntryQuery;
use ChurchCRM\Plugins\MigrationExample\Model\Map\EntryTableMap;
use ChurchCRM\Utils\SQLUtils;
use Ifsnop\Mysqldump\Mysqldump;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/** Wait for a real worker at a crash boundary, assert durable state, then kill. */
function crashAt(string $phase, callable $atBarrier): void
{
    global $workspace, $connection;
    $process = proc_open([PHP_BINARY, __DIR__ . '/worker.php', $workspace, $phase],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'Could not start crash worker');
    try {
        stream_set_timeout($pipes[1], 20);
        check(trim((string) fgets($pipes[1])) === 'barrier=' . $phase, 'Worker did not reach ' . $phase);
        check(proc_get_status($process)['running'], 'Worker exited before crash');
        $atBarrier();
    } finally {
        // SIGKILL on Linux, TerminateProcess on Windows: no PHP finally/shutdown.
        $terminated = proc_terminate($process, 9);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        $exit = proc_close($process);
        check($terminated && $exit !== 0, 'Crash worker was not terminated');
    }
    // Connection teardown releases GET_LOCK asynchronously at the server.
    $lock = 'ccrm:pm:' . substr(hash('sha256', 'churchcrm_plugin_migrations_test:migration-example'), 0, 55);
    $wait = $connection->prepare('SELECT GET_LOCK(?, 10)');
    $wait->execute([$lock]);
    check((int) $wait->fetchColumn() === 1, 'Dead worker retained migration lock');
    $release = $connection->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$lock]);
}

$tests['normal core SQL importer creates the ledger on a full clean install and upgrade'] = static function () use ($connection, $root): void {
    check($connection->query('SELECT DATABASE()')->fetchColumn() === 'churchcrm_plugin_migrations_test', 'Unsafe install target');
    SQLUtils::dropAllTables($connection);
    // Match Bootstrapper's production session mode when importing core schema.
    $connection->exec("SET sql_mode=(SELECT REPLACE(REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''),'NO_ZERO_DATE',''))");
    SQLUtils::sqlImport($root . '/src/mysql/install/Install.sql', $connection);
    $installed = $connection->query('SHOW CREATE TABLE plugin_migration_pmg')->fetch(PDO::FETCH_NUM)[1];
    $connection->exec('DROP TABLE plugin_migration_pmg');
    $upgrade = json_decode(file_get_contents($root . '/src/mysql/upgrade.json'), true, 32, JSON_THROW_ON_ERROR);
    check(in_array('/mysql/upgrade/7.7.0-plugin-migrations.sql', $upgrade['current']['scripts'], true), 'Core upgrade does not schedule ledger creation');
    SQLUtils::sqlImport($root . '/src/mysql/upgrade/7.7.0-plugin-migrations.sql', $connection);
    check($connection->query('SHOW CREATE TABLE plugin_migration_pmg')->fetch(PDO::FETCH_NUM)[1] === $installed, 'Install/upgrade ledger drift');
};

foreach (['before-ddl', 'after-ddl', 'after-success'] as $phase) {
    $tests['process death at ' . $phase . ' preserves a durable, non-replayed state'] = static function () use ($phase, $connection, $plugins): void {
        fresh();
        crashAt($phase, static function () use ($phase, $connection, $plugins): void {
            $row = $connection->query('SELECT * FROM plugin_migration_pmg')->fetch(PDO::FETCH_ASSOC);
            check($row !== false && $row['pmg_StartedAt'] !== null, 'Attempt was not committed before DDL');
            check(($row['pmg_AppliedAt'] !== null) === ($phase === 'after-success'), 'Wrong durable success state');
            $exists = (int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugin_migration_example__entries'")->fetchColumn() === 1;
            check($exists === ($phase !== 'before-ddl'), 'Wrong schema state at crash boundary');
            check(!PluginManager::isPluginActive('migration-example'), 'Activated before migration returned');
            rejects(fn () => PluginManager::enablePlugin('migration-example'), 'Another request');
            rejects(fn () => PluginManager::disablePlugin('migration-example'), 'Another request');
            rejects(fn () => PluginInstaller::uninstall($plugins, 'migration-example'), 'Another request');
            check(PluginMigrationManager::withLock('different-plugin', fn () => true), 'Lock leaked to another plugin');
            // Same plugin on a different database has a separate lock. Read-only
            // system schema is sufficient; no second writable test DB is needed.
            $connection->exec('USE information_schema');
            try {
                check(PluginMigrationManager::withLock('migration-example', fn () => true), 'Lock leaked to another installation');
            } finally {
                $connection->exec('USE churchcrm_plugin_migrations_test');
            }
        });
        $before = $connection->query('SELECT * FROM plugin_migration_pmg')->fetchAll(PDO::FETCH_ASSOC);
        restart();
        if ($phase === 'after-success') {
            check(PluginManager::enablePlugin('migration-example'), 'Completed migration could not activate after crash');
        } else {
            rejects(fn () => PluginManager::enablePlugin('migration-example'), 'interrupted or failed');
            check(PluginManager::getPlugin('migration-example') === null, 'Uncertain schema plugin booted');
        }
        check($connection->query('SELECT * FROM plugin_migration_pmg')->fetchAll(PDO::FETCH_ASSOC) === $before, 'Restart/re-enable changed attempt');
    };
}

$tests['uninstall detaches a loaded migration plugin and its scheduled callbacks'] = static function () use ($plugins): void {
    HookManager::reset();
    storedFixture();
    $jobs = MigrationExamplePlugin::$jobs;
    HookManager::doAction(Hooks::CRON_RUN);
    check(MigrationExamplePlugin::$jobs === $jobs + 1, 'Fixture cron callback missing');
    check(HookManager::applyFilters('migration-example.filter', 'value') === 'value:fixture', 'Fixture filter missing');
    PluginInstaller::uninstall($plugins, 'migration-example');
    $jobs = MigrationExamplePlugin::$jobs;
    HookManager::doAction(Hooks::CRON_RUN);
    check(MigrationExamplePlugin::$jobs === $jobs, 'Uninstalled plugin scheduled callback still executes');
    check(HookManager::applyFilters('migration-example.filter', 'value') === 'value', 'Uninstalled plugin filter still executes');
    check(PluginManager::getPlugin('migration-example') === null, 'Uninstalled plugin remains loaded');
};

$tests['crash between ordered migration resources resumes only the never-started resource'] = static function () use ($connection): void {
    fresh();
    upgradeFixture();
    crashAt('after-success', static function () use ($connection): void {
        check((int) $connection->query('SELECT COUNT(*) FROM plugin_migration_pmg')->fetchColumn() === 1, 'Second resource started before barrier');
    });
    $first = $connection->query('SELECT * FROM plugin_migration_pmg')->fetch(PDO::FETCH_ASSOC);
    restart();
    check(PluginManager::enablePlugin('migration-example'), 'Could not resume never-started migration');
    check((int) $connection->query('SELECT COUNT(*) FROM plugin_migration_pmg')->fetchColumn() === 2, 'Second resource not applied');
    check($connection->query('SELECT * FROM plugin_migration_pmg ORDER BY pmg_MigrationId LIMIT 1')->fetch(PDO::FETCH_ASSOC) === $first, 'First resource replayed');
};

$tests['disable, quarantine and reset remove migration callbacks without duplicates'] = static function (): void {
    storedFixture();
    PluginManager::disablePlugin('migration-example');
    $jobs = MigrationExamplePlugin::$jobs;
    HookManager::doAction(Hooks::CRON_RUN);
    check(MigrationExamplePlugin::$jobs === $jobs, 'Disabled cron callback executed');
    PluginManager::enablePlugin('migration-example');
    restart();
    HookManager::doAction(Hooks::CRON_RUN);
    check(MigrationExamplePlugin::$jobs === $jobs + 1, 'Restart duplicated callbacks');
    PluginManager::quarantinePlugin('migration-example', 'Fixture quarantine');
    HookManager::doAction(Hooks::CRON_RUN);
    check(MigrationExamplePlugin::$jobs === $jobs + 1, 'Quarantined cron callback executed');
};

$tests['pending or failed migrations withhold plugin routes while core routes work'] = static function (): void {
    storedFixture();
    $routes = static function (): array {
        $app = AppFactory::create();
        $app->get('/core-health', static function ($request, $response) {
            $response->getBody()->write('core');
            return $response;
        });
        PluginManager::registerPluginRoutes($app);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/core-health');
        check((string) $app->handle($request)->getBody() === 'core', 'Core route unavailable');
        return array_map(static fn ($route): string => $route->getPattern(), $app->getRouteCollector()->getRoutes());
    };
    check(in_array('/migration-example/entries', $routes(), true), 'Ready plugin route absent');
    upgradeFixture();
    check(!in_array('/migration-example/entries', $routes(), true), 'Pending plugin registered routes');
    crashAt('after-ddl', static fn () => null);
    restart();
    check(!in_array('/migration-example/entries', $routes(), true), 'Failed plugin registered routes');
};

$tests['failed approval on an already loaded plugin detaches its operational state'] = static function (): void {
    storedFixture();
    SystemConfig::setValue('plugin.migration-example.unverified', '1');
    rejects(fn () => PluginManager::enablePlugin('migration-example'), 'verified installation');
    check(PluginManager::getPlugin('migration-example') === null, 'Failed approval left loaded instance operational');
    check(!PluginManager::isPluginActive('migration-example'), 'Failed approval still reports active');
    $jobs = MigrationExamplePlugin::$jobs;
    HookManager::doAction(Hooks::CRON_RUN);
    check(MigrationExamplePlugin::$jobs === $jobs, 'Failed approval left scheduled callback');
};

$tests['removing the declaration cannot evade data retention during uninstall'] = static function () use ($pluginPath, $plugins, $connection): void {
    storedFixture();
    $data = json_decode(file_get_contents($pluginPath . '/plugin.json'), true);
    unset($data['migrations']);
    jsonFile($pluginPath . '/plugin.json', $data);
    // Rediscover metadata while keeping the previously loaded destructive
    // callback available, proving the history-based uninstall branch protects it.
    PluginManager::discoverPlugins();
    $uninstalls = MigrationExamplePlugin::$uninstalls;
    $deactivations = MigrationExamplePlugin::$deactivations;
    PluginInstaller::uninstall($plugins, 'migration-example');
    check(MigrationExamplePlugin::$uninstalls === $uninstalls && MigrationExamplePlugin::$deactivations === $deactivations, 'Removed declaration evaded callback protection');
    check(EntryQuery::create()->count() === 1 && (int) $connection->query('SELECT COUNT(*) FROM plugin_migration_pmg')->fetchColumn() === 1, 'Removed declaration lost data/history');
};

$tests['migrating IDs cannot make overlapping table prefixes'] = static function () use ($pluginPath): void {
    fresh();
    $data = json_decode(file_get_contents($pluginPath . '/plugin.json'), true);
    foreach (['a--b', 'a-', 'a--'] as $id) {
        $data['id'] = $id;
        file_put_contents($pluginPath . '/migrations/0001_create_entries.sql',
            'CREATE TABLE plugin_' . str_replace('-', '_', $id) . '__entries (id INT) ENGINE=InnoDB;');
        rejects(fn () => PluginMigrationManifest::read(new PluginMetadata($data, $pluginPath)), 'valid ID');
    }
};

$tests['community discovery cannot replace a core plugin with the same ID'] = static function () use ($plugins, $pluginPath): void {
    fresh();
    $corePath = $plugins . '/core/migration-example';
    mkdir($corePath, 0700, true);
    $data = json_decode(file_get_contents($pluginPath . '/plugin.json'), true);
    $data['type'] = 'core';
    unset($data['migrations']);
    jsonFile($corePath . '/plugin.json', $data);
    try {
        restart();
        check(PluginManager::getPluginMetadata('migration-example')->getType() === 'core', 'Community replaced reserved core identity');
    } finally {
        unlink($corePath . '/plugin.json');
        rmdir($corePath);
    }
};

$tests['plugins without migrations retain their legacy uninstall callbacks'] = static function () use ($connection, $pluginPath, $plugins): void {
    fresh();
    // A legacy plugin's pre-existing table is test setup, not runner-created DDL.
    $connection->exec(file_get_contents($pluginPath . '/migrations/0001_create_entries.sql'));
    $data = json_decode(file_get_contents($pluginPath . '/plugin.json'), true);
    unset($data['migrations']);
    jsonFile($pluginPath . '/plugin.json', $data);
    approve();
    restart();
    PluginManager::enablePlugin('migration-example');
    $uninstalls = MigrationExamplePlugin::$uninstalls;
    $deactivations = MigrationExamplePlugin::$deactivations;
    PluginInstaller::uninstall($plugins, 'migration-example');
    check(MigrationExamplePlugin::$uninstalls === $uninstalls + 1, 'Legacy uninstall callback changed');
    check(MigrationExamplePlugin::$deactivations === $deactivations + 1, 'Legacy deactivate callback changed');
    check((int) $connection->query('SELECT COUNT(*) FROM plugin_migration_pmg')->fetchColumn() === 0, 'Legacy plugin wrote migration history');
    HookManager::reset();
};

$tests['coordinated full backup restore recovers plugin and unrelated core records after a crash'] = static function () use ($connection, $workspace, $pluginPath): void {
    storedFixture();
    (new Config())->setName('sChurchName')->setValue('Core record before backup')->save();
    $backup = $workspace . '/pre-migration.sql';
    // Same locked production dependency used by ChurchCRM backup infrastructure.
    // No include/exclude list: snapshot every table in this disposable database.
    (new Mysqldump(getenv('PLUGIN_MIGRATION_TEST_DSN'), getenv('PLUGIN_MIGRATION_TEST_USER') ?: 'root',
        getenv('PLUGIN_MIGRATION_TEST_PASSWORD') ?: '', ['add-drop-table' => true, 'skip-comments' => true]))->start($backup);
    $originalManifest = file_get_contents($pluginPath . '/plugin.json');
    $originalMigrations = file_get_contents($pluginPath . '/migrations/migrations.json');
    $originalRegistry = $_SESSION['RemotePluginRegistry'];
    $originalProvenance = SystemConfig::getValue('plugin.migration-example.provenance');
    upgradeFixture();
    crashAt('after-ddl', static function () use ($connection): void {
        check($connection->query("SHOW COLUMNS FROM plugin_migration_example__entries LIKE 'note'")->fetch() !== false, 'Upgrade DDL did not finish');
    });
    rejects(fn () => PluginManager::enablePlugin('migration-example'), 'interrupted or failed');
    // Demonstrate why ALL writers must remain quiescent: newer unrelated core
    // writes would also be lost by this whole-database restore.
    ConfigQuery::create()->findPk('sChurchName')->setValue('Newer core write that restore would lose')->save();
    $connection->exec("UPDATE plugin_migration_example__entries SET label='Newer plugin write'");
    $connection->exec('CREATE TABLE plugin_migration_example__recovery_extra (id INT) ENGINE=InnoDB');
    check($connection->query('SELECT DATABASE()')->fetchColumn() === 'churchcrm_plugin_migrations_test', 'Unsafe restore target');
    // Restore into an empty schema: the dump cannot drop post-backup tables.
    SQLUtils::dropAllTables($connection);
    SQLUtils::sqlImport($backup, $connection);
    // Restore matching package metadata as part of the same recovery unit.
    file_put_contents($pluginPath . '/plugin.json', $originalManifest);
    file_put_contents($pluginPath . '/migrations/migrations.json', $originalMigrations);
    ConfigTableMap::clearInstancePool();
    PluginMigrationTableMap::clearInstancePool();
    EntryTableMap::clearInstancePool();
    $_SESSION['RemotePluginRegistry'] = $originalRegistry;
    jsonFile($workspace . '/registry.json', $originalRegistry);
    ApprovedPluginRegistry::reset();
    check(SystemConfig::getValue('plugin.migration-example.provenance') === $originalProvenance, 'Package provenance not restored');
    restart();
    check(ConfigQuery::create()->findPk('sChurchName')->getValue() === 'Core record before backup', 'Core record not restored');
    check(EntryQuery::create()->findOne()->getLabel() === 'Retained fixture', 'Plugin record not restored');
    check((int) $connection->query('SELECT COUNT(*) FROM plugin_migration_pmg')->fetchColumn() === 1, 'Attempt history not restored');
    check($connection->query("SHOW COLUMNS FROM plugin_migration_example__entries LIKE 'note'")->fetch() === false, 'Post-backup schema survived restore');
    check((int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugin_migration_example__recovery_extra'")->fetchColumn() === 0, 'Post-backup table survived restore');
    check(PluginManager::getPlugin('migration-example') !== null, 'Matching restored package failed to boot');
    upgradeFixture();
    check(PluginManager::enablePlugin('migration-example'), 'Approved upgrade after coordinated restore failed');
    check((int) $connection->query('SELECT COUNT(*) FROM plugin_migration_pmg WHERE pmg_AppliedAt IS NOT NULL')->fetchColumn() === 2, 'Recovered upgrade not applied');
};
