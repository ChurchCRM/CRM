<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemURLs;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

$documentRoot = sys_get_temp_dir() . '/releases-page-' . bin2hex(random_bytes(4));
mkdir($documentRoot . '/Include', 0o777, true);
touch($documentRoot . '/Include/Header.php');
touch($documentRoot . '/Include/Footer.php');
SystemURLs::init('', [], $documentRoot);

$render = function (array $preview): string {
    ob_start();
    require APP_ROOT . '/v2/templates/root/releases.php';

    return (string) ob_get_clean();
};

try {
    $html = $render([
        'installedVersion' => '7.8.1',
        'upgradePath' => [
            ['version' => '7.8.2', 'notes' => "## Fixes\n<script>x</script>", 'changelogUrl' => 'https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md#782'],
            ['version' => '7.8.3', 'notes' => 'Security fix', 'changelogUrl' => 'javascript:alert(1)'],
        ],
    ]);

    Assert::contains('Installed version: 7.8.1', $html);
    preg_match_all('/data-release-version="([^"]+)"/', $html, $versions);
    Assert::same(['7.8.3', '7.8.2'], $versions[1]);
    Assert::contains('&lt;script&gt;x&lt;/script&gt;', $html);
    Assert::contains('href="https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md#782"', $html);
    Assert::notContains('javascript:', $html);
    Assert::notContains('/admin/system/upgrade', $html);

    Assert::contains('id="releases-up-to-date"', $render(['installedVersion' => '7.8.4', 'upgradePath' => []]));
    Assert::contains('id="releases-unavailable"', $render(['installedVersion' => '', 'upgradePath' => [], 'unavailable' => true]));
} finally {
    array_map('unlink', glob($documentRoot . '/Include/*.php'));
    rmdir($documentRoot . '/Include');
    rmdir($documentRoot);
}
