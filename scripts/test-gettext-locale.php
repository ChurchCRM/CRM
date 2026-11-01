<?php

/**
 * scripts/test-gettext-locale.php — translations still load when the OS locale is not installed.
 *
 * Usage:
 *   php scripts/test-gettext-locale.php
 *   npm run test:php
 *
 * GNU gettext ignores LANGUAGE while LC_MESSAGES is C, so a host without the church's OS locale
 * showed every gettext() string in English. GettextLocale::enableCatalog() must set LANGUAGE and,
 * when the OS locale could not be set, move LC_MESSAGES to an installed English UTF-8 locale.
 * The end-to-end case runs only where such a locale is installed. No database or vendor needed.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 */

declare(strict_types=1);

require __DIR__ . '/../src/ChurchCRM/Utils/GettextLocale.php';

use ChurchCRM\Utils\GettextLocale;

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

$missing = 'xx_XX';

putenv('LANGUAGE');
$check(GettextLocale::enableCatalog($missing, true) === null, 'an installed OS locale needs no fallback');
$check(getenv('LANGUAGE') === $missing, 'LANGUAGE names the selected locale when the OS locale is set');

putenv('LANGUAGE');
$fallback = GettextLocale::enableCatalog($missing, false);
$check(getenv('LANGUAGE') === $missing, 'LANGUAGE names the selected locale when the OS locale is missing');
$check($fallback === false || str_starts_with((string) $fallback, 'en_'), 'the fallback is an English locale or false', var_export($fallback, true));

if ($fallback === false) {
    echo "ok " . ($passed + $failed + 1) . " - skipped the catalog case: no en_US or en_GB UTF-8 locale installed # SKIP\n";
    $passed++;
} else {
    $dir = sys_get_temp_dir() . '/gettext-locale-' . getmypid();
    mkdir($dir . '/' . $missing . '/LC_MESSAGES', 0777, true);
    $entries = ['' => "Content-Type: text/plain; charset=UTF-8\n", 'Hello' => 'Ola'];
    ksort($entries);
    $count = count($entries);
    $ids = implode("\0", array_keys($entries)) . "\0";
    $strings = implode("\0", array_values($entries)) . "\0";
    $keyOffset = 28 + 16 * $count;
    $valueOffset = $keyOffset + strlen($ids);
    $keyTable = '';
    $valueTable = '';
    $kPos = 0;
    $vPos = 0;
    foreach ($entries as $id => $string) {
        $keyTable .= pack('VV', strlen($id), $keyOffset + $kPos);
        $valueTable .= pack('VV', strlen($string), $valueOffset + $vPos);
        $kPos += strlen($id) + 1;
        $vPos += strlen($string) + 1;
    }
    $mo = pack('V7', 0x950412de, 0, $count, 28, 28 + 8 * $count, 0, 0) . $keyTable . $valueTable . $ids . $strings;
    file_put_contents($dir . '/' . $missing . '/LC_MESSAGES/messages.mo', $mo);

    bindtextdomain('messages', $dir);
    bind_textdomain_codeset('messages', 'UTF-8');
    textdomain('messages');
    $check(dgettext('messages', 'Hello') === 'Ola', 'the catalog of the selected locale is read', dgettext('messages', 'Hello'));

    unlink($dir . '/' . $missing . '/LC_MESSAGES/messages.mo');
    rmdir($dir . '/' . $missing . '/LC_MESSAGES');
    rmdir($dir . '/' . $missing);
    rmdir($dir);
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
