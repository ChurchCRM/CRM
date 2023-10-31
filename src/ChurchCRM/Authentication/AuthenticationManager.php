<?php

namespace ChurchCRM\Authentication {

    use ChurchCRM\Utils\RedirectUtils;
    use ChurchCRM\Service\NotificationService;
    use ChurchCRM\Utils\LoggerUtils;
    use ChurchCRM\dto\SystemURLs;
    use ChurchCRM\Authentication\AuthenticationProviders\IAuthenticationProvider;
    use ChurchCRM\Bootstrapper;
    use ChurchCRM\Authentication\Requests\AuthenticationRequest;
    use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
    use ChurchCRM\Authentication\AuthenticationProviders\APITokenAuthentication;
    use ChurchCRM\User;

    class AuthenticationManager
    {

        // This class exists to abstract the implementations of various authentication providers
        // Currently, only local auth is implemented; hence the zero-indexed array elements.

        public static function getAuthenticationProvider()
        {
            if (isset($_SESSION) &&
            array_key_exists('AuthenticationProvider', $_SESSION) &&
            $_SESSION['AuthenticationProvider'] instanceof IAuthenticationProvider
            ) {
                return $_SESSION['AuthenticationProvider'];
            } else {
                throw new \Exception("No active authentication provider");
            }
        }

        private static function setAuthenticationProvider(IAuthenticationProvider $AuthenticationProvider)
        {
            $_SESSION['AuthenticationProvider'] = $AuthenticationProvider;
        }

        public static function getCurrentUser() : User
        {
            try {
                $currentUser = self::getAuthenticationProvider()->getCurrentUser();
                if (empty($currentUser)) {
                    throw new \Exception("No current user provided by current authentication provider: " . get_class(self::getAuthenticationProvider()));
                }
                return $currentUser;
            } catch (\Exception $e) {
                LoggerUtils::getAppLogger()->debug('Failed to get current user', ['exception' => $e]);
                throw $e;
            }
        }

        public static function endSession($preventRedirect = false)
        {
            $logger = LoggerUtils::getAuthLogger();
            $currentSessionUserName = "Unknown";
            try {
                if (self::getCurrentUser() != null) {
                    $currentSessionUserName = self::getCurrentUser()->getName();
                }
            } catch (\Exception $e) {
          //unable to get name of user logging out. Don't really care.
            }

            try {
                $result = self::getAuthenticationProvider()->endSession();
                $_COOKIE = [];
                $_SESSION = [];
                session_destroy();
                Bootstrapper::initSession();
                $logger->info("Ended Local session for user " . $currentSessionUserName);
            } catch (\Exception $e) {
                $logger->warning('Error destroying session', ['exception' => $e]);
            } finally {
                if (!$preventRedirect) {
                        RedirectUtils::redirect(self::getSessionBeginURL());
                }
            }
        }

        public static function authenticate(AuthenticationRequest $AuthenticationRequest)
        {
            $logger = LoggerUtils::getAppLogger();
            switch (get_class($AuthenticationRequest)) {
                case \ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest::class:
                    $AuthenticationProvider = new APITokenAuthentication();
                    self::setAuthenticationProvider($AuthenticationProvider);
                    break;
                case \ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest::class:
                    $AuthenticationProvider = new LocalAuthentication();
                    self::setAuthenticationProvider($AuthenticationProvider);
                    break;
                case \ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest::class:
                    try {
                        self::getAuthenticationProvider();
                    } catch (\Exception $e) {
                        $logger->warning("Tried to supply two factor authentication code, but didn't have an existing session.  This shouldn't ever happen", ['exception' => $e]);
                    }
                    break;
                default:
                    $logger->critical("Unknown AuthenticationRequest type supplied");
                    break;
            }

            $result = self::getAuthenticationProvider()->authenticate($AuthenticationRequest);

            if (null !== $result->nextStepURL) {
                $logger->debug("Authentication requires additional step: " . $result->nextStepURL);
                RedirectUtils::redirect($result->nextStepURL);
            }

            if ($result->isAuthenticated && ! $result->preventRedirect) {
                $redirectLocation = array_key_exists("location", $_SESSION) ? $_SESSION['location'] : 'Menu.php';
                NotificationService::updateNotifications();
                $logger->debug("Authentication Successful; redirecting to: " . $redirectLocation);
                RedirectUtils::redirect($redirectLocation);
            }
            return $result;
        }

        public static function validateUserSessionIsActive($updateLastOperationTimestamp = true)
        {
            try {
                $result = self::getAuthenticationProvider()->validateUserSessionIsActive($updateLastOperationTimestamp);
                return $result->isAuthenticated;
            } catch (\Exception $error) {
                LoggerUtils::getAuthLogger()->debug("Error determining session authentication status.", ['exception' => $error]);
                return false;
            }
        }

        public static function ensureAuthentication()
        {
          // This function differs from the sematinc `ValidateUserSessionIsActive` in that it will
          // take corrective action to redirect the user to an appropriate login location
          // if the current session is not actually authenticated

            try {
                $result = self::getAuthenticationProvider()->validateUserSessionIsActive(true);
            // Auth providers will always include a `nextStepURL` if authentication fails.
            // Sometimes other actions may require a `nextStepURL` that should be enforced with
            // an authentication request (2FA, Expired Password, etc).
                if (!$result->isAuthenticated) {
                    LoggerUtils::getAuthLogger()->debug("Session not authenticated.  Redirecting to login page");
                    RedirectUtils::redirect(self::getSessionBeginURL());
                } elseif (null !== $result->nextStepURL) {
                    LoggerUtils::getAuthLogger()->debug("Session authenticated, but redirect requested by authentication provider.");
                    RedirectUtils::redirect($result->nextStepURL);
                }
                LoggerUtils::getAuthLogger()->debug("Session valid");
            } catch (\Throwable $error) {
                LoggerUtils::getAuthLogger()->debug("Error determining session authentication status.  Redirecting to login page.", ['exception' => $error]);
                RedirectUtils::redirect(self::getSessionBeginURL());
            }
        }

        public static function getSessionBeginURL()
        {
            return SystemURLs::getRootPath() . "/session/begin";
        }

        public static function getForgotPasswordURL()
        {
          // this assumes we're using local authentication
          // TODO: when we implement other authentication providers (SAML/etc)
          // this URL will need to be configuable by the system administrator
          // since they likely will not want users attempting to reset ChurchCRM passwords
          // but rather redirect users to some other password reset mechanism.
            return SystemURLs::getRootPath() . "/session/forgot-password/reset-request";
        }
    }
}
