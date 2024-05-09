<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;

class PHPPendingDeprecationVersionCheckTask implements TaskInterface, PreUpgradeTaskInterface
{
    public function isActive(): bool
    {
        return PHP_VERSION_ID < 80100;
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
        return gettext('Unsupported PHP Version');
    }

    public function getDesc(): string
    {
        return gettext('Support for this PHP version will soon be removed.  Current PHP Version: ' . PHP_VERSION . '. Minimum Required PHP Version: 8.1.0');
    }
}
