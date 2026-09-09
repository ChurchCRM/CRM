<?php

declare(strict_types=1);

// Match the integration harness for existing PHP 8.4 User hook deprecations.
error_reporting(E_ALL & ~E_DEPRECATED);

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\model\ChurchCRM\PluginMigrationQuery;
use ChurchCRM\Plugin\ApprovedPluginRegistry;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Plugin\PluginMetadata;
use ChurchCRM\Plugin\PluginMigrationManager;
use ChurchCRM\Plugin\PluginMigrationManifest;
use ChurchCRM\Plugins\MigrationExample\Model\Entry;
use ChurchCRM\Utils\SQLUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;

// CLI only, outside the public document root. Never configure this on a real CRM.
if (PHP_SAPI !== 'cli' || getenv('PLUGIN_MIGRATION_BROWSER_TEST') !== '1') {
    throw new RuntimeException('Explicit disposable browser-fixture mode is required.');
}
$root = dirname(__DIR__, 3);
$database = 'churchcrm_plugin_migrations_browser_test';
$host = getenv('PLUGIN_BROWSER_DB_HOST') ?: '127.0.0.1';
$port = getenv('PLUGIN_BROWSER_DB_PORT') ?: '3306';
if (!in_array($host, ['127.0.0.1', 'localhost'], true) || !ctype_digit($port)) {
    throw new RuntimeException('Browser fixtures require a loopback database.');
}
$action = $argv[1] ?? '';
if (!in_array($action, ['initialize', 'fresh', 'pending', 'failure', 'retained', 'inspect'], true)) {
    throw new RuntimeException('Unknown fixture action.');
}
require $root . '/src/vendor/autoload.php';
$manager = new ConnectionManagerSingle('default');
$manager->setConfiguration([
    'dsn' => "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
    'user' => getenv('PLUGIN_BROWSER_DB_USER') ?: 'root',
    'password' => getenv('PLUGIN_BROWSER_DB_PASSWORD') ?: '',
    'options' => ['ATTR_ERRMODE' => PDO::ERRMODE_EXCEPTION],
]);
$container = Propel::getServiceContainer();
$container->setAdapterClass('default', 'mysql');
$container->setConnectionManager($manager);
$container->setDefaultDatasource('default');
require $root . '/src/Include/LoadDatabaseMap.php';
$connection = Propel::getWriteConnection('default');
if ($connection->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    throw new RuntimeException('Unsafe browser-fixture database.');
}
$configPath = $root . '/src/Include/Config.php';
$marker = 'Disposable plugin migration browser fixture';
if (is_file($configPath) && !str_contains((string) file_get_contents($configPath), $marker)) {
    throw new RuntimeException('Refusing to replace a non-fixture CRM configuration.');
}
$sessionPath = getenv('PLUGIN_BROWSER_SESSION_DIR') ?: '';
if ($sessionPath === '' || !is_dir($sessionPath)) {
    throw new RuntimeException('Create a dedicated PLUGIN_BROWSER_SESSION_DIR first.');
}
if ($action === 'initialize') {
    // This importer is deliberately destructive only inside the fixed test database.
    SQLUtils::dropAllTables($connection);
    $connection->exec("SET sql_mode=(SELECT REPLACE(REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''),'NO_ZERO_DATE',''))");
    SQLUtils::sqlImport($root . '/cypress/data/seed.sql', $connection);
    file_put_contents($configPath, <<<'CONFIG'
<?php
// Disposable plugin migration browser fixture
if (getenv('PLUGIN_MIGRATION_BROWSER_TEST') !== '1') {
    throw new RuntimeException('Disposable browser fixture environment missing.');
}
$sSERVERNAME = getenv('PLUGIN_BROWSER_DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('PLUGIN_BROWSER_DB_PORT') ?: '3306';
$sUSER = getenv('PLUGIN_BROWSER_DB_USER') ?: 'root';
$sPASSWORD = getenv('PLUGIN_BROWSER_DB_PASSWORD') ?: '';
$sDATABASE = 'churchcrm_plugin_migrations_browser_test';
$sRootPath = '';
$bLockURL = false;
$URL = [getenv('CYPRESS_BASE_URL') ?: 'http://127.0.0.1:8097/'];
ini_set('session.save_path', getenv('PLUGIN_BROWSER_SESSION_DIR'));
error_reporting(E_ERROR);
require_once __DIR__ . '/LoadConfigs.php';
CONFIG);
    echo json_encode(['initialized' => true], JSON_THROW_ON_ERROR) . "\n";
    exit;
}

$sessionId = $argv[2] ?? '';
if (!preg_match('/^[a-zA-Z0-9,-]{16,128}$/D', $sessionId)
    || !is_file($sessionPath . '/sess_' . $sessionId)) {
    throw new RuntimeException('Use the existing browser login session.');
}
session_save_path($sessionPath);
session_id($sessionId);
session_start();
if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
    throw new RuntimeException('Fixture actions require the actual logged-in Admin.');
}
SystemURLs::init('', [getenv('CYPRESS_BASE_URL') ?: 'http://127.0.0.1:8097/'], $root . '/src');
SystemConfig::init(ConfigQuery::create()->find());
$pluginPath = $root . '/src/plugins/community/migration-example';
$ownershipMarker = $pluginPath . '/.browser-fixture';

