<?php

declare(strict_types=1);

use Propel\Runtime\Propel;
use ChurchCRM\Service\UpgradeService;
use ChurchCRM\Utils\VersionUtils;

$root = dirname(__DIR__, 3);
$dsn = getenv('UPGRADE_BENCHMARK_DSN') ?: '';
$user = getenv('UPGRADE_BENCHMARK_USER') ?: '';
$password = getenv('UPGRADE_BENCHMARK_PASSWORD') ?: '';
$startVersion = getenv('UPGRADE_BENCHMARK_START_VERSION') ?: '1.3.1';
$resume = getenv('UPGRADE_BENCHMARK_RESUME') === '1';
$stopAt = getenv('UPGRADE_BENCHMARK_STOP_AT') ?: '';
if ($dsn === '' || $user === '') {
    throw new RuntimeException('Set UPGRADE_BENCHMARK_DSN and UPGRADE_BENCHMARK_USER.');
}

$db = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (!$resume && $db->query('SHOW TABLES')->fetch(PDO::FETCH_NUM) !== false) {
    throw new RuntimeException('The benchmark database must be empty.');
}

require $root . '/src/vendor/autoload.php';
require_once $root . '/src/ChurchCRM/Utils/SQLUtils.php';
$host = getenv('UPGRADE_BENCHMARK_HOST') ?: '127.0.0.1';
$port = (int) (getenv('UPGRADE_BENCHMARK_PORT') ?: '3306');
$mysqli = new mysqli($host, $user, $password, (string) $db->query('SELECT DATABASE()')->fetchColumn(), $port);
if (!$resume) {
    ChurchCRM\Utils\SQLUtils::sqlImport($root . '/cypress/fixtures/upgrade/churchinfo-1.3.1.sql', $mysqli);
}

$config = $root . '/src/Include/Config.php';
if (file_exists($config)) {
    throw new RuntimeException('Remove src/Include/Config.php before benchmarking; the harness will create and remove its temporary config.');
}
$dockerConfig = file_get_contents($root . '/docker/Config.php');
$dockerConfig = str_replace("$" . "sSERVERNAME = 'database';", "$" . "sSERVERNAME = '$host';", $dockerConfig);
$dockerConfig = str_replace("$" . "dbPort = '3306';", "$" . "dbPort = '$port';", $dockerConfig);
$dockerConfig = str_replace("$" . "sUSER = 'churchcrm';", "$" . "sUSER = '$user';", $dockerConfig);
$dockerConfig = str_replace("$" . "sPASSWORD = 'changeme';", "$" . "sPASSWORD = '$password';", $dockerConfig);
$databaseName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$dockerConfig = str_replace("$" . "sDATABASE = 'churchcrm';", "$" . "sDATABASE = '$databaseName';", $dockerConfig);
file_put_contents($config, $dockerConfig, LOCK_EX);
register_shutdown_function(static function () use ($config): void {
    @unlink($config);
});

if (!$resume) {
    // Legacy Config rows have extra required fields; seed current bootstrap settings so
    // the current app can initialize without writing to that old shape.
    $nextConfigId = (int) $db->query('SELECT COALESCE(MAX(cfg_id), 0) + 1 FROM config_cfg')->fetchColumn();
    $seedConfig = $db->prepare('INSERT INTO config_cfg (cfg_id, cfg_name, cfg_value, cfg_type, cfg_default, cfg_tooltip, cfg_section) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ([['sTwoFASecretKey', 'benchmark-key'], ['sSystemID', 'benchmark-system']] as [$name, $value]) {
        $seedConfig->execute([$nextConfigId++, $name, $value, 'text', '', '', 'General']);
    }
}
$db->prepare('UPDATE version_ver SET ver_version = ? WHERE ver_ID = (SELECT max_id FROM (SELECT MAX(ver_ID) AS max_id FROM version_ver) AS latest)')->execute([json_decode(file_get_contents($root . '/package.json'), true, 512, JSON_THROW_ON_ERROR)['version']]);

require $config;
$connection = Propel::getConnection();
printf("DATABASE\t%s\n", $connection->query('SELECT DATABASE()')->fetchColumn());
$connection->prepare('UPDATE version_ver SET ver_version = ? WHERE ver_ID = (SELECT max_id FROM (SELECT MAX(ver_ID) AS max_id FROM version_ver) AS latest)')->execute([$startVersion]);

