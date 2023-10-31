<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\KeyManager;

class SecretsConfigurationCheckTask implements iTask
{
    public function isActive(): bool
    {
        return ! KeyManager::getAreAllSecretsDefined();
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function getLink(): string
    {
        return SystemURLs::getSupportURL(array_pop(explode('\\', self::class)));
    }

    public function getTitle(): string
    {
        return gettext('Secret Keys missing from Config.php');
    }

    public function getDesc(): string
    {
        return gettext('Secret Keys missing from Config.php');
    }
}