if ($action === 'inspect') {
    $history = PluginMigrationQuery::create()->filterByPluginId('migration-example')->orderByMigrationId()->find();
    echo json_encode([
        'applied' => count(array_filter(iterator_to_array($history), static fn ($row) => $row->getAppliedAt() !== null)),
        'uncertain' => count(array_filter(iterator_to_array($history), static fn ($row) => $row->getAppliedAt() === null)),
        'rows' => (int) $connection->query('SELECT COUNT(*) FROM plugin_migration_example__entries')->fetchColumn(),
        'settings' => ConfigQuery::create()->filterByName('plugin.migration-example.%', Criteria::LIKE)->count(),
        'installed' => is_dir($pluginPath),
    ], JSON_THROW_ON_ERROR) . "\n";
    exit;
}
if (is_dir($pluginPath) && !is_file($ownershipMarker)) {
    throw new RuntimeException('Refusing to replace a plugin not owned by this test.');
}
if (!is_dir($pluginPath)) {
    mkdir($pluginPath, 0700, true);
}
$fixture = $root . '/tests/fixtures/plugins/community/migration-example';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($files as $file) {
    if ($file->isLink()) {
        throw new RuntimeException('Fixture must not contain symlinks.');
    }
    $target = $pluginPath . substr($file->getPathname(), strlen($fixture));
    if ($file->isDir()) {
        if (!is_dir($target)) {
            mkdir($target, 0700, true);
        }
    } else {
        copy($file->getPathname(), $target);
    }
}
file_put_contents($ownershipMarker, $marker);
$connection->exec('DROP TABLE IF EXISTS plugin_migration_example__entries');
PluginMigrationQuery::create()->filterByPluginId('migration-example')->delete();
ConfigQuery::create()->filterByName('plugin.migration-example.%', Criteria::LIKE)->delete();
// Make the existing core plugin's ordinary enable/disable flow deterministic.
SystemConfig::setValue('plugin.custom-links.enabled', '0');

$approve = static function () use ($pluginPath): void {
    $plugin = PluginMetadata::fromJsonFile($pluginPath . '/plugin.json');
    $entry = [
        'id' => 'migration-example', 'name' => $plugin->getName(), 'version' => $plugin->getVersion(),
        'downloadUrl' => 'https://example.test/browser-migration-fixture.zip',
        'sha256' => str_repeat('b', 64), 'risk' => 'high', 'riskSummary' => 'Disposable browser fixture',
        'permissions' => ['db.migrate', 'db.read', 'db.write', 'cron', 'network.inbound'],
    ];
    // Synthetic reviewed catalog and extraction provenance belong only to this
    // disposable login session. Production approval checks are never bypassed.
    PluginMigrationManager::validateApproval($plugin, $entry);
    $_SESSION['RemotePluginRegistry'] = ['migration-example' => $entry];
    ApprovedPluginRegistry::reset();
    SystemConfig::setValue('plugin.migration-example.provenance', json_encode([
        'source' => 'registry', 'downloadUrl' => $entry['downloadUrl'], 'sha256' => $entry['sha256'],
        'version' => $entry['version'],
        'migrationFingerprint' => PluginMigrationManifest::fingerprint(PluginMigrationManifest::read($plugin)),
    ], JSON_THROW_ON_ERROR));
};
$approve();
PluginManager::init($root . '/src/plugins');
if ($action !== 'fresh') {
    PluginManager::enablePlugin('migration-example');
    (new Entry())->setLabel('Browser retained fixture')->save();
}
if (in_array($action, ['pending', 'failure'], true)) {
    $manifest = json_decode((string) file_get_contents($pluginPath . '/plugin.json'), true, 32, JSON_THROW_ON_ERROR);
    $manifest['version'] = '1.1.0';
    file_put_contents($pluginPath . '/plugin.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    file_put_contents($pluginPath . '/migrations/migrations.json', json_encode(['migrations' => [
        ['id' => '0001_create_entries', 'file' => 'migrations/0001_create_entries.sql'],
        ['id' => '0002_add_note', 'file' => 'migrations/0002_add_note.sql'],
    ]], JSON_THROW_ON_ERROR));
    if ($action === 'failure') {
        // Valid reviewed DDL shape, but a duplicate column fails at the database.
        file_put_contents($pluginPath . '/migrations/0002_add_note.sql', 'ALTER TABLE plugin_migration_example__entries ADD COLUMN id INTEGER');
    }
    $approve();
}
session_write_close();
echo json_encode(['state' => $action], JSON_THROW_ON_ERROR) . "\n";