$journal = "CREATE TABLE IF NOT EXISTS database_upgrade_progress (
    target_version VARCHAR(50) NOT NULL,
    script_path VARCHAR(255) NOT NULL,
    status VARCHAR(10) NOT NULL,
    started_at DATETIME NOT NULL,
    PRIMARY KEY (target_version, script_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$connection->exec($journal);

$registry = json_decode(file_get_contents($root . '/src/mysql/upgrade.json'), true, 512, JSON_THROW_ON_ERROR);
$currentVersion = $startVersion;
foreach ($registry as $block) {
    if (!in_array($currentVersion, $block['versions'], true)) {
        continue;
    }
    foreach ($block['scripts'] as $scriptPath) {
        $progress = $connection->prepare('SELECT status FROM database_upgrade_progress WHERE target_version = ? AND script_path = ?');
        $progress->execute([$block['dbVersion'], $scriptPath]);
        $checkpoint = $progress->fetch(PDO::FETCH_ASSOC);
        $status = $checkpoint['status'] ?? null;
        if ($status === 'complete') {
            continue;
        }
        if ($status !== null && $status !== '') {
            throw new RuntimeException("Script $scriptPath has a started journal entry; reset the disposable database.");
        }
        $connection->prepare('INSERT INTO database_upgrade_progress (target_version, script_path, status, started_at) VALUES (?, ?, ?, NOW())')->execute([$block['dbVersion'], $scriptPath, 'started']);
        $path = $root . '/src' . $scriptPath;
        $fileStarted = hrtime(true);
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'sql') {
            foreach (splitStatements(file_get_contents($path)) as $index => $statement) {
                $statementStarted = hrtime(true);
                $connection->exec($statement);
                printf("STATEMENT\t%s\t%d\t%.6f\t%s\n", $scriptPath, $index + 1, (hrtime(true) - $statementStarted) / 1e9, summarize($statement));
            }
        } elseif (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
            require $path;
        } else {
            throw new RuntimeException("Unsupported upgrade file: $scriptPath");
        }
        $connection->prepare('UPDATE database_upgrade_progress SET status = ? WHERE target_version = ? AND script_path = ?')->execute(['complete', $block['dbVersion'], $scriptPath]);
        printf("FILE\t%s\t%.6f\n", $scriptPath, (hrtime(true) - $fileStarted) / 1e9);

        if ($scriptPath === '/mysql/upgrade/7.8.0.sql') {
            putenv('UPGRADE_BENCHMARK_HOST=' . $host);
            putenv('UPGRADE_BENCHMARK_PORT=' . $port);
            putenv('UPGRADE_BENCHMARK_USER=' . $user);
            putenv('UPGRADE_BENCHMARK_PASSWORD=' . $password);
            putenv('UPGRADE_BENCHMARK_DATABASE=' . $databaseName);
            require __DIR__ . '/benchmark-large-dataset.php';
        }
    }
    $now = date('Y-m-d H:i:s');
    $connection->prepare('INSERT INTO version_ver (ver_version, ver_update_start, ver_update_end) VALUES (?, ?, ?)')->execute([$block['dbVersion'], $now, $now]);
    $currentVersion = $block['dbVersion'];
    if ($stopAt !== '' && $currentVersion === $stopAt) {
        echo "CHECKPOINT_READY\t$currentVersion\n";
        exit(0);
    }
}
VersionUtils::resetCache();
if (!UpgradeService::upgradeDatabaseVersion(false)) {
    throw new RuntimeException('UpgradeService did not recognize the completed benchmark schema.');
}
$people = (int) $connection->query('SELECT COUNT(*) FROM person_per')->fetchColumn();
$families = (int) $connection->query('SELECT COUNT(*) FROM family_fam')->fetchColumn();
if ($people !== 25000 || $families !== 9000) {
    throw new RuntimeException("Post-upgrade smoke check found $families families and $people people.");
}
echo "SMOKE\tUpgradeService current-version check and core table counts passed\n";
printf("CHAIN_COMPLETE\t%s\n", $currentVersion);

/** @return list<string> */
function splitStatements(string $sql): array
{
    $sql = preg_replace('~/\*![\s\S]*?\*/~', '', $sql);
    $sql = preg_replace('~/\*[\s\S]*?\*/~', '', $sql);
    $sql = preg_replace('/^\s*--[^\r\n]*/m', '', $sql);
    $statements = [];
    $buffer = '';
    $quote = null;
    $escaped = false;
    for ($i = 0, $length = strlen($sql); $i < $length; $i++) {
        $char = $sql[$i];
        if ($quote !== null) {
            $buffer .= $char;
            if ($escaped) {
                $escaped = false;
            } elseif ($char === "\\") {
                $escaped = true;
            } elseif ($char === $quote) {
                if (($sql[$i + 1] ?? '') === $quote) {
                    $buffer .= $sql[++$i];
                } else {
                    $quote = null;
                }
            }
            continue;
        }
        if (in_array($char, ["'", '"', '`'], true)) {
            $quote = $char;
            $buffer .= $char;
        } elseif ($char === ';') {
            if (trim($buffer) !== '') {
                $statements[] = trim($buffer);
            }
            $buffer = '';
        } else {
            $buffer .= $char;
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }
    return $statements;
}

function summarize(string $sql): string
{
    return str_replace("\t", ' ', preg_replace('/\s+/', ' ', substr(trim($sql), 0, 220)));
}
