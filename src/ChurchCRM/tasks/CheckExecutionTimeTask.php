<?php

namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;

class CheckExecutionTimeTask
{
    private int $executionTime;

    public function __construct()
    {
        $this->executionTime = ini_get('max_execution_time');
    }

    public function isActive(): bool
    {
        return $this->executionTime < 120;
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
        return gettext('PHP Max Execution Time is too Short') . ' (' . $this->executionTime . ')';
    }

    public function getDesc(): string
    {
        return gettext('Increase the PHP execution time limit to allow for backup and restore.');
    }
}
