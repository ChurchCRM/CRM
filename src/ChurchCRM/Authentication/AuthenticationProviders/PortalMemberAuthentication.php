<?php

namespace ChurchCRM\Authentication\AuthenticationProviders;

use ChurchCRM\Authentication\AuthenticationResult;
use ChurchCRM\Authentication\Requests\AuthenticationRequest;
use ChurchCRM\Exceptions\NotImplementedException;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\PortalMemberUser;

/**
 * The acting person of a portal session, for one volunteer API request
 * (`VolunteerPortalMemberMiddleware`). Like an API key it lives for the request
 * only and is never written to the session, and it is never a staff session:
 * `validateUserSessionIsActive()` answers no.
 */
final class PortalMemberAuthentication implements IAuthenticationProvider
{
    public function __construct(private readonly PortalMemberUser $member)
    {
    }

    public function authenticate(AuthenticationRequest $AuthenticationRequest): AuthenticationResult
    {
        throw new NotImplementedException();
    }

    public function validateUserSessionIsActive(bool $updateLastOperationTimestamp): AuthenticationResult
    {
        $result = new AuthenticationResult();
        $result->isAuthenticated = false;

        return $result;
    }

    public function getCurrentUser(): ?User
    {
        return $this->member;
    }

    public function endSession(): void
    {
    }

    public function getPasswordChangeURL(): string
    {
        throw new NotImplementedException();
    }
}
