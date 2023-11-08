<?php

namespace ChurchCRM\Authentication\Exceptions;

class PasswordChangeException extends \Exception
{
    public function __construct($OldOrNew, $PasswordChangeProblem)
    {
        parent::__construct($PasswordChangeProblem);
        $this->AffectedPassword = $OldOrNew;
    }
    public $AffectedPassword;
}
