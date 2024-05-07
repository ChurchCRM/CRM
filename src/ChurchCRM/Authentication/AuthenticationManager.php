<?php

namespace ChurchCRM\Authentication;

use ChurchCRM\Authentication\AuthenticationProviders\APITokenAuthentication;
use ChurchCRM\Authentication\AuthenticationProviders\IAuthenticationProvider;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use ChurchCRM\Authentication\Requests\AuthenticationRequest;
use ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest;
use ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

class AuthenticationManager
{
    // This class exists to abstract the implementations of various authentication providers
    // Currently, only local auth is implemented; hence the zero-indexed array elements.

    public static function getAuthenticationProvider(): IAuthenticationProvider
    {
        if (
            isset($_SESSION) &&
            array_key_exists('AuthenticationProvider', $_SESSION) &&
            $_SESSION['AuthenticationProvider'] instanceof IAuthenticationProvider
        ) {
            return $_SESSION['AuthenticationProvider'];
        } else {
            throw new \Exception('No active authentication provider');
        }
    }

    private static function setAuthenticationProvider(IAuthenticationProvider $AuthenticationProvider): void
    {
        $_SESSION['AuthenticationProvider'] = $AuthenticationProvider;
    }

    public static function getCurrentUser(): User
    {
        try {
            $currentUser = self::getAuthenticationProvider()->getCurrentUser();
            if (!$currentUser instanceof User) {
                throw new \Exception('No current user provided by current authentication provider: ' . get_class(self::getAuthenticationProvider()));
            }

            return $currentUser;
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->debug('Failed to get current user', ['exception' => $e]);

            throw $e;
        }
    }

    public static function endSession(bool $preventRedirect = false): void
    {
        $logger = LoggerUtils::getAuthLogger();
        $currentSessionUserName = 'Unknown';

        try {
            if (self::getCurrentUser() instanceof User) {
                $currentSessionUserName = self::getCurrentUser()->getName();
            }
        } catch (\Exception $e) {
            //unable to get name of user logging out. Don't really care.
        }
        $logCtx = ['username' => $currentSessionUserName];

        try {
            self::getAuthenticationProvider()->endSession();

            $_COOKIE = [];
            $_SESSION = [];
            session_destroy();
            Bootstrapper::initSession();
            $logger->info(
                'Ended Local session for user',
                $logCtx
            );
        } catch (\Exception $e) {
            $logger->warning(
                'Error destroying session',
                array_merge($logCtx, ['exception' => $e])
            );
        } finally {
            if (!$preventRedirect) {
                RedirectUtils::redirect(self::getSessionBeginURL());
            }
        }
    }

    public static function authenticate(AuthenticationRequest $AuthenticationRequest): AuthenticationResult
    {
        $logger = LoggerUtils::getAppLogger();
        switch (get_class($AuthenticationRequest)) {
            case APITokenAuthenticationRequest::class:
                $AuthenticationProvider = new APITokenAuthentication();
                self::setAuthenticationProvider($AuthenticationProvider);
                break;
            case LocalUsernamePasswordRequest::class:
                $AuthenticationProvider = new LocalAuthentication();
                self::setAuthenticationProvider($AuthenticationProvider);
                break;
            case LocalTwoFactorTokenRequest::class:
                try {
                    self::getAuthenticationProvider();
                } catch (\Exception $e) {
                    $logger->warning(
                        "Tried to supply two factor authentication code, but didn't have an existing session.  This shouldn't ever happen",
                        ['exception' => $e]
                    );
                }
                break;
            default:
                $logger->critical('Unknown AuthenticationRequest type supplied', ['providedAuthenticationRequestClass' => get_class($AuthenticationRequest)]);
                break;
        }

        $result = self::getAuthenticationProvider()->authenticate($AuthenticationRequest);

        if (null !== $result->nextStepURL) {
            $logger->debug('Authentication requires additional step: ' . $result->nextStepURL);
            RedirectUtils::redirect($result->nextStepURL);
        }

        if ($result->isAuthenticated && !$result->preventRedirect) {
            $redirectLocation = null;
            if ($AuthenticationRequest instanceof LocalUsernamePasswordRequest) {
                $redirectLocation = $AuthenticationRequest->redirectPath;
            }
            $redirectLocation ??= $_SESSION['location'] ?? 'v2/dashboard';
            NotificationService::updateNotifications();
            $logger->debug(
                'Authentication Successful; redirecting to: ' . $redirectLocation
            );
            RedirectUtils::redirect($redirectLocation);
        }

        return $result;
    }

