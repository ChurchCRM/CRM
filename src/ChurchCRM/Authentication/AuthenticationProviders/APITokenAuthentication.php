<?php

namespace ChurchCRM\Authentication\AuthenticationProviders;

use ChurchCRM\Authentication\AuthenticationResult;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use ChurchCRM\Authentication\Requests\AuthenticationRequest;
use ChurchCRM\Exceptions\NotImplementedException;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\LoggerUtils;

class APITokenAuthentication implements IAuthenticationProvider
{
    private ?User $currentUser = null;

    public function getCurrentUser(): ?User
    {
        return $this->currentUser;
    }

    public function authenticate(AuthenticationRequest $AuthenticationRequest): AuthenticationResult
    {
        if (!$AuthenticationRequest instanceof APITokenAuthenticationRequest) {
            throw new \Exception('Unable to process request as APITokenAuthenticationRequest');
        }
        $authenticationResult = new AuthenticationResult();
        $authenticationResult->isAuthenticated = false;
        $authenticationResult->preventRedirect = true;
        $this->currentUser = UserQuery::create()->findOneByApiKey($AuthenticationRequest->APIToken);

        if (!empty($this->currentUser)) {
            LoggerUtils::getAuthLogger()->debug(gettext('User authenticated via API Key: ') . $this->currentUser->getName());
            $authenticationResult->isAuthenticated = true;
        } else {
            LoggerUtils::getAuthLogger()->warning(gettext('Unsuccessful API Key authentication attempt'));
        }

        return $authenticationResult;
    }

    public function validateUserSessionIsActive(bool $updateLastOperationTimestamp): AuthenticationResult
    {
        // APITokens are session-less, so just always say false.
        $authenticationResult = new AuthenticationResult();
        $authenticationResult->isAuthenticated = false;

        return $authenticationResult;
    }

    public function endSession(): void
    {
        $this->currentUser = null;
    }

    public function getPasswordChangeURL(): string
    {
        throw new NotImplementedException();
    }
}
