<?php

namespace ChurchCRM\Authentication\AuthenticationProviders {

    interface IAuthenticationProvider {
        public function Authenticate(object $AuthenticationRequest);
    }
}
