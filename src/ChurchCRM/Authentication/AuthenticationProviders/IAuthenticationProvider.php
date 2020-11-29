<?php

namespace ChurchCRM\Authentication\AuthenticationProviders {
    use ChurchCRM\Authentication\Requests\AuthenticationRequest;
    interface IAuthenticationProvider {
        public function Authenticate(AuthenticationRequest $AuthenticationRequest);
        public function ValidateUserSessionIsActive(bool $updateLastOperationTimestamp);
        public function GetCurrentUser();
        public function EndSession();
        public function GetPasswordChangeURL();
    }
}
