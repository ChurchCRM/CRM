<?php

declare(strict_types=1);

use ChurchCRM\dto\LocaleInfo;
use ChurchCRM\dto\SystemURLs;
use Tester\Assert;
use Tester\Environment;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);

$hasEnglish = setlocale(LC_ALL, 'en_US.UTF-8', 'en_US.utf8', 'en_GB.UTF-8', 'en_GB.utf8') !== false;

putenv('LANGUAGE');
Assert::same('C', (new LocaleInfo('C', null))->activate(), 'an installed OS locale is returned as set');
Assert::same('C', getenv('LANGUAGE'));

$locale = 'pt_PT';
if (setlocale(LC_ALL, $locale, $locale . '.UTF-8', $locale . '.utf8') !== false) {
    Environment::skip("{$locale} is installed on this host, so the missing-locale path cannot run.");
}
setlocale(LC_ALL, 'C');

Assert::false((new LocaleInfo($locale, null))->activate(), 'a missing OS locale is reported');
Assert::same($locale, getenv('LANGUAGE'), 'LANGUAGE names the selected locale');

if (!$hasEnglish) {
    Environment::skip('No en_US or en_GB UTF-8 locale is installed, so the catalog cannot be read.');
}
Assert::match('en_%a%', setlocale(LC_MESSAGES, '0'), 'LC_MESSAGES moves to an English locale');

$dir = sys_get_temp_dir() . '/locale-info-' . getmypid();
$catalogDir = $dir . '/' . $locale . '/LC_MESSAGES';
mkdir($catalogDir, 0777, true);
register_shutdown_function(static function () use ($dir, $catalogDir, $locale): void {
    @unlink($catalogDir . '/messages.mo');
    @rmdir($catalogDir);
    @rmdir($dir . '/' . $locale);
    @rmdir($dir);
});

$entries = ['' => "Content-Type: text/plain; charset=UTF-8\n", 'Hello' => 'Ola'];
ksort($entries);
$count = count($entries);
$ids = implode("\0", array_keys($entries)) . "\0";
$strings = implode("\0", $entries) . "\0";
$idsAt = 28 + 16 * $count;
$stringsAt = $idsAt + strlen($ids);
$idTable = '';
$stringTable = '';
$idPos = 0;
$stringPos = 0;
foreach ($entries as $id => $string) {
    $idTable .= pack('VV', strlen($id), $idsAt + $idPos);
    $stringTable .= pack('VV', strlen($string), $stringsAt + $stringPos);
    $idPos += strlen($id) + 1;
    $stringPos += strlen($string) + 1;
}
file_put_contents(
    $catalogDir . '/messages.mo',
    pack('V7', 0x950412de, 0, $count, 28, 28 + 8 * $count, 0, 0) . $idTable . $stringTable . $ids . $strings
);

bindtextdomain('messages', $dir);
bind_textdomain_codeset('messages', 'UTF-8');
Assert::same('Ola', dgettext('messages', 'Hello'), 'the catalog of the selected locale is read');
