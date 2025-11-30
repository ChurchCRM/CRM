<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;

class PHPZipArchiveCheckTask implements TaskInterface, PreUpgradeTaskInterface
{
    public function isActive(): bool
    {
        return !class_exists('ZipArchive');
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function getLink(): string
    {
        return SystemURLs::getRootPath() . '/admin/system/debug';
    }

    public function getTitle(): string
    {
        return gettext('Missing PHP ZipArchive');
    }

    public function getDesc(): string
    {
        return gettext('PHP ZipArchive required to support upgrade');
    }
}