    public static function validateUserSessionIsActive(bool $updateLastOperationTimestamp = true): bool
    {
        try {
            $result = self::getAuthenticationProvider()
                ->validateUserSessionIsActive($updateLastOperationTimestamp);

            return $result->isAuthenticated;
        } catch (\Exception $error) {
            LoggerUtils::getAuthLogger()->debug(
                'Error determining session authentication status.',
                ['exception' => $error]
            );

            return false;
        }
    }

    public static function ensureAuthentication(): void
    {
        // This function differs from the semantic `ValidateUserSessionIsActive` in that it will
        // take corrective action to redirect the user to an appropriate login location
        // if the current session is not actually authenticated

        try {
            $result = self::getAuthenticationProvider()->validateUserSessionIsActive(true);
            // Auth providers will always include a `nextStepURL` if authentication fails.
            // Sometimes other actions may require a `nextStepURL` that should be enforced with
            // an authentication request (2FA, Expired Password, etc).
            if (!$result->isAuthenticated) {
                LoggerUtils::getAuthLogger()->debug(
                    'Session not authenticated.  Redirecting to login page'
                );

                $redirectPath = $_GET['location'] ?? $_SESSION['location'] ?? null;
                $loginUrl = self::getSessionBeginURL();
                if (!empty($redirectPath)) {
                    $queryParams = http_build_query([
                        'location' => $redirectPath,
                    ]);
                    $loginUrl .= '?' . $queryParams;
                }
                RedirectUtils::redirect($loginUrl);
            } elseif (null !== $result->nextStepURL) {
                LoggerUtils::getAuthLogger()->debug(
                    'Session authenticated, but redirect requested by authentication provider.'
                );
                $redirectPath = $_GET['location'] ?? $_SESSION['location'] ?? null;
                if (!empty($redirectPath)) {
                    $queryParams = http_build_query([
                        'location' => $redirectPath,
                    ]);
                    $result->nextStepURL .= '?' . $queryParams;
                }
                RedirectUtils::redirect($result->nextStepURL);
            }
            LoggerUtils::getAuthLogger()->debug('Session valid');
        } catch (\Throwable $error) {
            LoggerUtils::getAuthLogger()->debug(
                'Error determining session authentication status.  Redirecting to login page.',
                ['exception' => $error]
            );
            RedirectUtils::redirect(self::getSessionBeginURL());
        }
    }

    public static function getSessionBeginURL(?string $redirectPath = null): string
    {
        $url = SystemURLs::getRootPath() . '/session/begin';
        if (!empty($redirectPath)) {
            $url .= '?location=' . urlencode($redirectPath);
        }

        return $url;
    }

    public static function getForgotPasswordURL(): string
    {
        // this assumes we're using local authentication
        // TODO: when we implement other authentication providers (SAML/etc)
        // this URL will need to be configurable by the system administrator
        // since they likely will not want users attempting to reset ChurchCRM passwords
        // but rather redirect users to some other password reset mechanism.
        return SystemURLs::getRootPath() . '/session/forgot-password/reset-request';
    }
    public static function redirectHomeIfFalse(bool $hasAccess): void
    {
        if (!$hasAccess) {
            RedirectUtils::redirect('v2/dashboard');
        }
    }

    public static function redirectHomeIfNotAdmin(): void
    {
        if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
            RedirectUtils::securityRedirect('Admin');
        }
    }
}
