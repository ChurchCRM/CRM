<?php

namespace ChurchCRM\Authentication\Requests;

class APITokenAuthenticationRequest extends AuthenticationRequest
{
    public string $APIToken;

    public function __construct(string $APIToken)
    {
        $this->APIToken = $APIToken;
    }
}
