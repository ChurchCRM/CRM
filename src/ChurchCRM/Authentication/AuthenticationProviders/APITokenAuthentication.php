<?php

namespace ChurchCRM\Authentication\AuthenticationProviders {

    use ChurchCRM\Utils\LoggerUtils;
    use ChurchCRM\UserQuery;
    use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
    use ChurchCRM\Authentication\AuthenticationResult;
    use ChurchCRM\Authentication\Requests\AuthenticationRequest;

class APITokenAuthentication implements IAuthenticationProvider
    {

        /*** 
         * @var ChurchCRM\User
         */
        private $currentUser;

        public function GetCurrentUser()
        {
          return $this->currentUser;
        }

        public function Authenticate(AuthenticationRequest $AuthenticationRequest) {
            if (! $AuthenticationRequest instanceof APITokenAuthenticationRequest ) {
                throw new \Exception ("Unable to process request as APITokenAuthenticationRequest");
            }
            $authenticationResult = new AuthenticationResult();
            $authenticationResult->isAuthenticated = false;
            $authenticationResult->preventRedirect = true;
            $this->currentUser = UserQuery::create()->findOneByApiKey($AuthenticationRequest->APIToken);
            
            if (!empty($this->currentUser)) {
                LoggerUtils::getAuthLogger()->addInfo(gettext("User authenticated via API Key: ") . $this->currentUser->getName());
                $authenticationResult->isAuthenticated = true;                
            } else {
                LoggerUtils::getAuthLogger()->addWarning(gettext("Unsuccessful API Key authentication attempt"));
            }
            return $authenticationResult;
        }

        public function ValidateUserSessionIsActive($updateLastOperationTimestamp) : AuthenticationResult
        { 
            // APITokens are sessionless, so just always say false.
            $authenticationResult = new AuthenticationResult();
            $authenticationResult->isAuthenticated = false;
            return $authenticationResult;
        }

        public function EndSession() {
            $this->currentUser = null;
        }
      
    

    }

}