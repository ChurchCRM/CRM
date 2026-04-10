<?php

namespace ChurchCRM\Authentication\Requests;

class LocalUsernamePasswordRequest extends AuthenticationRequest
{
    public string $username;
    public string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }
}
