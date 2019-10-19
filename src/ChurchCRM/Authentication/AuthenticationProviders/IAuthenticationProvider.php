<?php

namespace ChurchCRM\Authentication\AuthenticationProviders {

    interface IAuthenticationProvider {
        public function Authenticate(object $AuthenticationRequest);
        public function GetAuthenticationStatus();
        public function GetCurrentUser();
    }
}
