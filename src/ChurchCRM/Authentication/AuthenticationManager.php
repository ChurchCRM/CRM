<?php

namespace ChurchCRM\Authentication;

use ChurchCRM\Authentication\AuthenticationProviders\APITokenAuthentication;
use ChurchCRM\Authentication\AuthenticationProviders\IAuthenticationProvider;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use ChurchCRM\Authentication\Requests\AuthenticationRequest;
use ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest;
use ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

class AuthenticationManager
{
    // This class exists to abstract the implementations of various authentication providers
    // Currently, only local auth is implemented; hence the zero-indexed array elements.

    /**
     * Holds the authenticated user for the current request when API token auth is used.
     * This allows getCurrentUser() to return the correct API user even after the session
     * provider has been restored to LocalAuthentication (to avoid session corruption).
     */
    private static ?User $currentRequestUser = null;

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
        // For API token requests, the authenticated user is cached here to avoid
        // reading from the session (which has been restored to the previous provider).
        if (self::$currentRequestUser instanceof User) {
            return self::$currentRequestUser;
        }

        try {
            $currentUser = self::getAuthenticationProvider()->getCurrentUser();
            if (!$currentUser instanceof User) {
                $provider = self::getAuthenticationProvider();
                throw new \Exception('No current user provided by current authentication provider: ' . $provider::class);
            }

            return $currentUser;
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->debug('Failed to get current user', ['exception' => $e]);

            throw $e;
        }
    }

    public static function isUserAuthenticated(): bool
    {
        try {
            $user = self::getCurrentUser();
            return $user !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function endSession(bool $preventRedirect = false): void
    {
        $logger = LoggerUtils::getAuthLogger();
        $currentSessionUserName = 'Unknown';

        try {
            $currentSessionUserName = self::getCurrentUser()->getName();
        } catch (\Exception $e) {
            //unable to get name of user logging out. Don't really care.
        }
        $logCtx = ['username' => $currentSessionUserName];

        try {
            self::getAuthenticationProvider()->endSession();

            session_unset();
            session_destroy();
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
        $previousProvider = null; // used by APITokenAuthenticationRequest to restore session provider
        switch ($AuthenticationRequest::class) {
            case APITokenAuthenticationRequest::class:
                // Use a temporary provider for API key auth that does NOT permanently
                // overwrite the session. Save the existing session provider (if any)
                // and restore it after authenticate() so that a concurrent browser
                // session using the same PHP session ID is not corrupted.
                // (cy.request() in Cypress shares the browser cookie jar, so the
                // session cookie is sent on API key requests too.)
                $previousProvider = null;
                try {
                    $previousProvider = self::getAuthenticationProvider();
                } catch (\Exception $e) {
                    // No existing session provider — pure API-only request, no restoration needed.
                }
                $AuthenticationProvider = new APITokenAuthentication();
                self::setAuthenticationProvider($AuthenticationProvider);
                break;
            case LocalUsernamePasswordRequest::class:
                $AuthenticationProvider = new LocalAuthentication();
                self::setAuthenticationProvider($AuthenticationProvider);
                break;
            case LocalTwoFactorTokenRequest::class:
                try {
                    $AuthenticationProvider = self::getAuthenticationProvider();
                } catch (\Exception $e) {
                    $logger->warning(
                        "Tried to supply two factor authentication code, but didn't have an existing session.  This shouldn't ever happen",
                        ['exception' => $e]
                    );
                    $AuthenticationProvider = new LocalAuthentication();
                    self::setAuthenticationProvider($AuthenticationProvider);
                }
                break;
            default:
                $logger->error('Unknown AuthenticationRequest type supplied', ['providedAuthenticationRequestClass' => $AuthenticationRequest::class]);
                // Fall back to session provider or create a new one to avoid undefined variable.
                try {
                    $AuthenticationProvider = self::getAuthenticationProvider();
                } catch (\Exception $e) {
                    $AuthenticationProvider = new LocalAuthentication();
                }
                break;
        }

        $result = $AuthenticationProvider->authenticate($AuthenticationRequest);

        // For API token requests: restore the previous session provider immediately so
        // the session is NOT left with APITokenAuthentication at request end.
        // APITokenAuthentication::validateUserSessionIsActive() always returns false,
        // which would break subsequent browser page loads on the same PHP session.
        // We cache the authenticated API user in $currentRequestUser so getCurrentUser()
        // can still return the correct user for the remainder of this request.
        if ($AuthenticationRequest instanceof APITokenAuthenticationRequest) {
            if ($result->isAuthenticated) {
                self::$currentRequestUser = $AuthenticationProvider->getCurrentUser();
            }
            if ($previousProvider !== null) {
                self::setAuthenticationProvider($previousProvider);
            }
        }

        if (null !== $result->nextStepURL) {
            $logger->debug('Authentication requires additional step: ' . $result->nextStepURL);
            RedirectUtils::redirect($result->nextStepURL);
        }

        if ($result->isAuthenticated && !$result->preventRedirect) {
            $redirectLocation = self::validateRedirectPath($_SESSION['location'] ?? null);
            unset($_SESSION['location']); // clear post-login redirect (one-time use)
            $redirectLocation ??= 'v2/dashboard';
            
            // One-time login tasks: check for system updates and fetch remote notifications
            self::checkSystemUpdates();
            NotificationService::fetchRemoteNotifications();

            $logger->debug(
                'Authentication Successful; redirecting to: ' . $redirectLocation
            );
            RedirectUtils::redirect($redirectLocation);
        }

        return $result;
    }

    public static function validateUserSessionIsActive(bool $updateLastOperationTimestamp = true): bool
    {
        // Check if an authentication provider is set before attempting validation
        // This prevents unnecessary logging for public API calls that don't require authentication
        if (
            !isset($_SESSION) ||
            !array_key_exists('AuthenticationProvider', $_SESSION) ||
            !$_SESSION['AuthenticationProvider'] instanceof IAuthenticationProvider
        ) {
            return false;
        }

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

                // Store the originally requested URL in the session for post-login redirect.
                // Using the session (server-side) prevents open-redirect attacks via a crafted query parameter.
                $safeUri = RedirectUtils::stripAndValidatePath($_SERVER['REQUEST_URI'] ?? '');
                if ($safeUri !== '') {
                    $_SESSION['location'] = $safeUri;
                }

                RedirectUtils::redirect(self::getSessionBeginURL());
            } elseif (null !== $result->nextStepURL) {
                LoggerUtils::getAuthLogger()->debug(
                    'Session authenticated, but redirect requested by authentication provider.'
                );
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

    public static function getSessionBeginURL(): string
    {
        return SystemURLs::getRootPath() . '/session/begin';
    }

    public static function getForgotPasswordURL(): string
    {
        return SystemURLs::getRootPath() . '/session/forgot-password/reset-request';
    }
    public static function redirectHomeIfFalse(bool $hasAccess, string $missingRole = ''): void
    {
        if (!$hasAccess) {
            if ($missingRole !== '') {
                RedirectUtils::securityRedirect($missingRole);
            } else {
                RedirectUtils::redirect('v2/dashboard');
            }
        }
    }

    public static function redirectHomeIfNotAdmin(): void
    {
        if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
            RedirectUtils::securityRedirect('Admin');
        }
    }

    /**
     * Validates a redirect URL and returns it, or null if it is invalid/empty.
     * Used to consolidate the validate-then-nullify pattern for post-login redirects.
     */
    private static function validateRedirectPath(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        $validated = RedirectUtils::validateRedirectUrl($url, '');

        return $validated !== '' ? $validated : null;
    }

    /**
     * Check for system updates and store result in session.
     * Only runs for admin users on login. The upgrade notification is
     * rendered on page load by NotificationService::loadSessionNotifications().
     */
    private static function checkSystemUpdates(): void
    {
        $currentUser = self::getCurrentUser();
        if (!$currentUser->isAdmin()) {
            $_SESSION['systemUpdateAvailable'] = false;
            $_SESSION['systemUpdateVersion'] = null;
            $_SESSION['systemLatestVersion'] = null;
            return;
        }

        $updateInfo = ChurchCRMReleaseManager::checkSystemUpdateAvailable();
        $_SESSION['systemUpdateAvailable'] = $updateInfo['available'];
        $_SESSION['systemUpdateVersion'] = $updateInfo['version'];
        $_SESSION['systemLatestVersion'] = $updateInfo['latestVersion'];
    }
}
