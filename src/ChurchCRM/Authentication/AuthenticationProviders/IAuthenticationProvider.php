<?php

namespace ChurchCRM\Authentication\AuthenticationProviders;

use ChurchCRM\Authentication\Requests\AuthenticationRequest;

interface IAuthenticationProvider
{
    public function authenticate(AuthenticationRequest $AuthenticationRequest);

    public function validateUserSessionIsActive(bool $updateLastOperationTimestamp);

    public function getCurrentUser();

    public function endSession();

    public function getPasswordChangeURL();
}
