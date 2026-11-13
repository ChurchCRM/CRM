<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$directory = sys_get_temp_dir() . '/crm-requirements-' . bin2hex(random_bytes(6));
mkdir($directory . '/Include', 0700, true);
mkdir($directory . '/errors', 0700, true);
symlink(APP_ROOT . '/vendor', $directory . '/vendor');
$files = ['Include/LoadConfigs.php', 'Include/CheckRequirements.php', 'errors/system-requirements.php', 'errors/template.php', 'composer.json'];
foreach ($files as $file) {
    copy(APP_ROOT . '/' . $file, $directory . '/' . $file);
}

$run = static function (string $script, string $disabledFunctions = ''): string {
    $process = proc_open([PHP_BINARY, '-d', 'disable_functions=' . $disabledFunctions, '-r', $script], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes);
    Assert::true(is_resource($process));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    Assert::same(0, proc_close($process), $errors);
    Assert::same('', $errors);

    return $output;
};

try {
    foreach (['', '/churchcrm', '/apps/churchcrm'] as $root) {
        file_put_contents($directory . '/Include/Config.php', '<?php '
            . '$sSERVERNAME = "localhost"; $dbPort = 3306; $sUSER = "test"; $sPASSWORD = "secret"; $sDATABASE = "test"; '
            . '$sRootPath = ' . var_export($root, true) . '; $URL = ["http://localhost/"];');
        foreach (['Include/LoadConfigs.php' => '/session/index.php'] as $entry => $scriptName) {
            $script = 'namespace ChurchCRM; class Bootstrapper { public static function init(...$args) { \\ChurchCRM\\dto\\SystemConfig::init(); } } '
                . 'namespace ChurchCRM\Service; '
                . 'function class_exists($name) { return $name === "ZipArchive" ? false : \\class_exists($name); } '
                . '$_SERVER["SCRIPT_NAME"] = ' . var_export($root . $scriptName, true) . '; '
                . 'register_shutdown_function(static function () { if (http_response_code() !== 503) { exit(1); } }); '
                . 'require ' . var_export($directory . '/' . $entry, true) . '; '
                . 'throw new \\RuntimeException("Bootstrap must stop");';
            $html = $run($script, 'gettext,bindtextdomain,imagecreatetruecolor');
            Assert::contains('ChurchCRM System Check', $html);
            Assert::contains('href="' . $root . '/skin/v2/error.min.css"', $html);
            Assert::notContains('cdn.jsdelivr.net', $html);
            foreach (['locale gettext', 'GD Library for image manipulation', 'PHP ZipArchive'] as $name) {
                Assert::match('~' . preg_quote($name, '~') . '</th>\s*<td class="text-danger">Failed</td>~', $html);
            }
            Assert::contains('PHP Session', $html);
            Assert::notContains('Fatal error', $html);
            Assert::notContains('<form', $html);
            Assert::true(file_exists($directory . '/Include/Config.php'));
        }
    }

    file_put_contents($directory . '/Include/Config.php', '<?php '
        . '$sSERVERNAME = "localhost"; $dbPort = 3306; $sUSER = "test"; $sPASSWORD = "secret"; $sDATABASE = "test"; '
        . '$sRootPath = "/churchcrm"; $URL = ["http://localhost/"];');
    foreach (['pdo_mysql' => 'PHP PDO MySQL', 'filter' => 'PHP Filter', 'zlib' => 'PHP Zlib'] as $extension => $name) {
        $missing = 'namespace ChurchCRM; class Bootstrapper { public static function init(...$args) { throw new \\Error("Bootstrap failed"); } } '
            . 'namespace ChurchCRM\\Service; '
            . 'function extension_loaded($name) { return $name === ' . var_export($extension, true) . ' ? false : \\extension_loaded($name); } '
            . '$_SERVER["SCRIPT_NAME"] = "/churchcrm/session/index.php"; '
            . 'register_shutdown_function(static function () { if (http_response_code() !== 503) { exit(1); } }); '
            . 'require ' . var_export($directory . '/Include/LoadConfigs.php', true) . '; '
            . 'throw new \\RuntimeException("Bootstrap must stop");';
        $html = $run($missing);
        Assert::match('~' . preg_quote($name, '~') . '</th>\s*<td class="text-danger">Failed</td>~', $html);
        Assert::match('~PHP Session</th>\s*<td class="text-success">Passed</td>~', $html);
    }

    $api = $run('namespace ChurchCRM; class Bootstrapper { public static function init(...$args) { throw new \\Error("Bootstrap failed"); } } '
        . 'namespace ChurchCRM\\Service; function class_exists($name) { return $name === "ZipArchive" ? false : \\class_exists($name); } '
        . '$_SERVER["SCRIPT_NAME"] = "/api/index.php"; $_SERVER["REQUEST_URI"] = "/api/events/today"; '
        . 'register_shutdown_function(static function () { if (http_response_code() !== 503) { exit(1); } }); '
        . 'require ' . var_export($directory . '/Include/LoadConfigs.php', true) . '; '
        . 'throw new \\RuntimeException("Bootstrap must stop");');
    $payload = json_decode($api, true, 512, JSON_THROW_ON_ERROR);
    Assert::false($payload['success']);
    Assert::same(503, $payload['code']);
    Assert::contains('PHP ZipArchive', $payload['failedRequirements']);
    Assert::notContains('PHP Session', $payload['failedRequirements']);

    file_put_contents($directory . '/Include/Config.php', '<?php '
        . '$sSERVERNAME = "localhost"; $dbPort = 3306; $sUSER = "test"; $sPASSWORD = "secret"; $sDATABASE = "test"; '
        . '$sRootPath = "/api/churchcrm"; $URL = ["http://localhost/"];');
    $prefixed = $run('namespace ChurchCRM; class Bootstrapper { public static function init(...$args) { throw new \\Error("Bootstrap failed"); } } '
        . 'namespace ChurchCRM\\Service; function class_exists($name) { return $name === "ZipArchive" ? false : \\class_exists($name); } '
        . '$_SERVER["SCRIPT_NAME"] = "/api/churchcrm/session/index.php"; $_SERVER["REQUEST_URI"] = "/api/churchcrm/session/begin"; '
        . 'register_shutdown_function(static function () { if (http_response_code() !== 503) { exit(1); } }); '
        . 'require ' . var_export($directory . '/Include/LoadConfigs.php', true) . '; '
        . 'throw new \\RuntimeException("Bootstrap must stop");');
    Assert::contains('ChurchCRM System Check', $prefixed);
    Assert::contains('href="/api/churchcrm/skin/v2/error.min.css"', $prefixed);

    $installRoot = $run('namespace ChurchCRM; class Bootstrapper { public static function init(...$args) { throw new \\Error("Bootstrap failed"); } } '
        . 'namespace ChurchCRM\\Service; function class_exists($name) { return $name === "ZipArchive" ? false : \\class_exists($name); } '
        . '$_SERVER["SCRIPT_NAME"] = "/api/churchcrm/index.php"; $_SERVER["REQUEST_URI"] = "/api/churchcrm"; '
        . 'register_shutdown_function(static function () { if (http_response_code() !== 503) { exit(1); } }); '
        . 'require ' . var_export($directory . '/Include/LoadConfigs.php', true) . '; '
        . 'throw new \\RuntimeException("Bootstrap must stop");');
    Assert::contains('ChurchCRM System Check', $installRoot);

    $healthy = $run('namespace ChurchCRM; class Bootstrapper { public static function init(...$args) {} public static function isDBCurrent() { return false; } } '
        . 'namespace ChurchCRM\\Service; function extension_loaded($name) { throw new \\Error("Requirements must not run"); } '
        . 'require ' . var_export($directory . '/Include/LoadConfigs.php', true) . '; echo "continue";');
    Assert::same('continue', $healthy);

    $unrelated = $run('namespace ChurchCRM; class Bootstrapper { public static function init(...$args) { throw new \\Error("Original failure"); } } '
        . 'try { require ' . var_export($directory . '/Include/LoadConfigs.php', true) . '; } '
        . 'catch (\\Error $error) { echo $error->getMessage(); }');
    Assert::same('Original failure', $unrelated);
} finally {
    foreach (array_merge($files, ['Include/Config.php', 'vendor']) as $file) {
        if (file_exists($directory . '/' . $file)) {
            unlink($directory . '/' . $file);
        }
    }
    rmdir($directory . '/errors');
    rmdir($directory . '/Include');
    rmdir($directory);
}
