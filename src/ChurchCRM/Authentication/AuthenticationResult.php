<?php

namespace ChurchCRM\Authentication;

class AuthenticationResult
{
    public $isAuthenticated;
    public $nextStepURL;
    public $message;
    public $preventRedirect;
}
