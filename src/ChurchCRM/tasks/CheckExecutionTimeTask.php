<?php


namespace ChurchCRM\Tasks;


class CheckExecutionTimeTask
{
    private $executionTime;

    public function __construct()
    {
        $this->executionTime = ini_get('max_execution_time');
    }

    public function isActive()
    {
        return $this->executionTime < 120;
    }

    public function isAdmin()
    {
        return true;
    }

    public function getLink()
    {
        return 'https://github.com/ChurchCRM/CRM/wiki/PHP-Max-Execution-Time';
    }

    public function getTitle()
    {
        return gettext('PHP Max Execution Time is too Short') . " (" . $this->executionTime . ")";
    }

    public function getDesc()
    {
        return gettext("Increase the PHP execution time limit to allow for backup and restore.");
    }

}
