<?php

declare(strict_types=1);

use ChurchCRM\dto\ChurchCRMRelease;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$documentRoot = sys_get_temp_dir() . '/release-cache-' . bin2hex(random_bytes(4));
mkdir($documentRoot . '/Include/cache', 0o777, true);
SystemURLs::init('', [], APP_ROOT);
SystemConfig::init([]);
SystemURLs::init('', [], $documentRoot);
$cacheFile = $documentRoot . '/Include/cache/releases.ser';
$populate = new ReflectionMethod(ChurchCRMReleaseManager::class, 'populateReleases');

try {
    file_put_contents($cacheFile, serialize([new ChurchCRMRelease(['name' => '7.9.0'])]));
    $releases = $populate->invoke(null);
    Assert::count(1, $releases);
    Assert::same('7.9.0', $releases[0]->__toString());
} finally {
    @unlink($cacheFile);
    rmdir($documentRoot . '/Include/cache');
    rmdir($documentRoot . '/Include');
    rmdir($documentRoot);
}
