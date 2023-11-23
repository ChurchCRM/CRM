<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;

class PHPPendingDeprecationVersionCheckTask implements TaskInterface, PreUpgradeTaskInterface
{
    private const REQUIRED_PHP_VERSION = '8.1.0';

    public function isActive(): bool
    {
        return version_compare(PHP_VERSION, $this::REQUIRED_PHP_VERSION, '<');
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
        return gettext('Support for this PHP version will soon be removed.  Current PHP Version: ' . PHP_VERSION . '. Minimum Required PHP Version: ' . $this::REQUIRED_PHP_VERSION);
    }
}
