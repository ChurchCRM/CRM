<?php

/**
 * Run on a disposable checkout with no Include/Config.php, generated ORM models,
 * and UPGRADE_TEST_DSN/USER/PASSWORD for a disposable MySQL or MariaDB server.
 * Temporarily replaces upgrade.json and writes Config.php; restores both on exit.
 */
declare(strict_types=1);

use ChurchCRM\Utils\SQLUtils;

require __DIR__ . '/../src/vendor/autoload.php';
$root = dirname(__DIR__) . '/src';
$dsn = getenv('UPGRADE_TEST_DSN');
if (!$dsn || str_contains($dsn, 'dbname=')) { throw new RuntimeException('Set UPGRADE_TEST_DSN without a database name.'); }
if (is_file($root . '/Include/Config.php')) { throw new RuntimeException('Use a disposable checkout without Include/Config.php.'); }
$user = getenv('UPGRADE_TEST_USER') ?: 'root';
$password = getenv('UPGRADE_TEST_PASSWORD') ?: '';
if ($password === '') { throw new RuntimeException('Use a nonempty disposable database password for Config.php validation.'); }
$admin = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$manifestPath = $root . '/mysql/upgrade.json';
$manifest = file_get_contents($manifestPath);
$temp = sys_get_temp_dir() . '/crm-upgrade-http-' . bin2hex(random_bytes(6));
mkdir($temp);
$schemas = [];
$scriptPaths = [];
$server = null;
$check = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
};
$router = $temp . '/router.php';
file_put_contents($router, '<?php $_SERVER["SCRIPT_NAME"] = getenv("UPGRADE_TEST_PREFIX") . "/external/index.php"; if (str_ends_with($_SERVER["REQUEST_URI"], "/legacy-deploy")) { require ' . var_export($temp . '/legacy.php', true) . '; } elseif (str_contains($_SERVER["REQUEST_URI"], "/external/")) { require ' . var_export($root . '/external/index.php', true) . '; } else { require ' . var_export($root . '/Include/LoadConfigs.php', true) . '; echo "Application ready"; }');
$request = static function (string $path) use ($temp): array {
    $headers = [];
    $curl = curl_init('http://127.0.0.1:8097' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => '', CURLOPT_COOKIEFILE => $temp . '/cookies', CURLOPT_COOKIEJAR => $temp . '/cookies', CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
        if (str_contains($line, ':')) { [$name, $value] = explode(':', $line, 2); $headers[strtolower(trim($name))] = trim($value); }
        return strlen($line);
    }]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($body === false) { throw new RuntimeException(curl_error($curl)); }
    curl_close($curl);
    return [$status, $headers, $body];
};
try {
    foreach (['', '/churchcrm'] as $prefix) {
        $schema = 'upgrade_test_' . bin2hex(random_bytes(6));
        $schemas[] = $schema;
        $admin->exec("CREATE DATABASE `$schema`");
        $db = new PDO($dsn . ';dbname=' . $schema, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        SQLUtils::sqlImport($root . '/mysql/install/Install.sql', $db);
        $db->exec("INSERT INTO version_ver (ver_version) VALUES ('7.8.0')");
        $db->exec('CREATE TABLE request_runs (id INT PRIMARY KEY)');
        $db->exec('ALTER TABLE config_cfg ADD COLUMN cfg_test_required TEXT NOT NULL');
        $scripts = [];
        for ($i = 0; $i < 30; $i++) {
            $path = '/mysql/upgrade/request-test-' . basename($temp) . '-' . $i . '.sql';
            $scriptPaths[] = $root . $path;
            $scripts[] = $path;
            file_put_contents($root . $path, "INSERT INTO request_runs VALUES ($i);\n" . ($i === 29 ? 'ALTER TABLE config_cfg DROP COLUMN cfg_test_required;' : ''));
        }
        $installed = json_decode(file_get_contents($root . '/composer.json'), true)['version'];
        file_put_contents($manifestPath, json_encode([
            ['versions' => ['7.8.0'], 'scripts' => array_slice($scripts, 0, 3), 'dbVersion' => '7.8.1'],
            ['versions' => ['7.8.1'], 'scripts' => array_slice($scripts, 3), 'dbVersion' => $installed],
        ], JSON_THROW_ON_ERROR));
        preg_match('/host=([^;]+)/', $dsn, $host);
        preg_match('/port=(\d+)/', $dsn, $port);
        $config = ['sSERVERNAME' => $host[1], 'dbPort' => $port[1] ?? '3306', 'sUSER' => $user, 'sPASSWORD' => $password, 'sDATABASE' => $schema, 'sRootPath' => $prefix, 'URL' => ['http://127.0.0.1:8097' . $prefix . '/']];
        $php = '<?php error_reporting(E_ERROR); $debugBootstrapper = false;';
        foreach ($config as $key => $value) { $php .= '$' . $key . '=' . var_export($value, true) . ';'; }
        file_put_contents($root . '/Include/Config.php', $php);
        $legacy = '<?php require ' . var_export($root . '/vendor/autoload.php', true) . ';';
        $legacy .= '\ChurchCRM\dto\SystemURLs::init(' . var_export($prefix, true) . ', [], ' . var_export($root, true) . ');';
        $legacy .= '\ChurchCRM\dto\SystemConfig::init(); session_start();';
        $legacy .= '$container = \Propel\Runtime\Propel::getServiceContainer(); $container->setAdapterClass("default", "mysql");';
        $legacy .= '$manager = new \Propel\Runtime\Connection\ConnectionManagerSingle("default");';
        $legacy .= '$manager->setConfiguration(' . var_export(['dsn' => $dsn . ';dbname=' . $schema, 'user' => $user, 'password' => $password], true) . ');';
        $legacy .= '$container->setConnectionManager($manager); $container->setDefaultDatasource("default");';
        $legacy .= 'echo json_encode(["complete" => \ChurchCRM\Service\UpgradeService::upgradeDatabaseVersion(), "pending" => $_SESSION["dbUpgradePending"] ?? false]);';
        file_put_contents($temp . '/legacy.php', $legacy);
        putenv('UPGRADE_TEST_PREFIX=' . $prefix);
        $server = proc_open([PHP_BINARY, '-n', '-d', 'session.save_path=' . $temp, '-c', php_ini_loaded_file() ?: '', '-d', 'extension_dir=' . ini_get('extension_dir'), '-d', 'extension=mysqlnd', '-d', 'extension=pdo', '-d', 'extension=pdo_mysql', '-d', 'extension=mysqli', '-d', 'extension=mbstring', '-d', 'extension=intl', '-d', 'extension=gettext', '-d', 'extension=iconv', '-d', 'extension=curl', '-d', 'extension=ctype', '-d', 'extension=tokenizer', '-S', '127.0.0.1:8097', '-t', $root, $router], [0 => ['pipe', 'r'], 1 => ['file', $temp . '/server.log', 'a'], 2 => ['file', $temp . '/server.log', 'a']], $pipes);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $socket = @fsockopen('127.0.0.1', 8097);
            if ($socket) { fclose($socket); break; }
            usleep(20000);
        }
        [$status, $headers, $body] = $request($prefix . '/legacy-deploy');
        $check($status === 200 && json_decode($body, true) === ['complete' => false, 'pending' => true], 'An older deployment caller defers database work to a fresh request');
        $check((int) $db->query('SELECT COUNT(*) FROM request_runs')->fetchColumn() === 0, 'Deployment request executes no database scripts');
        $path = $prefix . '/';
        for ($i = 0; $i < 30; $i++) {
            [$status, $headers, $body] = $request($path);
            if ((int) $db->query('SELECT COUNT(*) FROM request_runs')->fetchColumn() !== $i + 1) { fwrite(STDERR, json_encode([$status, $headers, substr($body, 0, 2500)]) . "\n"); }
            $check((int) $db->query('SELECT COUNT(*) FROM request_runs')->fetchColumn() === $i + 1, "$prefix request $i runs one file");
            if ($i === 0) {
                $check($status === 302 && $headers['location'] === $prefix . '/external/system/db-upgrade', 'Initial request redirects into the upgrade continuation');
            } elseif ($i < 29) {
                $check($status === 200 && str_contains($headers['refresh'] ?? '', $prefix . '/external/system/db-upgrade'), 'Continuation returns HTTP 200 with refresh, avoiding redirect-chain limits');
                $check(str_contains($body, 'keep this page open') && !str_contains($body, 'Application ready'), 'Partial upgrades show progress and block the application');
            } else {
                $check($status === 303 && $headers['location'] === $prefix . '/session/end', 'Completed upgrade sends the browser through logout');
            }
            $path = $prefix . '/external/system/db-upgrade';
        }
        $check((string) $db->query('SELECT ver_version FROM version_ver ORDER BY ver_ID DESC LIMIT 1')->fetchColumn() === $installed, 'Real HTTP continuation advances the database to the installed version');
        $db->exec("DELETE FROM version_ver");
        $db->exec("INSERT INTO version_ver (ver_version) VALUES ('7.8.0')");
        $db->exec('DELETE FROM database_upgrade_progress');
        $db->exec('DELETE FROM request_runs');
        $failedPath = '/mysql/upgrade/request-test-' . basename($temp) . '-failure.sql';
        $scriptPaths[] = $root . $failedPath;
        file_put_contents($root . $failedPath, 'INSERT INTO request_runs VALUES (77); SELECT missing_upgrade_column;');
        file_put_contents($manifestPath, json_encode([['versions' => ['7.8.0'], 'scripts' => [$failedPath], 'dbVersion' => $installed]], JSON_THROW_ON_ERROR));
        [$status, $headers, $body] = $request($path);
        $check($status === 302 && $headers['location'] === $path, 'A failed file redirects once to its error page');
        for ($i = 0; $i < 2; $i++) {
            [$status, $headers, $body] = $request($path);
            $check($status === 200 && !isset($headers['refresh']), 'Failure page stays put without automatic retries');
            $check(str_contains($body, 'Database Upgrade Failed') && !str_contains($body, 'missing_upgrade_column'), 'Failure page shows a safe error without SQL details');
            $check((int) $db->query('SELECT COUNT(*) FROM request_runs')->fetchColumn() === 1, 'Reloading the failure page cannot repeat partial DML');
        }
        proc_terminate($server); proc_close($server); $server = null;
        unlink($root . '/Include/Config.php');
        unlink($temp . '/cookies');
    }
    echo "All HTTP upgrade checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, json_encode([$status ?? null, $headers ?? [], substr($body ?? '', 0, 3500)]) . "\n");
    fwrite(STDERR, file_get_contents($temp . '/server.log'));
    throw $e;
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    file_put_contents($manifestPath, $manifest);
    if (is_file($root . '/Include/Config.php')) { unlink($root . '/Include/Config.php'); }
    foreach (array_unique($scriptPaths) as $path) { unlink($path); }
    foreach ($schemas as $schema) { $admin->exec("DROP DATABASE `$schema`"); }
    foreach (glob($temp . '/*') as $path) { unlink($path); }
    rmdir($temp);
}
