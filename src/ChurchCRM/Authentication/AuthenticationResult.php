<?php

namespace ChurchCRM\Authentication;

class AuthenticationResult
{
    public bool $isAuthenticated = false;
    public ?string $nextStepURL = null;
    public string $message;
    public bool $preventRedirect = false;
}
