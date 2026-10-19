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

    public function __serialize(): array
    {
        return [];
    }

    public function __unserialize(array $data): void
    {
        $this->currentUser = null;
    }

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

        $blockedReason = $this->currentUser?->getSignInBlockedReason();
        if ($blockedReason !== null) {
            LoggerUtils::getAuthLogger()->warning('API Key authentication refused: account cannot sign in', [
                'username' => $this->currentUser->getUserName(),
                'reason' => $blockedReason,
            ]);
            $this->currentUser = null;
        }

        if ($this->currentUser instanceof \ChurchCRM\model\ChurchCRM\User && \ChurchCRM\Shepherd\SecurityPolicy::apiEligible($this->currentUser)) {
            LoggerUtils::getAuthLogger()->debug(sprintf(gettext('User authenticated via API Key: %s'), $this->currentUser->getName()));
            $authenticationResult->isAuthenticated = true;
        } else {
            $this->currentUser = null;
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
