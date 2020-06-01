<?php

namespace ChurchCRM\Exceptions;

use ChurchCRM\Utils\LoggerUtils;

class AppException extends \Exception
{
    public function __construct($message) {
        LoggerUtils::getAppLogger()->error($message);
        parent::__construct($message);
    }

}