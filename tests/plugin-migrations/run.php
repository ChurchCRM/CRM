<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Map\ConfigTableMap;
use ChurchCRM\model\ChurchCRM\Map\PluginMigrationTableMap;
use ChurchCRM\model\ChurchCRM\PluginMigrationQuery;
use ChurchCRM\Plugin\ApprovedPluginRegistry;
use ChurchCRM\Plugin\PluginInstaller;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Plugin\PluginMetadata;
use ChurchCRM\Plugin\PluginMigrationException;
use ChurchCRM\Plugin\PluginMigrationManager;
use ChurchCRM\Plugin\PluginMigrationManifest;
use ChurchCRM\Plugins\MigrationExample\MigrationExamplePlugin;
use ChurchCRM\Plugins\MigrationExample\Model\Entry;
use ChurchCRM\Plugins\MigrationExample\Model\EntryQuery;
use ChurchCRM\Plugins\MigrationExample\Model\Map\EntryTableMap;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Utils\CSRFUtils;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__, 2);
$fixture = $root . '/tests/fixtures/plugins/community/migration-example';
$workspace = sys_get_temp_dir() . '/churchcrm-migrations-' . bin2hex(random_bytes(8));
$plugins = $workspace . '/plugins';
$pluginPath = $plugins . '/community/migration-example';
mkdir($plugins . '/community', 0700, true);
register_shutdown_function(static function () use ($workspace): void {
    // Only remove this run's newly created temporary directory. Do not follow links.
    $resolved = realpath($workspace);
    if ($resolved === false || !preg_match('/^churchcrm-migrations-[a-f0-9]{16}$/D', basename($resolved))
        || dirname($resolved) !== realpath(sys_get_temp_dir())) {
        return;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($resolved);
});
SystemURLs::init('', ['http://localhost'], $workspace);

function check(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

function rejects(callable $operation, string $contains): void
{
    try {
        $operation();
    } catch (PluginMigrationException $e) {
        check(str_contains($e->getMessage(), $contains), 'Unexpected rejection: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection containing: ' . $contains);
}

function copyTree(string $from, string $to): void
{
    if (!is_dir($to)) {
        mkdir($to, 0700, true);
    }
    foreach (new DirectoryIterator($from) as $file) {
        if ($file->isDot()) {
            continue;
        }
        $target = $to . '/' . $file->getFilename();
        if ($file->isDir()) {
            copyTree($file->getPathname(), $target);
        } else {
            copy($file->getPathname(), $target);
        }
    }
}

function jsonFile(string $path, array $value): void
{
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}

function metadata(): PluginMetadata
{
    global $pluginPath;
    return PluginMetadata::fromJsonFile($pluginPath . '/plugin.json') ?? throw new RuntimeException('Missing fixture manifest');
}

function approve(): void
{
    global $workspace;
    $plugin = metadata();
    $entry = [
        'id' => $plugin->getId(), 'name' => $plugin->getName(), 'version' => $plugin->getVersion(),
        'downloadUrl' => 'https://example.test/migration-example-' . $plugin->getVersion() . '.zip',
        'sha256' => str_repeat('a', 64), 'risk' => 'high', 'riskSummary' => 'Fixture only',
        'permissions' => ['db.migrate', 'db.read', 'db.write', 'cron', 'network.inbound'],
    ];
    PluginMigrationManager::validateApproval($plugin, $entry);
    $_SESSION['RemotePluginRegistry'] = [$plugin->getId() => $entry];
    jsonFile($workspace . '/registry.json', $_SESSION['RemotePluginRegistry']);
    ApprovedPluginRegistry::reset();
    // Simulate the provenance written after verified extraction; no test plugin
    // is added to the real remote registry and tests never access the network.
    SystemConfig::setValue('plugin.migration-example.provenance', json_encode([
        'source' => 'registry', 'downloadUrl' => $entry['downloadUrl'], 'sha256' => $entry['sha256'],
        'version' => $entry['version'],
        'migrationFingerprint' => PluginMigrationManifest::fingerprint(PluginMigrationManifest::read($plugin)),
    ], JSON_THROW_ON_ERROR));
}

function restart(): void
{
    global $plugins;
    PluginManager::reset();
    PluginManager::init($plugins);
}

function fresh(): void
{
    global $connection, $fixture, $pluginPath, $root;
    $connection->exec(file_get_contents($root . '/src/mysql/upgrade/7.7.0-plugin-migrations.sql'));
    $connection->exec('DROP TABLE IF EXISTS plugin_migration_example__entries');
    $connection->exec('DELETE FROM plugin_migration_pmg');
    $connection->exec('DELETE FROM config_cfg');
    ConfigTableMap::clearInstancePool();
    PluginMigrationTableMap::clearInstancePool();
    if (class_exists(EntryTableMap::class)) {
        EntryTableMap::clearInstancePool();
    }
    copyTree($fixture, $pluginPath);
    $_SESSION['AuthenticationProvider']->user->setAdmin(true);
    approve();
    restart();
}

function upgradeFixture(): void
{
    global $pluginPath;
    $data = json_decode(file_get_contents($pluginPath . '/plugin.json'), true, 32, JSON_THROW_ON_ERROR);
    $data['version'] = '1.1.0';
    jsonFile($pluginPath . '/plugin.json', $data);
    jsonFile($pluginPath . '/migrations/migrations.json', ['migrations' => [
        ['id' => '0001_create_entries', 'file' => 'migrations/0001_create_entries.sql'],
        ['id' => '0002_add_note', 'file' => 'migrations/0002_add_note.sql'],
    ]]);
    approve();
    restart();
}

function storedFixture(): void
{
    fresh();
    PluginManager::enablePlugin('migration-example');
    (new Entry())->setLabel('Retained fixture')->save();
}

// Normal core upgrade SQL creates the ledger; no hand-maintained test substitute.
$connection->exec('DROP TABLE IF EXISTS plugin_migration_pmg');
$connection->exec(file_get_contents($root . '/src/mysql/upgrade/7.7.0-plugin-migrations.sql'));
$connection->exec('CREATE TABLE IF NOT EXISTS config_cfg (cfg_name VARCHAR(50) PRIMARY KEY, cfg_value TEXT) ENGINE=InnoDB');

$tests = [];
$tests['no migrations, including before the core ledger upgrade'] = function () use ($connection, $root): void {
    $plugin = new PluginMetadata(['id' => 'legacy-example', 'name' => 'Legacy', 'mainClass' => 'Example', 'version' => '1.0.0'], __DIR__);
    PluginMigrationManager::migrate($plugin);
    PluginMigrationManager::assertCurrent($plugin);
    $connection->exec('DROP TABLE plugin_migration_pmg');
    PluginMigrationManager::assertCurrent($plugin);
    $connection->exec(file_get_contents($root . '/src/mysql/upgrade/7.7.0-plugin-migrations.sql'));
};
$tests['first enable applies once, before boot; generated model reads and writes'] = function (): void {
    fresh();
    check(PluginManager::getPlugin('migration-example') === null, 'Disabled plugin loaded');
    PluginManager::enablePlugin('migration-example');
    $row = new Entry();
    $row->setLabel('Retained donor-free fixture');
    $row->save();
    check(EntryQuery::create()->findPk($row->getId())->getLabel() === $row->getLabel(), 'Generated model round-trip failed');
    PluginManager::enablePlugin('migration-example');
    check(PluginMigrationQuery::create()->count() === 1, 'Migration was repeated');
    check(PluginMigrationQuery::create()->findOne()->getAppliedAt() !== null, 'Success not recorded');
};
$tests['disable and restart/re-enable preserve history and rows'] = function (): void {
    storedFixture();
    PluginManager::disablePlugin('migration-example');
    restart();
    check(!PluginManager::isPluginActive('migration-example'), 'Disable not preserved');
    PluginManager::enablePlugin('migration-example');
    restart();
    check(PluginManager::getPlugin('migration-example') !== null, 'Restart did not load ready plugin');
    check(EntryQuery::create()->count() === 1 && PluginMigrationQuery::create()->count() === 1, 'Disable/restart changed data');
};
$tests['upgrade remains unavailable until Admin enable, then applies only new ID'] = function (): void {
    storedFixture();
    upgradeFixture();
    check(!PluginManager::isPluginActive('migration-example'), 'Pending plugin became operational');
    check(PluginManager::getPlugin('migration-example') === null, 'Pending plugin booted');
    check(PluginMigrationQuery::create()->count() === 1, 'Restart executed pending DDL');
    PluginManager::enablePlugin('migration-example');
    check(PluginMigrationQuery::create()->count() === 2, 'Upgrade history missing');
    check(EntryQuery::create()->count() === 1, 'Upgrade lost data');
};
$tests['uninstall/reinstall preserve ledger/data and skip destructive legacy callbacks'] = function () use ($plugins, $pluginPath): void {
    storedFixture();
    upgradeFixture();
    PluginManager::enablePlugin('migration-example');
    $deactivations = MigrationExamplePlugin::$deactivations;
    PluginInstaller::uninstall($plugins, 'migration-example');
    check(!is_dir($pluginPath), 'Plugin files were not removed');
    check(MigrationExamplePlugin::$uninstalls === 0 && MigrationExamplePlugin::$deactivations === $deactivations, 'Uninstall called legacy callbacks');
    check(PluginMigrationQuery::create()->count() === 2 && EntryQuery::create()->count() === 1, 'Uninstall lost permanent records');
    check(SystemConfig::getValue('plugin.migration-example.provenance') === '', 'Config was not removed');
    global $fixture;
    copyTree($fixture, $pluginPath);
    upgradeFixture();
    PluginManager::enablePlugin('migration-example');
    check(PluginMigrationQuery::create()->count() === 2 && EntryQuery::create()->count() === 1, 'Reinstall replayed or lost records');
};
$tests['duplicate or out-of-order IDs rejected'] = function () use ($pluginPath): void {
    fresh();
    $entry = ['id' => '0001_create_entries', 'file' => 'migrations/0001_create_entries.sql'];
    jsonFile($pluginPath . '/migrations/migrations.json', ['migrations' => [$entry, $entry]]);
    rejects(fn () => PluginMigrationManifest::read(metadata()), 'unique and strictly increasing');
    jsonFile($pluginPath . '/migrations/migrations.json', ['migrations' => [
        ['id' => '0002_add_note', 'file' => 'migrations/0002_add_note.sql'], $entry,
    ]]);
    rejects(fn () => PluginMigrationManifest::read(metadata()), 'unique and strictly increasing');
};
$tests['applied checksum drift rejected before boot or enable'] = function () use ($pluginPath): void {
    fresh();
    PluginManager::enablePlugin('migration-example');
    file_put_contents($pluginPath . '/migrations/0001_create_entries.sql', "\n-- changed\n", FILE_APPEND);
    rejects(fn () => PluginMigrationManager::assertCurrent(metadata()), 'checksum');
    restart();
    check(PluginManager::getPlugin('migration-example') === null, 'Drifted plugin loaded');
    rejects(fn () => PluginManager::enablePlugin('migration-example'), 'differ');
};
$tests['path traversal, absolute paths, hidden segments and symlinks rejected'] = function () use ($pluginPath, $workspace): void {
    fresh();
    foreach (['../outside.sql', '/outside.sql', 'C:/outside.sql', 'migrations/../../outside.sql', 'migrations\\outside.sql', 'https://example.test/a.sql', 'migrations/.hidden.sql'] as $path) {
        jsonFile($pluginPath . '/migrations/migrations.json', ['migrations' => [['id' => '0001_bad', 'file' => $path]]]);
        rejects(fn () => PluginMigrationManifest::read(metadata()), 'path');
    }
    // Windows may require Developer Mode for symlink creation. CI on Linux
    // always exercises this case; report the environmental limitation explicitly.
    file_put_contents($workspace . '/outside.sql', 'ALTER TABLE plugin_migration_example__entries ADD x INT');
    if (@symlink($workspace . '/outside.sql', $pluginPath . '/migrations/link.sql')) {
        jsonFile($pluginPath . '/migrations/migrations.json', ['migrations' => [['id' => '0001_bad', 'file' => 'migrations/link.sql']]]);
        rejects(fn () => PluginMigrationManifest::read(metadata()), 'symbolic links');
        unlink($pluginPath . '/migrations/link.sql');
    } else {
        check(PHP_OS_FAMILY === 'Windows', 'CI must exercise filesystem symlink rejection');
        echo "NOTE: filesystem symlink creation unavailable on this host; Linux CI covers symlinks.\n";
    }
};
$tests['undeclared capability and unapproved/unverified releases rejected'] = function () use ($pluginPath): void {
    fresh();
    $data = json_decode(file_get_contents($pluginPath . '/plugin.json'), true);
    $longId = $data;
    $longId['id'] = str_repeat('a', 27);
    rejects(fn () => PluginMigrationManifest::read(new PluginMetadata($longId, $pluginPath)), 'at most 26');
    $data['permissions'] = ['db.write'];
    rejects(fn () => new PluginMetadata($data, $pluginPath), 'db.migrate');
    rejects(fn () => PluginMigrationManager::validateApproval(metadata(), null), 'approved');
    $entry = $_SESSION['RemotePluginRegistry']['migration-example'];
    $entry['permissions'] = ['db.write'];
    rejects(fn () => PluginMigrationManager::validateApproval(metadata(), $entry), 'db.migrate');
    $entry['permissions'] = ['db.migrate'];
    $entry['risk'] = 'low';
    rejects(fn () => PluginMigrationManager::validateApproval(metadata(), $entry), 'high risk');
    check(!(new ReflectionMethod(ApprovedPluginRegistry::class, 'isValidEntry'))->invoke(null, $entry), 'Low-risk db.migrate registry entry accepted');
    SystemConfig::setValue('plugin.migration-example.unverified', '1');
    rejects(fn () => PluginManager::enablePlugin('migration-example'), 'verified installation');
    check(PluginMigrationQuery::create()->count() === 0, 'Unverified migration executed');
};
$tests['non-Admin service call rejected'] = function (): void {
    fresh();
    $_SESSION['AuthenticationProvider']->user->setAdmin(false);
    rejects(fn () => PluginMigrationManager::migrate(metadata()), 'administrator');
    check(PluginMigrationQuery::create()->count() === 0, 'Unauthorized migration executed');
    $_SESSION['AuthenticationProvider']->user->setAdmin(true);
};
$tests['failed DDL is unapplied and never automatically replayed'] = function () use ($pluginPath): void {
    fresh();
    file_put_contents($pluginPath . '/migrations/0001_create_entries.sql', 'ALTER TABLE plugin_migration_example__missing ADD x INT;');
    approve();
    rejects(fn () => PluginManager::enablePlugin('migration-example'), 'did not complete');
    $record = PluginMigrationQuery::create()->findOne();
    check($record !== null && $record->getAppliedAt() === null, 'Failed migration marked applied');
    rejects(fn () => PluginManager::enablePlugin('migration-example'), 'interrupted or failed');
    check(!PluginManager::isPluginActive('migration-example'), 'Failed plugin enabled');
};
$tests['SQL is one owned CREATE/ALTER statement with no executable comments'] = function () use ($pluginPath): void {
    fresh();
    foreach ([
        'DROP TABLE person_per;',
        'ALTER TABLE person_per ADD x INT;',
        'ALTER TABLE plugin_migration_example__entries ADD x INT; DROP TABLE person_per;',
        'ALTER TABLE plugin_migration_example__entries RENAME TO person_per;',
        'CREATE TABLE plugin_migration_example__entries LIKE person_per;',
        'CREATE TABLE plugin_migration_example__entries (id INT) ENGINE=MyISAM;',
        'ALTER TABLE plugin_migration_example__entries ADD x INT /*! , DROP COLUMN label */;',
        'ALTER TABLE plugin_migration_example__entries ADD x INT REFERENCES person_per(per_ID);',
        'ALTER TABLE other.plugin_migration_example__entries ADD x INT;',
        'ALTER TABLE plugin_other__entries ADD x INT;',
        'ALTER TABLE plugin_migration_example__entries ADD x INT /*M! , DROP COLUMN label */;',
        'ALTER TABLE plugin_migration_example__entries ADD x VARCHAR(10) DEFAULT "ambiguous";',
        "ALTER TABLE plugin_migration_example__entries ADD x VARCHAR(10) DEFAULT 'back\\slash';",
    ] as $sql) {
        file_put_contents($pluginPath . '/migrations/0001_create_entries.sql', $sql);
        rejects(fn () => PluginMigrationManifest::read(metadata()), '');
    }
    file_put_contents($pluginPath . '/migrations/0001_create_entries.sql', "/*Migration comment*/ ALTER TABLE plugin_migration_example__entries ADD note VARCHAR(100) DEFAULT 'it''s; fine'; -- one statement\n");
    check(count(PluginMigrationManifest::read(metadata())) === 1, 'Plain comments or doubled-quote literals were rejected');
};
$tests['removed history/declaration and inserted historical IDs rejected'] = function () use ($pluginPath): void {
    fresh();
    PluginManager::enablePlugin('migration-example');
    jsonFile($pluginPath . '/migrations/migrations.json', ['migrations' => []]);
    rejects(fn () => PluginMigrationManager::assertCurrent(metadata()), 'removed');
    jsonFile($pluginPath . '/migrations/migrations.json', ['migrations' => [
        ['id' => '0000_inserted', 'file' => 'migrations/0002_add_note.sql'],
        ['id' => '0001_create_entries', 'file' => 'migrations/0001_create_entries.sql'],
    ]]);
    rejects(fn () => PluginMigrationManager::assertCurrent(metadata()), 'reordered');
    $data = json_decode(file_get_contents($pluginPath . '/plugin.json'), true);
    unset($data['migrations']);
    jsonFile($pluginPath . '/plugin.json', $data);
    rejects(fn () => PluginMigrationManager::assertCurrent(metadata()), 'removed');
};
$tests['fresh PHP process boots without replaying DDL'] = function () use ($workspace): void {
    fresh();
    PluginManager::enablePlugin('migration-example');
    $process = proc_open([PHP_BINARY, __DIR__ . '/worker.php', $workspace, 'restart'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'Could not start PHP worker');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    check(proc_close($process) === 0 && trim($output) === 'applied=1', 'Restart worker failed: ' . $output . $error);
};
$tests['concurrent lifecycle requests cannot enable or uninstall during migration'] = function () use ($workspace, $plugins, $pluginPath): void {
    fresh();
    $process = proc_open([PHP_BINARY, __DIR__ . '/worker.php', $workspace, 'lock'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'Could not start lock worker');
    try {
        check(trim((string) fgets($pipes[1])) === 'locked', 'Worker did not acquire migration lock');
        rejects(fn () => PluginManager::enablePlugin('migration-example'), 'Another request');
        rejects(fn () => PluginInstaller::uninstall($plugins, 'migration-example'), 'Another request');
        check(PluginMigrationQuery::create()->count() === 0 && is_dir($pluginPath), 'Concurrent lifecycle changed state');
    } finally {
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        check(proc_close($process) === 0, 'Lock worker failed');
    }
    PluginManager::enablePlugin('migration-example');
    check(PluginMigrationQuery::create()->count() === 1, 'Lock was not released');
};
$tests['completion write failure after successful DDL is never replayed'] = function () use ($connection): void {
    fresh();
    $connection->exec("CREATE TRIGGER migration_test_fail_completion BEFORE UPDATE ON plugin_migration_pmg FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Simulated completion write failure'");
    try {
        rejects(fn () => PluginManager::enablePlugin('migration-example'), 'did not complete');
    } finally {
        $connection->exec('DROP TRIGGER migration_test_fail_completion');
    }
    check(EntryQuery::create()->count() === 0, 'DDL did not execute before simulated interruption');
    check(PluginMigrationQuery::create()->select('AppliedAt')->findOne() === null, 'Incomplete attempt marked applied');
    restart();
    rejects(fn () => PluginManager::enablePlugin('migration-example'), 'interrupted or failed');
};
$tests['application transactions are rejected without committing caller data'] = function () use ($connection): void {
    fresh();
    $connection->beginTransaction();
    try {
        rejects(fn () => PluginManager::enablePlugin('migration-example'), 'outside an application transaction');
        check($connection->inTransaction(), 'Migration committed caller transaction');
    } finally {
        $connection->rollBack();
    }
    check(PluginMigrationQuery::create()->count() === 0, 'Transactional invocation attempted DDL');
};
$tests['pending resources, malformed declarations and missing files fail before DDL'] = function () use ($pluginPath, $connection, $root): void {
    fresh();
    file_put_contents($pluginPath . '/migrations/0001_create_entries.sql', "\n-- unapproved edit\n", FILE_APPEND);
    rejects(fn () => PluginManager::enablePlugin('migration-example'), 'differ');
    check(PluginMigrationQuery::create()->count() === 0, 'Modified pending resource attempted');
    $data = json_decode(file_get_contents($pluginPath . '/plugin.json'), true);
    foreach ([[], 123, ''] as $value) {
        $data['migrations'] = $value;
        rejects(fn () => new PluginMetadata($data, $pluginPath), 'JSON path');
    }
    foreach (['missing.json', '../migrations.json', 'migrations/../../outside.json'] as $value) {
        $data['migrations'] = $value;
        rejects(fn () => PluginMigrationManifest::read(new PluginMetadata($data, $pluginPath)), '');
    }
    file_put_contents($pluginPath . '/migrations/migrations.json', '{');
    rejects(fn () => PluginMigrationManifest::read(metadata()), 'valid JSON');
    fresh();
    $connection->exec('DROP TABLE plugin_migration_pmg');
    try {
        rejects(fn () => PluginManager::enablePlugin('migration-example'), 'core database upgrade');
        rejects(fn () => PluginMigrationManager::assertCurrent(metadata()), 'core database upgrade');
    } finally {
        $connection->exec(file_get_contents($root . '/src/mysql/upgrade/7.7.0-plugin-migrations.sql'));
    }
};
$tests['community directory cannot impersonate a core plugin'] = function () use ($pluginPath): void {
    fresh();
    $data = json_decode(file_get_contents($pluginPath . '/plugin.json'), true);
    $data['type'] = 'core';
    jsonFile($pluginPath . '/plugin.json', $data);
    restart();
    check(PluginManager::getPluginMetadata('migration-example') === null, 'Community package impersonated a core plugin');
};
$tests['forged descriptor cannot execute migrations from another directory'] = function () use ($fixture, $workspace): void {
    fresh();
    $outside = $workspace . '/other-package';
    copyTree($fixture, $outside);
    $descriptor = PluginMetadata::fromJsonFile($outside . '/plugin.json');
    rejects(fn () => PluginMigrationManager::migrate($descriptor), 'discovered plugin installation');
    check(PluginMigrationQuery::create()->count() === 0, 'Foreign descriptor attempted DDL');
};
$tests['verified ZIP extraction validates migration resources before plugin code loads'] = function () use ($workspace, $pluginPath): void {
    fresh();
    $zipPath = $workspace . '/fixture.zip';
    $zip = new ZipArchive();
    check($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'Could not create fixture package');
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginPath, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($pluginPath) + 1));
        $zip->addFile($file->getPathname(), 'migration-example/' . $relative);
    }
    $zip->close();
    $extract = new ReflectionMethod(PluginInstaller::class, 'extractAndValidate');
    $destination = $workspace . '/extracted';
    mkdir($destination, 0700);
    $extract->invoke(null, $zipPath, $destination, 'migration-example', '1.0.0');
    $extracted = PluginMetadata::fromJsonFile($destination . '/migration-example/plugin.json');
    check(PluginMigrationManifest::fingerprint(PluginMigrationManifest::read($extracted)) === PluginMigrationManifest::fingerprint(PluginMigrationManifest::read(metadata())), 'Extraction changed migration bytes');
    check(PluginMigrationQuery::create()->count() === 0, 'Extraction ran DDL');
    $zip->open($zipPath);
    $zip->addFromString('migration-example/migrations/migrations.json', '{"migrations":[{"id":"0001_bad","file":"../outside.sql"}]}');
    $zip->close();
    rejects(fn () => $extract->invoke(null, $zipPath, $destination, 'migration-example', '1.0.0'), 'paths');
};
$tests['new install applies multiple migrations in manifest order'] = function () use ($connection): void {
    fresh();
    upgradeFixture();
    PluginManager::enablePlugin('migration-example');
    $ids = PluginMigrationQuery::create()->orderByMigrationId()->select('MigrationId')->find()->toArray();
    check($ids === ['0001_create_entries', '0002_add_note'], 'Ordered migration history differs');
    check($connection->query("SHOW COLUMNS FROM plugin_migration_example__entries LIKE 'note'")->fetch() !== false, 'Second ALTER did not execute after CREATE');
};
$tests['fresh install, core upgrade and Cypress seed produce the same ledger'] = function () use ($connection, $root): void {
    $schemas = [];
    foreach (['src/mysql/install/Install.sql', 'src/mysql/upgrade/7.7.0-plugin-migrations.sql', 'cypress/data/seed.sql'] as $path) {
        $sql = file_get_contents($root . '/' . $path);
        check(preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`plugin_migration_pmg` \([\s\S]+?;/', $sql, $match) === 1, 'Missing ledger in ' . $path);
        $connection->exec('DROP TABLE IF EXISTS plugin_migration_pmg');
        $connection->exec($match[0]);
        $schemas[] = $connection->query('SHOW CREATE TABLE plugin_migration_pmg')->fetch(PDO::FETCH_NUM)[1];
    }
    check(count(array_unique($schemas)) === 1, 'Core ledger schema drift');
};
$tests['security scanner reports migrations and rejects undeclared or altered SQL'] = function () use ($root, $pluginPath): void {
    fresh();
    $scan = static function () use ($root, $pluginPath): array {
        $process = proc_open([PHP_BINARY, $root . '/scripts/plugin-scan.php', '--json', $pluginPath], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        check(is_resource($process), 'Could not start scanner');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        check($error === '', 'Scanner failed: ' . $error);
        return [$exit, json_decode($output, true, 32, JSON_THROW_ON_ERROR)];
    };
    [$status, $result] = $scan();
    check($status === 0 && in_array('capability.db.migrate', array_column($result['findings'], 'code'), true), 'Scanner omitted capability');
    file_put_contents($pluginPath . '/migrations/0001_create_entries.sql', 'DROP TABLE config_cfg;');
    [$status, $result] = $scan();
    check($status === 1 && $result['stats']['errors'] > 0, 'Scanner accepted unsupported SQL');
    $data = json_decode(file_get_contents($pluginPath . '/plugin.json'), true);
    unset($data['migrations']);
    $data['permissions'] = ['db.write'];
    jsonFile($pluginPath . '/plugin.json', $data);
    [$status, $result] = $scan();
    check($status === 1 && in_array('migration.undeclared', array_column($result['findings'], 'code'), true), 'Scanner accepted undeclared SQL');
};
$tests['enable HTTP endpoint requires Admin and CSRF; returns actionable migration conflict'] = function () use ($root): void {
    fresh();
    $app = AppFactory::create();
    $app->group('/plugins/api', function (RouteCollectorProxy $group) use ($root): void {
        require $root . '/src/plugins/routes/api/management.php';
    })->add(AdminRoleAuthMiddleware::class);
    $app->addBodyParsingMiddleware();
    $request = (new ServerRequestFactory())->createServerRequest('POST', '/plugins/api/plugins/migration-example/enable')->withHeader('Accept', 'application/json');
    check($app->handle($request)->getStatusCode() === 403, 'Missing CSRF token accepted');
    $request = $request->withHeader('X-CSRF-Token', CSRFUtils::generateToken());
    $_SESSION['AuthenticationProvider']->user->setAdmin(false);
    check($app->handle($request)->getStatusCode() === 403, 'Non-admin HTTP request accepted');
    $_SESSION['AuthenticationProvider']->user->setAdmin(true);
    check($app->handle($request)->getStatusCode() === 200, 'Admin CSRF-protected enable failed');
    SystemConfig::setValue('plugin.migration-example.unverified', '1');
    $response = $app->handle($request);
    check($response->getStatusCode() === 409 && str_contains((string) $response->getBody(), 'verified installation'), 'Migration error not actionable');
};

$tests['management view offers Enable for pending migrations and explains retention'] = function () use ($root, $workspace): void {
    storedFixture();
    upgradeFixture();
    $communityPlugins = PluginManager::getAllPlugins();
    check($communityPlugins[0]['migrationError'] && !$communityPlugins[0]['isActive'], 'Pending status absent');
    $corePlugins = [];
    $sRootPath = '';
    // Render the real management view while isolating unrelated page-shell services.
    mkdir($workspace . '/Include', 0700);
    file_put_contents($workspace . '/Include/Header.php', '');
    file_put_contents($workspace . '/Include/Footer.php', '');
    (new ReflectionProperty(SystemURLs::class, 'CSPNonce'))->setValue(null, 'migration-test-nonce');
    ob_start();
    try {
        require $root . '/src/plugins/views/management.php';
        $html = (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
    check(str_contains($html, 'data-action="enable" data-plugin-id="migration-example"'), 'Migration error hid the Enable action');
    check(str_contains($html, 'Uninstalling preserves its application tables and migration history.'), 'Retention notice absent');
    check(str_contains($html, 'X-CSRF-Token'), 'Browser enable lacks CSRF header');
};

require __DIR__ . '/failure-recovery.php';

echo 'Environment: PHP ' . PHP_VERSION . '; ' . PHP_OS_FAMILY . '; database ' . $connection->query('SELECT VERSION()')->fetchColumn() . "\n";
$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS: $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL: $name\n" . $e . "\n";
    }
}
echo count($tests) . " tests; $failed failed\n";
exit($failed === 0 ? 0 : 1);
