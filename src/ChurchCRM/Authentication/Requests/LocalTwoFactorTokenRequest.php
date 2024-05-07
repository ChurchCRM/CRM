<?php

namespace ChurchCRM\Authentication\Requests;

class LocalTwoFactorTokenRequest extends AuthenticationRequest
{
    public string $TwoFACode;

    public function __construct(string $TwoFACode)
    {
        $this->TwoFACode = $TwoFACode;
    }
}
