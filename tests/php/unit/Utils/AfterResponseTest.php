<?php

declare(strict_types=1);

use ChurchCRM\Utils\AfterResponse;
use ChurchCRM\Utils\LoggerUtils;
use Monolog\Handler\TestHandler;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$ran = [];
AfterResponse::defer(function () use (&$ran): void {
    $ran[] = 'first';
});
Assert::same(['first'], $ran, 'command line: deferred work runs at once');

// Without a database the telemetry handler reports its missing settings through error_log().
ini_set('error_log', (string) tempnam(sys_get_temp_dir(), 'crm-after-response-'));
$logged = new TestHandler();
LoggerUtils::getAppLogger()->pushHandler($logged);

AfterResponse::defer(function (): void {
    throw new RuntimeException('deferred work failed on purpose');
});
AfterResponse::defer(function () use (&$ran): void {
    $ran[] = 'second';
});
Assert::same(['first', 'second'], $ran, 'command line: a failing callable is caught and the next one runs');
Assert::true($logged->hasErrorThatContains('Work deferred until after the response failed'), 'command line: the failure is logged');
LoggerUtils::getAppLogger()->popHandler();

$level = ob_get_level();
AfterResponse::finishEarly();
Assert::same($level, ob_get_level(), 'command line: finishEarly() holds no output back');

// The web half runs under php-cgi, which has no fastcgi_finish_request and so takes the mod_php path.
$phpCgi = trim((string) shell_exec('command -v php-cgi 2>/dev/null'));
if ($phpCgi === '') {
    return;
}

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

$page = function (string $outputBuffering, bool $filter) use ($phpCgi, $dir, $log): array {
    @unlink($log);
    $process = proc_open(
        [$phpCgi, '-d', 'session.save_path=' . $dir, '-d', 'output_buffering=' . $outputBuffering, $dir . '/page.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $dir,
        [
            'AFTER_RESPONSE_AUTOLOAD' => APP_ROOT . '/vendor/autoload.php',
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

try {
    $workRanAfter = 'first headers_sent=true session=' . PHP_SESSION_NONE . "\nsecond\n";
    foreach (['0', '4096'] as $outputBuffering) {
        [$headers, $body, $work] = $page($outputBuffering, false);
        $label = "web, output_buffering={$outputBuffering}";
        Assert::same(1, preg_match('/^Content-Length: 12\r?$/mi', $headers), "{$label}: the response is sent with its length");
        Assert::same(1, preg_match('/^Connection: close\r?$/mi', $headers), "{$label}: the response closes the connection");
        Assert::same('the response', $body, "{$label}: the body is sent whole");
        Assert::same($workRanAfter, $work, "{$label}: the work runs in order after the response, with the session closed; a failure stops nothing");
    }

    [$headers, $body, $work] = $page('0', true);
    Assert::same(0, preg_match('/^Content-Length:/mi', $headers), 'web, output filter: the length is left to the server');
    Assert::same('the response', $body, 'web, output filter: the body is sent whole');
    Assert::true(str_ends_with($work, "second\n") && substr_count($work, "\n") === 2, 'web, output filter: the work still runs, in order');
} finally {
    array_map('unlink', glob($dir . '/*') ?: []);
    rmdir($dir);
}
