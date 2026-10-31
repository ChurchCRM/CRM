<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\UpgradeService;
use ChurchCRM\Utils\SQLUtils;
use ChurchCRM\Utils\VersionUtils;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Tester\Assert;

require dirname(__DIR__, 2) . '/integration/bootstrap.php';

$root = APP_ROOT;
$dsn = getenv('UPGRADE_TEST_DSN');
$user = getenv('UPGRADE_TEST_USER');
$password = getenv('UPGRADE_TEST_PASSWORD');
$baseline = dirname(__DIR__, 2) . '/fixtures/upgrade/Install-7.8.0.sql';
$original = dirname(__DIR__, 2) . '/fixtures/upgrade/7.8.1-original.sql';
$admin = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo 'Engine: ' . $admin->query('SELECT VERSION()')->fetchColumn() . "\n";
$schemas = [];
$tempRoot = sys_get_temp_dir() . '/crm-upgrade-batches-' . bin2hex(random_bytes(6));
mkdir($tempRoot . '/mysql', 0700, true);
mkdir($tempRoot . '/scripts');
symlink($root . '/locale', $tempRoot . '/locale');
symlink($root . '/mysql/upgrade', $tempRoot . '/mysql/upgrade');
file_put_contents($tempRoot . '/composer.json', '{"version":"7.8.1"}');
SystemURLs::init('', [], $root);
SystemConfig::init();
$newDatabase = static function (bool $fixture = false) use ($dsn, $admin, $user, $password, $baseline, &$schemas): PDO {
    $name = 'upgrade_test_' . bin2hex(random_bytes(6));
    $admin->exec("CREATE DATABASE `$name`");
    $schemas[] = $name;
    $db = new PDO($dsn . ';dbname=' . $name, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($fixture) {
        SQLUtils::sqlImport($baseline, $db);
    } else {
        $db->exec('CREATE TABLE version_ver (ver_ID INT AUTO_INCREMENT PRIMARY KEY, ver_version VARCHAR(50) UNIQUE, ver_update_start DATETIME, ver_update_end DATETIME)');
        $db->exec('CREATE TABLE request_runs (id INT PRIMARY KEY)');
    }
    $db->exec("INSERT INTO version_ver (ver_version) VALUES ('7.8.0')");
    $container = Propel::getServiceContainer();
    $container->closeConnections();
    $container->setAdapterClass('default', 'mysql');
    $manager = new ConnectionManagerSingle('default');
    $manager->setConfiguration(['dsn' => $dsn . ';dbname=' . $name, 'user' => $user, 'password' => $password]);
    $container->setConnectionManager($manager);
    $container->setDefaultDatasource('default');
    Propel::disableInstancePooling();
    require APP_ROOT . '/Include/LoadDatabaseMap.php';
    return $db;
};
$manifest = static function (array $steps, string $installed = '7.8.1') use ($tempRoot): void {
    file_put_contents($tempRoot . '/mysql/upgrade.json', json_encode($steps, JSON_THROW_ON_ERROR));
    file_put_contents($tempRoot . '/composer.json', json_encode(['version' => $installed], JSON_THROW_ON_ERROR));
    SystemURLs::init('', [], $tempRoot);
    $_SESSION = [];
};
$snapshot = static function (PDO $db): array {
    $result = [];
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if ($table === 'database_upgrade_progress' || $table === 'version_ver') {
            continue;
        }
        $schema = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        $rows = array_map('json_encode', $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC));
        sort($rows);
        $result[$table] = [$schema, $rows];
    }
    ksort($result);
    return $result;
};

