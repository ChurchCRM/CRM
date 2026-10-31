<?php

/**
 * scripts/test-in-locale.php — text built in another person's language
 * (ChurchCRM\Bootstrapper::inLocale(), member sign-in member language).
 *
 * Usage:
 *   php scripts/test-in-locale.php
 *   npm run test:php
 *
 * No database, web server or Config.php is needed.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 *   2  — vendor autoloader missing
 */

declare(strict_types=1);

use ChurchCRM\Bootstrapper;
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

// The app logger's telemetry handler can't read its settings without a
// database and says so through error_log(); keep that out of the output.
$loggerNoise = tempnam(sys_get_temp_dir(), 'crm-in-locale-');
$previousErrorLog = ini_set('error_log', $loggerNoise);
$logged = new TestHandler();
LoggerUtils::getAppLogger()->pushHandler($logged);

$before = setlocale(LC_ALL, '0');

$result = Bootstrapper::inLocale('xx_NOWHERE', static fn (): string => 'built');
$check($result === 'built', 'a locale the server lacks: the work still runs');
$check(
    $logged->hasWarningThatContains('Failed to set locale'),
    'a locale the server lacks: a warning is logged',
);
$check(setlocale(LC_ALL, '0') === $before, 'a locale the server lacks: the request locale is back', (string) setlocale(LC_ALL, '0'));

$logged->clear();
Bootstrapper::inLocale('C', static fn (): null => null);
$check(!$logged->hasWarningRecords(), 'a locale the server has: no warning');
$check(setlocale(LC_ALL, '0') === $before, 'a locale the server has: the request locale is back', (string) setlocale(LC_ALL, '0'));

LoggerUtils::getAppLogger()->popHandler();
ini_set('error_log', $previousErrorLog === false ? '' : $previousErrorLog);
unlink($loggerNoise);

$total = $passed + $failed;
echo "1..{$total}\n";
echo "# {$total} cases, {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
