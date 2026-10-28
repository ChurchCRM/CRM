<?php

/**
 * Requires Composer + generated ORM models and a disposable MySQL/MariaDB server.
 * UPGRADE_TEST_DSN='mysql:host=127.0.0.1;port=3306' UPGRADE_TEST_USER=root \
 *   UPGRADE_TEST_PASSWORD=... php scripts/test-database-upgrades.php
 * Add --boundaries to interrupt and replay every SQL statement.
 * Add --sql-only to run without Composer or ORM models.
 * UPGRADE_TEST_BASELINE can point to an unmodified 7.8.0 Install.sql.
 * Creates and drops only randomly named upgrade_test_* schemas.
 */

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\Service\UpgradeService;
use ChurchCRM\Utils\SQLUtils;
use ChurchCRM\Utils\VersionUtils;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;

$sqlOnly = in_array('--sql-only', $argv, true);
if ($sqlOnly) {
    require __DIR__ . '/../src/ChurchCRM/Utils/SQLUtils.php';
} else {
    require __DIR__ . '/../src/vendor/autoload.php';
}

$root = dirname(__DIR__) . '/src';
$dsn = getenv('UPGRADE_TEST_DSN');
if (!$dsn || str_contains($dsn, 'dbname=')) {
    throw new RuntimeException('Set UPGRADE_TEST_DSN without dbname to a disposable database server.');
}
$user = getenv('UPGRADE_TEST_USER') ?: 'root';
$password = getenv('UPGRADE_TEST_PASSWORD') ?: '';
$admin = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo 'Engine: ' . $admin->query('SELECT VERSION()')->fetchColumn() . "\n";
$schemas = [];
$tempRoot = sys_get_temp_dir() . '/churchcrm-upgrade-' . bin2hex(random_bytes(6));
mkdir($tempRoot . '/mysql', 0700, true);
symlink($root . '/mysql/upgrade', $tempRoot . '/mysql/upgrade');
symlink($root . '/locale', $tempRoot . '/locale');
file_put_contents($tempRoot . '/composer.json', '{"version":"7.8.1"}');
$updates = json_decode(file_get_contents($root . '/mysql/upgrade.json'), true, 512, JSON_THROW_ON_ERROR);
$step = $updates['pre-7.8.1'];

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
};
$newDatabase = static function (bool $occupied = false) use ($admin, $dsn, $user, $password, $root, &$schemas): PDO {
    $name = 'upgrade_test_' . bin2hex(random_bytes(6));
    $admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $schemas[] = $name;
    $db = new PDO($dsn . ';dbname=' . $name, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $baseline = getenv('UPGRADE_TEST_BASELINE');
    SQLUtils::sqlImport($baseline ?: $root . '/mysql/install/Install.sql', $db);
    if (!$baseline) {
        $db->exec('ALTER TABLE email_log_eml DROP INDEX idx_eml_Batch, DROP COLUMN eml_NextAttempt, DROP COLUMN eml_Attempts, DROP COLUMN eml_Batch');
        $db->exec('ALTER TABLE person_per DROP INDEX idx_per_grd_ID, DROP COLUMN per_grd_ID');
        $db->exec('DELETE FROM list_lst WHERE lst_ID = 6');
    }
    $db->exec("DELETE FROM config_cfg WHERE cfg_name IN ('sVolunteerVersion', 'iVolunteerGapAlertLeadHours', 'iGradeListId')");
    $db->exec("DELETE FROM version_ver");
    $db->exec("INSERT INTO version_ver (ver_version) VALUES ('7.8.0')");
    $db->exec("INSERT INTO config_cfg VALUES ('iVolunteerReminderLeadHours', '36')");
    $db->exec("INSERT INTO pledge_plg (plg_plgID, plg_PledgeOrPayment, plg_GroupKey, plg_NonDeductible) VALUES (1, 'Pledge', 'mixed-key', 0), (2, 'Payment', 'mixed-key', 0), (3, 'Payment', 'payment-only', 0)");
    $db->exec("INSERT INTO pledge_denominations_pdem (pdem_plg_GroupKey, pdem_denominationID, pdem_denominationQuantity) VALUES ('mixed-key', 1, 3), ('payment-only', 1, 2)");
    $db->exec("INSERT INTO email_log_eml (eml_Address, eml_Kind, eml_Subject, eml_Status, eml_DateSent) VALUES ('test@example.org', 'direct', 'upgrade test', 'sent', '2026-01-01'), ('queued@example.org', 'direct', 'upgrade test', 'queued', '2026-01-01')");
    if ($occupied) {
        $db->exec("INSERT INTO list_lst VALUES (6, 1, 1, 'Existing group role')");
    }
    return $db;
};
$snapshot = static function (PDO $db): array {
    $schema = [];
    $data = [];
    foreach ($db->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$table, $type]) {
        $definition = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        $schema[$table] = $table === 'version_ver' ? preg_replace('/ AUTO_INCREMENT=\d+/', '', $definition) : $definition;
        if ($type !== 'BASE TABLE' || $table === 'version_ver') {
            continue;
        }
        $rows = $db->query("SELECT * FROM `$table`" . ($table === 'config_cfg' ? " WHERE cfg_name NOT LIKE 'dbUpgrade.%'" : ''))->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows);
        sort($rows);
        $data[$table] = $rows;
    }
    ksort($schema);
    ksort($data);
    return [$schema, $data];
};
$runSplit = static function (PDO $db) use ($step, $root): void {
    foreach ($step['scripts'] as $script) {
        if (str_ends_with($script['path'], '.sql')) {
            SQLUtils::sqlImport($root . $script['path'], $db);
        }
    }
};
$useDatabase = static function (PDO $db) use ($dsn, $user, $password): void {
    $name = $db->query('SELECT DATABASE()')->fetchColumn();
    $container = Propel::getServiceContainer();
    $container->closeConnections();
    $container->setAdapterClass('default', 'mysql');
    $manager = new ConnectionManagerSingle('default');
    $manager->setConfiguration(['dsn' => $dsn . ';dbname=' . $name, 'user' => $user, 'password' => $password]);
    $container->setConnectionManager($manager);
    $container->setDefaultDatasource('default');
    Propel::disableInstancePooling();
    require __DIR__ . '/../src/Include/LoadDatabaseMap.php';
};
$setStep = static function (array $scripts) use ($tempRoot): void {
    file_put_contents($tempRoot . '/mysql/upgrade.json', json_encode([
        'test' => ['versions' => ['7.8.0'], 'scripts' => $scripts, 'dbVersion' => '7.8.1'],
    ], JSON_THROW_ON_ERROR));
};

