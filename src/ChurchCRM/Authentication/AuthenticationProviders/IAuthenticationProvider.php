<?php

namespace ChurchCRM\Authentication\AuthenticationProviders;

use ChurchCRM\Authentication\AuthenticationResult;
use ChurchCRM\Authentication\Requests\AuthenticationRequest;
use ChurchCRM\model\ChurchCRM\User;

interface IAuthenticationProvider
{
    public function authenticate(AuthenticationRequest $AuthenticationRequest): AuthenticationResult;

    public function validateUserSessionIsActive(bool $updateLastOperationTimestamp): AuthenticationResult;

    public function getCurrentUser(): ?User;

    public function endSession(): void;

    public function getPasswordChangeURL(): string;
}
