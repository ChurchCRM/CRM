<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$directory = sys_get_temp_dir() . '/crm-requirements-' . bin2hex(random_bytes(6));
mkdir($directory . '/Include', 0700, true);
mkdir($directory . '/setup/templates', 0700, true);
symlink(APP_ROOT . '/vendor', $directory . '/vendor');
$files = ['Include/LoadConfigs.php', 'Include/CheckRequirements.php', 'setup/index.php', 'setup/templates/system-requirements.php'];
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
    foreach ([false, true] as $configured) {
        if ($configured) {
            file_put_contents($directory . '/Include/Config.php', '<?php throw new RuntimeException("Configuration must not load");');
        }
        foreach (['', '/churchcrm', '/apps/churchcrm'] as $root) {
            foreach (['Include/LoadConfigs.php' => '/session/index.php', 'setup/index.php' => '/setup/index.php'] as $entry => $scriptName) {
                $script = 'namespace ChurchCRM\Service; '
                    . 'function class_exists($name) { return $name === "ZipArchive" ? false : \\class_exists($name); } '
                    . '$_SERVER["SCRIPT_NAME"] = ' . var_export($root . $scriptName, true) . '; '
                    . 'register_shutdown_function(static function () { if (http_response_code() !== 503) { exit(1); } }); '
                    . 'require ' . var_export($directory . '/' . $entry, true) . '; '
                    . 'throw new \\RuntimeException("Bootstrap must stop");';
                $html = $run($script, 'gettext,bindtextdomain,imagecreatetruecolor');
                Assert::contains('ChurchCRM System Check', $html);
                Assert::contains('href="' . $root . '/skin/v2/setup.min.css"', $html);
                foreach (['locale gettext', 'GD Library for image manipulation', 'PHP ZipArchive'] as $name) {
                    Assert::match('~' . preg_quote($name, '~') . '</th>\s*<td class="text-danger">Failed</td>~', $html);
                }
                Assert::contains('PHP Session', $html);
                Assert::notContains('Fatal error', $html);
                Assert::notContains('<form', $html);
                Assert::same($configured, file_exists($directory . '/Include/Config.php'));
            }
        }
    }

    $healthy = $run('require ' . var_export(APP_ROOT . '/vendor/autoload.php', true) . '; '
        . 'require ' . var_export($directory . '/Include/CheckRequirements.php', true) . '; echo "continue";');
    Assert::same('continue', $healthy);
} finally {
    foreach (array_merge($files, ['Include/Config.php', 'vendor']) as $file) {
        if (file_exists($directory . '/' . $file)) {
            unlink($directory . '/' . $file);
        }
    }
    rmdir($directory . '/setup/templates');
    rmdir($directory . '/setup');
    rmdir($directory . '/Include');
    rmdir($directory);
}
