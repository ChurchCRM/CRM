<?php

namespace ChurchCRM\Authentication\Requests;

class LocalTwoFactorTokenRequest extends AuthenticationRequest
{
    public string $TwoFACode;
    public bool $isRecoveryMode;

    public function __construct(string $TwoFACode, bool $isRecoveryMode = false)
    {
        $this->TwoFACode = $TwoFACode;
        $this->isRecoveryMode = $isRecoveryMode;
    }
}