try {
    $scripts = [];
    for ($i = 0; $i < 30; $i++) {
        $path = '/scripts/' . $i . ($i === 1 ? '.php' : '.sql');
        file_put_contents($tempRoot . $path, $i === 1 ? '<?php $connection->exec("INSERT INTO request_runs VALUES (1)");' : "INSERT INTO request_runs VALUES ($i);");
        $scripts[] = $path;
    }
    $steps = [
        ['versions' => ['7.8.0'], 'scripts' => array_slice($scripts, 0, 3), 'dbVersion' => '7.8.1'],
        ['versions' => ['7.8.1'], 'scripts' => array_slice($scripts, 3), 'dbVersion' => '7.8.2'],
    ];
    $db = $newDatabase();
    $manifest($steps, '7.8.2');
    for ($i = 0; $i < 30; $i++) {
        $done = UpgradeService::upgradeDatabaseVersion(true);
        Assert::true((int) $db->query('SELECT COUNT(*) FROM request_runs')->fetchColumn() === $i + 1, "Request $i executes exactly one SQL or PHP file");
        Assert::true($done === ($i === 29), 'Completion is reported only after the last file');
        Assert::true(VersionUtils::getDBVersion() === ($i < 2 ? '7.8.0' : ($i < 29 ? '7.8.1' : '7.8.2')), 'Version advances only after a whole step');
        if (!$done) {
            Assert::true(empty($_SESSION['dbUpgradeJustRan']), 'Partial progress is not marked as a completed upgrade');
        }
    }
    Assert::true(UpgradeService::upgradeDatabaseVersion(true), 'A completed upgrade is a no-op');

    $db = $newDatabase();
    $manifest($steps, '7.8.2');
    Assert::true(UpgradeService::upgradeDatabaseVersion(), 'Synchronous callers still complete all files');
    Assert::true((int) $db->query('SELECT COUNT(*) FROM request_runs')->fetchColumn() === 30, 'Synchronous upgrade runs the full path');

    $db = $newDatabase();
    $manifest([['versions' => ['7.8.0'], 'scripts' => ['/scripts/fail.sql'], 'dbVersion' => '7.8.1']]);
    file_put_contents($tempRoot . '/scripts/fail.sql', 'INSERT INTO request_runs VALUES (77); SELECT missing_upgrade_column;');
    Assert::exception(static fn () => UpgradeService::upgradeDatabaseVersion(true), PDOException::class);
    Assert::same('7.8.0', VersionUtils::getDBVersion(), 'Interrupted file keeps the version');
    $_SESSION = [];
    $error = Assert::exception(static fn () => UpgradeService::upgradeDatabaseVersion(true), RuntimeException::class);
    Assert::contains('/scripts/fail.sql', $error->getMessage());
    Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM request_runs')->fetchColumn(), 'Partial DML executes once');

    $db = $newDatabase();
    $manifest([['versions' => ['7.8.0'], 'scripts' => ['/scripts/0.sql'], 'dbVersion' => '7.8.1']]);
    $lockName = 'churchcrm-upgrade-' . sha1((string) $db->query('SELECT DATABASE()')->fetchColumn());
    $db->query("SELECT GET_LOCK('$lockName', 0)");
    try {
        Assert::exception(static fn () => UpgradeService::upgradeDatabaseVersion(true), RuntimeException::class, code: 409);
    } finally {
        $db->query("SELECT RELEASE_LOCK('$lockName')");
    }
    Assert::true(UpgradeService::upgradeDatabaseVersion(true), 'Busy requests do not create a started checkpoint');

    $expectedDb = $newDatabase(true);
    SQLUtils::sqlImport($original, $expectedDb);
    $expected = $snapshot($expectedDb);
    foreach ([false, true] as $partial) {
        $db = $newDatabase(true);
        if ($partial) {
            $sql = file_get_contents($original);
            file_put_contents($tempRoot . '/partial.sql', substr($sql, 0, strpos($sql, 'UPDATE `email_log_eml`')));
            SQLUtils::sqlImport($tempRoot . '/partial.sql', $db);
        }
        $manifest([['versions' => ['7.8.0'], 'scripts' => ['/mysql/upgrade/7.8.1.sql', '/mysql/upgrade/7.8.1.php'], 'dbVersion' => '7.8.1']]);
        Assert::true(!UpgradeService::upgradeDatabaseVersion(true), '7.8.1 SQL yields before PHP');
        Assert::true($snapshot($db) === $expected, $partial ? 'Legacy partial email ALTER heals to the original schema and data' : 'Fresh guarded SQL exactly matches the original schema and data');
        Assert::true(VersionUtils::getDBVersion() === '7.8.0', 'SQL completion alone does not bump the version');
        Assert::true(UpgradeService::upgradeDatabaseVersion(true) && VersionUtils::getDBVersion() === '7.8.1', 'Following request executes PHP and completes the step');
    }
} finally {
    Propel::getServiceContainer()->closeConnections();
    foreach ($schemas as $schema) {
        $admin->exec("DROP DATABASE `$schema`");
    }
    unlink($tempRoot . '/mysql/upgrade');
    unlink($tempRoot . '/locale');
    foreach (glob($tempRoot . '/scripts/*') as $file) { unlink($file); }
    foreach (glob($tempRoot . '/mysql/*') as $file) { unlink($file); }
    rmdir($tempRoot . '/scripts');
    rmdir($tempRoot . '/mysql');
    foreach (glob($tempRoot . '/*') as $file) { unlink($file); }
    rmdir($tempRoot);
}
