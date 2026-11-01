<?php

declare(strict_types=1);

use ChurchCRM\Utils\GettextLocale;
use Tester\Assert;
use Tester\Environment;

require dirname(__DIR__, 2) . '/bootstrap.php';

$missing = 'xx_XX';

putenv('LANGUAGE');
Assert::null(GettextLocale::enableCatalog($missing, true), 'an installed OS locale needs no fallback');
Assert::same($missing, getenv('LANGUAGE'));

putenv('LANGUAGE');
$fallback = GettextLocale::enableCatalog($missing, false);
Assert::same($missing, getenv('LANGUAGE'));
Assert::true($fallback === false || (is_string($fallback) && str_starts_with($fallback, 'en_')), 'the fallback is an English locale or false');

if ($fallback === false) {
    Environment::skip('No en_US or en_GB UTF-8 locale is installed, so the catalog cannot be read.');
}

$dir = sys_get_temp_dir() . '/gettext-locale-' . getmypid();
$catalogDir = $dir . '/' . $missing . '/LC_MESSAGES';
mkdir($catalogDir, 0777, true);
register_shutdown_function(static function () use ($dir, $catalogDir, $missing): void {
    @unlink($catalogDir . '/messages.mo');
    @rmdir($catalogDir);
    @rmdir($dir . '/' . $missing);
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
