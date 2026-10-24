<?php

namespace ChurchCRM\Authentication;

use ChurchCRM\Authentication\AuthenticationProviders\APITokenAuthentication;
use ChurchCRM\Authentication\AuthenticationProviders\IAuthenticationProvider;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Authentication\AuthenticationProviders\PortalMemberAuthentication;
use ChurchCRM\Authentication\Requests\APITokenAuthenticationRequest;
use ChurchCRM\Authentication\Requests\AuthenticationRequest;
use ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest;
use ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\LoginAsUser;
use ChurchCRM\Portal\Auth\PortalMemberUser;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use ChurchCRM\Portal\Auth\PortalSessionStore;
use ChurchCRM\Portal\Auth\SignInAudit;
use ChurchCRM\Portal\Auth\SignInAuditEvent;
use ChurchCRM\Portal\Auth\SignInReason;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\Service\RememberedDeviceService;
use ChurchCRM\Utils\ChurchCRMReleaseManager;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

class AuthenticationManager
{
    // This class exists to abstract the implementations of various authentication providers
    // Currently, only local auth is implemented; hence the zero-indexed array elements.

    // API-key authentication is stateless: its provider lives for one request and is
    // never written to the session, so an API call can neither create a login session
    // nor replace the browser session that may arrive with it. A portal member acting
    // on the volunteer API is held the same way.
    private static APITokenAuthentication|PortalMemberAuthentication|null $requestProvider = null;

    public static function clearRequestProvider(): void
    {
        self::$requestProvider = null;
    }

    /**
     * For this request only, the portal session's person is the current user of
     * the volunteer API (`VolunteerPortalMemberMiddleware`). No staff route sees it:
     * AuthMiddleware clears it at the start of every request.
     */
    public static function actAsPortalMember(PortalMemberUser $member): void
    {
        self::$requestProvider = new PortalMemberAuthentication($member);
    }