try {
    if (!$sqlOnly) {
        SystemURLs::init('', [], $root);
        SystemConfig::init();
    }
    foreach ([false, true] as $occupied) {
        $original = $newDatabase($occupied);
        SQLUtils::sqlImport($root . '/mysql/upgrade/7.8.1.sql', $original);
        $expected = $snapshot($original);
        $split = $newDatabase($occupied);
        $runSplit($split);
        $check($snapshot($split) === $expected, 'Fresh schema and application data match original SQL (list 6 ' . ($occupied ? 'occupied' : 'free') . ')');
        $runSplit($split);
        if ($snapshot($split) !== $expected) {
            file_put_contents(sys_get_temp_dir() . '/upgrade-expected.json', json_encode($expected, JSON_PRETTY_PRINT));
            file_put_contents(sys_get_temp_dir() . '/upgrade-actual.json', json_encode($snapshot($split), JSON_PRETTY_PRINT));
        }
        $check($snapshot($split) === $expected, 'Every split SQL file can run again without changing application data');

        if (in_array('--boundaries', $argv, true)) {
            foreach ($step['scripts'] as $index => $script) {
                if (!str_ends_with($script['path'], '.sql')) {
                    continue;
                }
                $counter = new class ($split) {
                    public int $count = 0;
                    public ?int $failAfter = null;
                    public function __construct(public PDO $db) {}
                    public function query(string $sql): PDOStatement|false
                    {
                        $result = $this->db->query($sql);
                        if (++$this->count === $this->failAfter) {
                            throw new RuntimeException('simulated interruption');
                        }
                        return $result;
                    }
                };
                SQLUtils::sqlImport($root . $script['path'], $counter);
                $count = $counter->count;
                for ($boundary = 1; $boundary <= $count; $boundary++) {
                    $case = $newDatabase($occupied);
                    foreach (array_slice($step['scripts'], 0, $index) as $previous) {
                        SQLUtils::sqlImport($root . $previous['path'], $case);
                    }
                    $counter->db = $case;
                    $counter->count = 0;
                    $counter->failAfter = $boundary;
                    try {
                        SQLUtils::sqlImport($root . $script['path'], $counter);
                        throw new RuntimeException('Expected interruption');
                    } catch (RuntimeException $e) {
                        if ($e->getMessage() !== 'simulated interruption') {
                            throw $e;
                        }
                    }
                    $schema = $case->query('SELECT DATABASE()')->fetchColumn();
                    $case = new PDO($dsn . ';dbname=' . $schema, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $runSplit($case);
                    if ($snapshot($case) !== $expected) {
                        throw new RuntimeException($script['path'] . ' failed after statement ' . $boundary);
                    }
                    $admin->exec("DROP DATABASE `$schema`");
                    $schemas = array_values(array_diff($schemas, [$schema]));
                }
                $check(true, basename($script['path']) . " recovers after each of its $count statements");
            }
        }

        $healing = $newDatabase($occupied);
        $oldSql = file_get_contents($root . '/mysql/upgrade/7.8.1.sql');
        $partialFile = $tempRoot . '/partial.sql';
        file_put_contents($partialFile, substr($oldSql, 0, strpos($oldSql, 'UPDATE `email_log_eml`')));
        SQLUtils::sqlImport($partialFile, $healing);
        if ($sqlOnly) {
            $runSplit($healing);
            $check($snapshot($healing) === $expected, 'Healed legacy partial SQL matches the original schema and data');
            continue;
        }
        $useDatabase($healing);
        SystemURLs::init('', [], $tempRoot);
        $setStep($step['scripts']);
        $check(UpgradeService::upgradeDatabaseVersion(), 'Partially applied legacy email ALTER heals through the real upgrade service');
        $check(VersionUtils::getDBVersion() === '7.8.1', 'Healing advances the version after SQL and PHP complete');
        $check($snapshot($healing) === $expected, 'Healed schema and application data match original SQL');
        $check((int) $healing->query("SELECT COUNT(*) FROM config_cfg WHERE cfg_name LIKE 'dbUpgrade.%' AND cfg_value = 'complete'")->fetchColumn() === count($step['scripts']), 'SystemConfig initialization preserves every completed file checkpoint');
        SystemURLs::init('', [], $root);
    }

    if ($sqlOnly) {
        echo "All SQL upgrade checks passed.\n";
        return;
    }
    $db = $newDatabase();
    $useDatabase($db);
    SystemURLs::init('', [], $tempRoot);
    file_put_contents($tempRoot . '/once.sql', 'CREATE TABLE upgrade_once (id INT);');
    file_put_contents($tempRoot . '/retry.sql', 'CREATE TABLE IF NOT EXISTS upgrade_retry (id INT); SELECT missing_upgrade_column;');
    $setStep(['/once.sql', ['path' => '/retry.sql', 'retryable' => true]]);
    try {
        UpgradeService::upgradeDatabaseVersion();
        throw new RuntimeException('Expected SQL failure');
    } catch (PDOException $e) {
        $check(VersionUtils::getDBVersion() === '7.8.0', 'A failed file leaves the DB version unchanged');
    }
    file_put_contents($tempRoot . '/retry.sql', 'CREATE TABLE IF NOT EXISTS upgrade_retry (id INT);');
    $check(UpgradeService::upgradeDatabaseVersion(), 'Retry skips completed non-idempotent SQL and restarts the retryable boundary file');

    foreach ([false, null, 'legacy'] as $retryable) {
        $db = $newDatabase();
        $useDatabase($db);
        file_put_contents($tempRoot . '/unsafe.php', '<?php throw new Error("simulated PHP interruption");');
        $script = ['path' => '/unsafe.php'];
        if ($retryable === 'legacy') {
            $script = '/unsafe.php';
        } elseif ($retryable !== null) {
            $script['retryable'] = $retryable;
        }
        $setStep([$script]);
        try {
            UpgradeService::upgradeDatabaseVersion();
        } catch (Error $e) {
            $check($e->getMessage() === 'simulated PHP interruption', 'PHP errors leave a durable started checkpoint');
        }
        file_put_contents($tempRoot . '/unsafe.php', '<?php throw new Error("must not execute");');
        try {
            UpgradeService::upgradeDatabaseVersion();
            throw new RuntimeException('Expected unsafe retry rejection');
        } catch (RuntimeException $e) {
            $check(str_contains($e->getMessage(), '/unsafe.php') && str_contains($e->getMessage(), 'not safe to retry'), 'Unsafe or unflagged interrupted file is rejected by name before execution');
        }
        $check(VersionUtils::getDBVersion() === '7.8.0', 'Unsafe retry does not advance the version');
    }
    $db = $newDatabase();
    $useDatabase($db);
    file_put_contents($tempRoot . '/retry.php', <<<'MIGRATION'
<?php
$GLOBALS['upgradeTestPhpAttempts'] = ($GLOBALS['upgradeTestPhpAttempts'] ?? 0) + 1;
if ($GLOBALS['upgradeTestPhpAttempts'] === 1) {
    throw new Error('retryable PHP interruption');
}
MIGRATION
    );
    $setStep([['path' => '/retry.php', 'retryable' => true]]);
    $lockName = 'churchcrm-upgrade-' . sha1((string) $db->query('SELECT DATABASE()')->fetchColumn());
    $db->query("SELECT GET_LOCK('$lockName', 0)");
    try {
        UpgradeService::upgradeDatabaseVersion();
        throw new RuntimeException('Expected concurrent upgrade rejection');
    } catch (RuntimeException $e) {
        $check(str_contains($e->getMessage(), 'already running'), 'Concurrent upgrade requests cannot execute a file together');
    } finally {
        $db->query("SELECT RELEASE_LOCK('$lockName')");
    }
    try {
        UpgradeService::upgradeDatabaseVersion();
    } catch (Error $e) {
        $check($e->getMessage() === 'retryable PHP interruption', 'Retryable PHP failure is recorded');
    }
    $check(UpgradeService::upgradeDatabaseVersion() && $GLOBALS['upgradeTestPhpAttempts'] === 2, 'Retryable PHP executes again in the same request');
    echo "All database upgrade checks passed.\n";
} finally {
    if (!$sqlOnly) {
        Propel::getServiceContainer()->closeConnections();
    }
    foreach ($schemas as $schema) {
        $admin->exec("DROP DATABASE IF EXISTS `$schema`");
    }
    unlink($tempRoot . '/mysql/upgrade');
    unlink($tempRoot . '/locale');
    foreach (glob($tempRoot . '/mysql/*') as $file) {
        unlink($file);
    }
    rmdir($tempRoot . '/mysql');
    foreach (glob($tempRoot . '/*') as $file) {
        unlink($file);
    }
    rmdir($tempRoot);
}
