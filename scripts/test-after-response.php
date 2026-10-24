<?php

/**
 * scripts/test-after-response.php — work deferred until after the response
 * (ChurchCRM\Utils\AfterResponse, member sign-in row 5).
 *
 * Usage:
 *   php scripts/test-after-response.php
 *   npm run test:php
 *
 * On the command line deferred work runs at once. Under a web SAPI it runs at
 * shutdown, after the response has gone out with a Content-Length and
 * `Connection: close`; that half runs the helper under php-cgi when it is
 * installed (no fastcgi_finish_request there, so it takes the mod_php path) and
 * is skipped otherwise. No database, web server or Config.php is needed.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 *   2  — vendor autoloader missing
 */

declare(strict_types=1);

use ChurchCRM\Utils\AfterResponse;
use ChurchCRM\Utils\LoggerUtils;
use Monolog\Handler\TestHandler;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing {$autoload} — run `composer install` in src/ first.\n");
    exit(2);
}
require $autoload;

$passed = 0;
$failed = 0;

$check = function (bool $ok, string $label, string $detail = '') use (&$passed, &$failed): void {
    $n = $passed + $failed + 1;
    if ($ok) {
        $passed++;
        echo "ok {$n} - {$label}\n";
        return;
    }
    $failed++;
    echo "not ok {$n} - {$label}" . ($detail !== '' ? "  # {$detail}" : '') . "\n";
};

$ran = [];
AfterResponse::defer(function () use (&$ran): void {
    $ran[] = 'first';
});
$check($ran === ['first'], 'command line: deferred work runs at once', json_encode($ran));

// The app logger's telemetry handler can't read its settings without a
// database and says so through error_log(); show that as a diagnostic.
$loggerNoise = tempnam(sys_get_temp_dir(), 'crm-after-response-');
$previousErrorLog = ini_set('error_log', $loggerNoise);
$logged = new TestHandler();
LoggerUtils::getAppLogger()->pushHandler($logged);

AfterResponse::defer(function (): void {
    throw new RuntimeException('deferred work failed on purpose');
});
AfterResponse::defer(function () use (&$ran): void {
    $ran[] = 'second';
});
$check($ran === ['first', 'second'], 'command line: a failing callable is caught and the next one runs', json_encode($ran));
$check(
    $logged->hasErrorThatContains('Work deferred until after the response failed'),
    'command line: the failure is logged',
);

LoggerUtils::getAppLogger()->popHandler();
ini_set('error_log', $previousErrorLog === false ? '' : $previousErrorLog);
foreach (array_unique(file($loggerNoise, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) as $line) {
    echo '# logger (expected without a database): ' . preg_replace('/^\[[^\]]*\] /', '', $line) . "\n";
}
unlink($loggerNoise);

$level = ob_get_level();
AfterResponse::finishEarly();
$check(ob_get_level() === $level, 'command line: finishEarly() holds no output back');

$phpCgi = trim((string) shell_exec('command -v php-cgi 2>/dev/null'));
if ($phpCgi === '') {
    echo "ok " . ($passed + $failed + 1) . " - # SKIP php-cgi is not installed\n";
    $passed++;
} else {
    $dir = sys_get_temp_dir() . '/churchcrm-after-response-' . bin2hex(random_bytes(4));
    mkdir($dir);
    $log = $dir . '/work.log';
    file_put_contents($dir . '/page.php', <<<'PHP'
        <?php
        require getenv('AFTER_RESPONSE_AUTOLOAD');
        use ChurchCRM\Utils\AfterResponse;
        $log = getenv('AFTER_RESPONSE_LOG');
        if (getenv('AFTER_RESPONSE_FILTER') === '1') {
            ob_start(static fn (string $output): string => $output);
        }
        session_start();
        AfterResponse::defer(static function () use ($log): void {
            file_put_contents($log, 'first headers_sent=' . var_export(headers_sent(), true) . ' session=' . session_status() . "\n", FILE_APPEND);
        });
        AfterResponse::defer(static function (): void {
            throw new RuntimeException('deferred work failed on purpose');
        });
        AfterResponse::defer(static function () use ($log): void {
            file_put_contents($log, "second\n", FILE_APPEND);
        });
        echo 'the response';
        PHP);

    $page = function (string $outputBuffering, bool $filter) use ($phpCgi, $dir, $log, $autoload): array {
        @unlink($log);
        $process = proc_open(
            [$phpCgi, '-d', 'session.save_path=' . $dir, '-d', 'output_buffering=' . $outputBuffering, $dir . '/page.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $dir,
            [
                'AFTER_RESPONSE_AUTOLOAD' => $autoload,
                'AFTER_RESPONSE_LOG' => $log,
                'AFTER_RESPONSE_FILTER' => $filter ? '1' : '0',
                'PATH' => (string) getenv('PATH'),
            ],
        );
        $output = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        proc_close($process);
        [$headers, $body] = array_pad(preg_split("/\r?\n\r?\n/", $output, 2), 2, '');

        return [$headers, $body, is_file($log) ? (string) file_get_contents($log) : ''];
    };
    $workRanAfter = 'first headers_sent=true session=' . PHP_SESSION_NONE . "\nsecond\n";

    foreach (['0', '4096'] as $outputBuffering) {
        [$headers, $body, $work] = $page($outputBuffering, false);
        $label = "web, output_buffering={$outputBuffering}";
        $check(preg_match('/^Content-Length: 12\r?$/mi', $headers) === 1, "{$label}: the response is sent with its length", $headers);
        $check(preg_match('/^Connection: close\r?$/mi', $headers) === 1, "{$label}: the response closes the connection", $headers);
        $check($body === 'the response', "{$label}: the body is sent whole", json_encode($body));
        $check($work === $workRanAfter, "{$label}: the work runs in order after the response, with the session closed; a failure stops nothing", json_encode($work));
    }

    [$headers, $body, $work] = $page('0', true);
    $check(preg_match('/^Content-Length:/mi', $headers) === 0, 'web, output filter: the length is left to the server', $headers);
    $check($body === 'the response', 'web, output filter: the body is sent whole', json_encode($body));
    $check(str_ends_with($work, "second\n") && substr_count($work, "\n") === 2, 'web, output filter: the work still runs, in order', json_encode($work));

    array_map('unlink', glob($dir . '/*') ?: []);
    rmdir($dir);
}

$total = $passed + $failed;
echo "1..{$total}\n";
echo "# {$total} cases, {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