    public static function getAuthenticationProvider(): IAuthenticationProvider
    {
        if (self::$requestProvider !== null) {
            return self::$requestProvider;
        }

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

    /**
     * Login as User (member sign-in design §6.5): takes the browser's staff session
     * out of the PHP session while the administrator is in the Member Portal, so no
     * staff page or API sees it, and hands it to the caller to keep.
     */
    public static function setAsideSessionProvider(): ?LocalAuthentication
    {
        $provider = $_SESSION['AuthenticationProvider'] ?? null;
        if (!$provider instanceof LocalAuthentication || self::$requestProvider !== null) {
            return null;
        }
        unset($_SESSION['AuthenticationProvider']);

        return $provider;
    }

    public static function restoreSessionProvider(LocalAuthentication $provider): void
    {
        self::setAuthenticationProvider($provider);
    }

    private static function setAuthenticationProvider(IAuthenticationProvider $AuthenticationProvider): void
    {
        if ($AuthenticationProvider instanceof APITokenAuthentication) {
            self::$requestProvider = $AuthenticationProvider;

            return;
        }

        self::$requestProvider = null;
        $_SESSION['AuthenticationProvider'] = $AuthenticationProvider;
    }

    public static function getCurrentUser(): User
    {
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

        LoginAsUser::endWithSession();

        try {
            if (self::hasSessionProvider()) {
                self::getAuthenticationProvider()->endSession();
            }
        } catch (\Exception $e) {
            $logger->warning(
                'Error ending the authentication provider session',
                array_merge($logCtx, ['exception' => $e])
            );
        }

        // A portal-only session has no staff provider, and must still be
        // destroyed whole: the principal lives in the same PHP session.
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_unset();
                session_destroy();
            }
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
                RedirectUtils::redirect(self::getStaffSignInURL());
            }
        }
    }

    /**
     * Ends the staff sign-in in this browser and keeps the rest of the PHP
     * session: a member sign-in replaces it (one principal per session, §6.4).
     */
    public static function endStaffSession(): void
    {
        if (!isset($_SESSION['AuthenticationProvider'])) {
            return;
        }

        try {
            if ($_SESSION['AuthenticationProvider'] instanceof IAuthenticationProvider) {
                $_SESSION['AuthenticationProvider']->endSession();
            }
        } catch (\Throwable $e) {
            LoggerUtils::getAuthLogger()->warning('Error ending the staff session', ['exception' => $e]);
        }
        unset($_SESSION['AuthenticationProvider']);
        LoggerUtils::getAuthLogger()->info('Staff session ended by a member sign-in in the same browser');
    }

    /** A staff sign-in replaces a member session in this browser (one principal per session, §6.4). */
    private static function replacePortalSession(): void
    {
        LoginAsUser::endForNewSignIn();
        $principal = PortalSessionStore::load();
        if (!$principal instanceof PortalPrincipal) {
            return;
        }

        PortalSessionStore::clear();
        SignInAudit::record(
            SignInAuditEvent::SESSION_ENDED,
            $principal->personId,
            $principal->method,
            SignInReason::REPLACED,
            $principal->actorUserId,
        );
    }

    /**
     * Signs out whatever this browser holds, staff or portal session (design §7),
     * and returns where to send it: a member goes back to the member sign-in page.
     * A remembered device is forgotten too (§6.2); the caller drops its cookie.
     */
    public static function signOut(): string
    {
        $principal = PortalSessionStore::load();
        $staffSignedIn = self::validateUserSessionIsActive(false);

        if ($principal instanceof PortalPrincipal) {
            SignInAudit::record(
                SignInAuditEvent::SESSION_ENDED,
                $principal->personId,
                $principal->method,
                SignInReason::SIGNED_OUT,
                $principal->actorUserId,
            );
            (new RememberedDeviceService())->forgetSessionDevice();
        }

        self::endSession(true);

        return $principal instanceof PortalPrincipal && !$staffSignedIn && !$principal->isAssisted()
            ? self::getPortalLoginURL()
            : self::getStaffSignInURL();
    }

    public static function authenticate(AuthenticationRequest $AuthenticationRequest): AuthenticationResult
    {
        $logger = LoggerUtils::getAppLogger();
        switch ($AuthenticationRequest::class) {
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
                $logger->error('Unknown AuthenticationRequest type supplied', ['providedAuthenticationRequestClass' => $AuthenticationRequest::class]);
                break;
        }

        $result = self::getAuthenticationProvider()->authenticate($AuthenticationRequest);

        if ($result->isAuthenticated && !$AuthenticationRequest instanceof APITokenAuthenticationRequest) {
            self::replacePortalSession();
        }

        if (null !== $result->nextStepURL) {
            $logger->debug('Authentication requires additional step: ' . $result->nextStepURL);
            RedirectUtils::redirect($result->nextStepURL);
        }

        if ($result->isAuthenticated && !$result->preventRedirect) {
            $redirectLocation = self::validateRedirectPath($_SESSION['location'] ?? null);
            unset($_SESSION['location']); // clear post-login redirect (one-time use)
            $redirectLocation ??= self::getDefaultLandingPath();
            
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

            return $result->isAuthenticated && self::endSessionIfSignInBlocked();
        } catch (\Exception $error) {
            LoggerUtils::getAuthLogger()->debug(
                'Error determining session authentication status.',
                ['exception' => $error]
            );

            return false;
        }
    }

    /**
     * @param bool $rememberPage false for a request that names no real page, so
     *                           sign-in does not land on a not-found page
     */
    public static function ensureAuthentication(bool $rememberPage = true): void
    {
        // This function differs from the semantic `ValidateUserSessionIsActive` in that it will
        // take corrective action to redirect the user to an appropriate login location
        // if the current session is not actually authenticated

        if (LoginAsUser::isActive()) {
            LoginAsUser::refuseStaffPage();
        }

        try {
            $result = self::getAuthenticationProvider()->validateUserSessionIsActive(true);
            $result->isAuthenticated = $result->isAuthenticated && self::endSessionIfSignInBlocked();
            // Auth providers will always include a `nextStepURL` if authentication fails.
            // Sometimes other actions may require a `nextStepURL` that should be enforced with
            // an authentication request (2FA, Expired Password, etc).
            if (!$result->isAuthenticated) {
                LoggerUtils::getAuthLogger()->debug(
                    'Session not authenticated.  Redirecting to login page'
                );
                self::redirectToStaffSignIn($rememberPage);
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
            self::redirectToStaffSignIn($rememberPage);
        }
    }

    /**
     * Store the originally requested URL in the session for post-login redirect,
     * whether or not this browser ever signed in (design §7). Using the session
     * (server-side) prevents open-redirect attacks via a crafted query parameter.
     */
    private static function redirectToStaffSignIn(bool $rememberPage): void
    {
        if ($rememberPage) {
            $safeUri = RedirectUtils::stripAndValidatePath($_SERVER['REQUEST_URI'] ?? '');
            if ($safeUri !== '') {
                $_SESSION['location'] = $safeUri;
            }
        }

        RedirectUtils::redirect(self::getStaffSignInURL());
    }

    /**
     * Ends the current session when its user may no longer sign in (member
     * sign-in design §4, blocks for everyone).
     *
     * @return bool false when the session was ended
     */
    private static function endSessionIfSignInBlocked(): bool
    {
        $user = self::getCurrentUser();
        $reason = $user->getSignInBlockedReason();
        if ($reason === null) {
            return true;
        }

        LoggerUtils::getAuthLogger()->warning('Ending session: account cannot sign in', [
            'username' => $user->getUserName(),
            'reason' => $reason,
        ]);
        self::endSession(true);

        return false;
    }

    /** The staff sign-in page, the `/admin` root (design §7). `/session/begin` redirects there. */
    public static function getStaffSignInURL(): string
    {
        return SystemURLs::getRootPath() . '/admin/';
    }

    public static function getPortalLoginURL(): string
    {
        return SystemURLs::getRootPath() . '/portal/login';
    }

    private static function hasSessionProvider(): bool
    {
        return self::$requestProvider !== null
            || (isset($_SESSION['AuthenticationProvider']) && $_SESSION['AuthenticationProvider'] instanceof IAuthenticationProvider);
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
     * Where a login lands when nothing else asked for a specific page.
     *
     * One rule (Member Portal design P10, #9863): a self-service account —
     * EditSelf and nothing else — lands in the Member Portal and cannot reach
     * the admin shell; every other login lands on the admin dashboard as before.
     */
    private static function getDefaultLandingPath(): string
    {
        $currentUser = self::getCurrentUser();
        if ($currentUser !== null && $currentUser->isEditSelfExclusive()) {
            return 'portal/';
        }

        return 'v2/dashboard';
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
