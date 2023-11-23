<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;

class PHPZipArchiveCheckTask implements TaskInterface, PreUpgradeTaskInterface
{
    // todo: make these const variables private after deprecating PHP7.0 #4948
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
        return SystemURLs::getRootPath() . '/v2/admin/debug';
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
