<?php

declare(strict_types=1);

use ChurchCRM\Exceptions\ImageStorageException;
use ChurchCRM\Utils\ImageSupportUtils;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$image = imagecreatetruecolor(2, 2);
$missingDirectory = sys_get_temp_dir() . '/churchcrm-missing-' . bin2hex(random_bytes(4));

Assert::exception(
    static fn () => ImageSupportUtils::savePngAtomically($image, $missingDirectory . '/letterhead.png'),
    ImageStorageException::class,
);

$target = sys_get_temp_dir() . '/churchcrm-letterhead-' . bin2hex(random_bytes(4)) . '.png';
ImageSupportUtils::savePngAtomically($image, $target);
Assert::true(is_file($target) && filesize($target) > 0);
unlink($target);
