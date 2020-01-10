<?php

namespace ChurchCRM\Authentication\AuthenticationProviders {

    interface IAuthenticationProvider {
        public function Authenticate(object $AuthenticationRequest);
        public function ValidateUserSessionIsActive(bool $updateLastOperationTimestamp);
        public function GetCurrentUser();
    }
}
