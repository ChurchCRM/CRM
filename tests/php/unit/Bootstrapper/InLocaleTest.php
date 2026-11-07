<?php

declare(strict_types=1);

use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use Monolog\Handler\TestHandler;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);

// Without a database the telemetry handler reports its missing settings through error_log().
ini_set('error_log', (string) tempnam(sys_get_temp_dir(), 'crm-in-locale-'));
$logged = new TestHandler();
LoggerUtils::getAppLogger()->pushHandler($logged);

$before = setlocale(LC_ALL, '0');
$beforeLanguage = getenv('LANGUAGE');

Assert::same('built', Bootstrapper::inLocale('xx_NOWHERE', static fn (): string => 'built'), 'a locale the server lacks: the work still runs');
Assert::true($logged->hasWarningThatContains('Failed to set locale'), 'a locale the server lacks: a warning is logged');
Assert::same($before, setlocale(LC_ALL, '0'), 'a locale the server lacks: the request locale is back');

$logged->clear();
Bootstrapper::inLocale('C', static fn (): null => null);
Assert::false($logged->hasWarningRecords(), 'a locale the server has: no warning');
Assert::same($before, setlocale(LC_ALL, '0'), 'a locale the server has: the request locale is back');

Assert::same($beforeLanguage, getenv('LANGUAGE'), 'the request catalog selection is restored');
Assert::exception(static function (): void {
    Bootstrapper::inLocale('C', static function (): void {
        Assert::same('C', getenv('LANGUAGE'));
        throw new RuntimeException('render failed');
    });
}, RuntimeException::class, 'render failed');
Assert::same($beforeLanguage, getenv('LANGUAGE'), 'the catalog selection is restored after a rendering error');
Assert::same($before, setlocale(LC_ALL, '0'), 'the OS locale is restored after a rendering error');
