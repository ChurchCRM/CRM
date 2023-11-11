<?php

namespace ChurchCRM\Authentication\Requests;

class LocalUsernamePasswordRequest extends AuthenticationRequest
{
    public $username;
    public $password;

    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }
}
