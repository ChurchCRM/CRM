<?php

namespace ChurchCRM\Authentication\Requests;

class LocalUsernamePasswordRequest extends AuthenticationRequest
{
    public string $username;
    public string $password;
    public ?string $redirectPath = null;

    public function __construct(string $username, string $password, ?string $redirectPath = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->redirectPath = $redirectPath;
    }
}
