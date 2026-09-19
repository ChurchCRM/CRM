<?php

namespace ChurchCRM\Authentication\AuthenticationProviders;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationResult;
use ChurchCRM\Authentication\Requests\AuthenticationRequest;
use ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest;
use ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\users\LockedEmail;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Service\ImpersonationService;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Endroid\QrCode\QrCode;
use PragmaRX\Google2FA\Google2FA;

class LocalAuthentication implements IAuthenticationProvider
{
    private ?User $currentUser = null;
    private ?bool $bPendingTwoFactorAuth = null;
    private ?int $tLastOperationTimestamp = null;

    public function __serialize(): array
    {
        // Explicitly serialize only the essential properties that need to persist across requests
        return [
            'currentUser' => $this->currentUser,
            'bPendingTwoFactorAuth' => $this->bPendingTwoFactorAuth,
            'tLastOperationTimestamp' => $this->tLastOperationTimestamp,
        ];
    }

    public function __unserialize(array $data): void
    {
        // Restore the properties from serialized data
        $this->currentUser = $data['currentUser'] ?? null;
        $this->bPendingTwoFactorAuth = $data['bPendingTwoFactorAuth'] ?? null;
        $this->tLastOperationTimestamp = $data['tLastOperationTimestamp'] ?? null;
    }

    public function getPasswordChangeURL(): string
    {
        // this shouldn't really be called, but it's necessary to implement the IAuthenticationProvider interface
        return SystemURLs::getRootPath() . '/v2/user/current/changepassword';
    }


    public static function getTwoFactorQRCode($username, $secret): QrCode
    {
        $google2fa = new Google2FA();
        $g2faUrl = $google2fa->getQRCodeUrl(
            SystemConfig::getValue('s2FAApplicationName'),
            $username,
            $secret
        );

        return new QrCode(
            data: $g2faUrl,
            size: 300
        );
    }

    public function getCurrentUser(): ?User
    {
        return $this->currentUser;
    }

    public function endSession(): void
    {
        if ($this->currentUser instanceof User) {
            //$this->currentUser->setDefaultFY($_SESSION['idefaultFY']);
            if (isset($_SESSION['iCurrentDeposit'])) {
                $this->currentUser->setCurrentDeposit($_SESSION['iCurrentDeposit']);
                $this->currentUser->save();
            }
            $this->currentUser = null;
        }
    }

    /**
     * Establish the PHP session state for `$this->currentUser`.
     *
     * This is the half of a successful login that is purely about the *session*
     * payload: the per-session flags and caches the rest of the application
     * reads. It deliberately contains neither login bookkeeping (last login
     * stamp, login/failed-login counters) nor the session id rotation, so that
     * it can be reused by flows that are not logins — notably the admin
     * masquerade in {@see \ChurchCRM\Service\ImpersonationService}, which must
     * not make the impersonated account look as though the user signed in.
     *
     * @see prepareSuccessfulLoginOperations() for the full post-login sequence.
     */
    private function establishSessionForUser(): void
    {
        $_SESSION['bManageGroups'] = $this->currentUser->isManageGroupsEnabled();
        $_SESSION['bFinance'] = $this->currentUser->isFinanceEnabled();

        // Create the Cart
        $_SESSION['aPeopleCart'] = [];

        // Initialize session variables (global message will be set only when needed)
        $this->tLastOperationTimestamp = time();

        $_SESSION['bHasMagicQuotes'] = 0;

        // Pledge and payment preferences
        //$_SESSION['idefaultFY'] = CurrentFY(); // Improve the chance of getting the correct fiscal year assigned to new transactions
        $_SESSION['iCurrentDeposit'] = $this->currentUser->getCurrentDeposit();
    }

    /**
     * Everything that must happen after credentials (and 2FA, where enrolled)
     * have been accepted: record the login against the account, then establish
     * the session.
     */
    private function prepareSuccessfulLoginOperations(): void
    {
        // Regenerate session ID to prevent session fixation attacks.
        // delete_old_session=true ensures the old session file is removed.
        // This belongs to the *login* only: it is the transition from an
        // anonymous (possibly attacker-supplied) id to an authenticated one.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->establishSessionForUser();

        // Set the LastLogin and Increment the LoginCount
        $date = new \DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
        $this->currentUser->setLastLogin($date->format('Y-m-d H:i:s'));
        $this->currentUser->setLoginCount($this->currentUser->getLoginCount() + 1);
        $this->currentUser->setFailedLogins(0);
        $this->currentUser->save();
    }

    /**
     * Establish this provider's session as `$user` WITHOUT authenticating them.
     *
     * Only the impersonation flow may call this, and only after it has proven
     * that the *caller* is an administrator — see
     * {@see \ChurchCRM\Service\ImpersonationService}. Compared with a password
     * login this skips the password check, the 2FA prompt, the failed-login
     * counters and the `usr_LastLogin` / `usr_LoginCount` bookkeeping; the
     * session state itself is identical because both paths share
     * establishSessionForUser().
     *
     * The session id is deliberately NOT rotated. Both masquerade transitions
     * happen inside an already-authenticated session in the same browser, so
     * there is no fixation window to close — while rotating with
     * delete_old_session=true destroys the session out from under any request
     * the page being left behind still has in flight, which 401s and bounces
     * the browser to the login page (the global jQuery 401 handler in
     * CRMJSOM.js redirects on sight). Rotating with delete_old_session=false
     * would be worse: it leaves the *previous* identity reachable on the old
     * id until GC.
     */
    public function establishSessionAsUser(User $user): void
    {
        $this->currentUser = $user;
        $this->bPendingTwoFactorAuth = false;
        $this->establishSessionForUser();
    }

    public function authenticate(AuthenticationRequest $AuthenticationRequest): AuthenticationResult
    {
        if (!($AuthenticationRequest instanceof LocalUsernamePasswordRequest || $AuthenticationRequest instanceof LocalTwoFactorTokenRequest)) {
            throw new \Exception('Unable to process request as LocalUsernamePasswordRequest or LocalTwoFactorTokenRequest');
        }

        $authenticationResult = new AuthenticationResult();
        $logCtx = ['username' => $AuthenticationRequest->username];
        if ($AuthenticationRequest instanceof LocalUsernamePasswordRequest) {
            LoggerUtils::getAuthLogger()->debug('Processing local login', $logCtx);
            // Get the information for the selected user
            $this->currentUser = UserQuery::create()->findOneByUserName($AuthenticationRequest->username);
            if ($this->currentUser === null) {
                // Set the error text
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Invalid login or password');
            } elseif ($this->currentUser->isLocked()) {
                // Block the login if a maximum login failure count has been reached
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Too many failed logins: your account has been locked.  Please contact an administrator.');
                LoggerUtils::getAuthLogger()->warning('Authentication attempt for locked account', $logCtx);
            } elseif (!$this->currentUser->isPasswordValid($AuthenticationRequest->password)) {
                // Does the password match?

                // Increment the FailedLogins
                $this->currentUser->setFailedLogins($this->currentUser->getFailedLogins() + 1);
                $this->currentUser->save();
                if (!empty($this->currentUser->getEmail()) && $this->currentUser->isLocked()) {
                    LoggerUtils::getAuthLogger()->warning('Too many failed logins. The account has been locked', $logCtx);
                    $lockedEmail = new LockedEmail($this->currentUser);
                    $lockedEmail->send();
                }
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Invalid login or password');
                LoggerUtils::getAuthLogger()->warning('Invalid login attempt', $logCtx);
            } elseif ($this->currentUser->is2FactorAuthEnabled()) {
                // User has enrolled in 2FA — redirect to verification step
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/session/two-factor';
                $this->bPendingTwoFactorAuth = true;
                LoggerUtils::getAuthLogger()->info('User partially authenticated, pending 2FA', $logCtx);
            } elseif (SystemConfig::getBooleanValue('bRequire2FA') && !$this->currentUser->is2FactorAuthEnabled()) {
                // Mandate is active but user has not enrolled. Stamp the grace period start (if not already
                // set) as a side-effect of getTwoFactorGraceStatus(), then let the user log in.
                // validateUserSessionIsActive() handles blocking once the window has expired.
                $this->prepareSuccessfulLoginOperations();
                $authenticationResult->isAuthenticated = true;
                $this->currentUser->getTwoFactorGraceStatus(); // side-effect: lazy-stamps start timestamp
                LoggerUtils::getAuthLogger()->info('User logged in under 2FA mandate; grace period check applied', $logCtx);
            } else {
                $this->prepareSuccessfulLoginOperations();
                $authenticationResult->isAuthenticated = true;
                LoggerUtils::getAuthLogger()->info('User successfully logged in without 2FA', $logCtx);
            }
        } elseif ($AuthenticationRequest instanceof LocalTwoFactorTokenRequest && $this->bPendingTwoFactorAuth) {
            // Guard: if the account is already locked (e.g. from a prior OTP failure in
            // this session), reject without incrementing the counter or re-sending email.
            if ($this->currentUser->isLocked()) {
                // Clear the pending-2FA state so the session cannot resume OTP
                // brute-forcing after an admin resets usr_FailedLogins.
                // validateUserSessionIsActive() calls currentUser->reload(), which
                // would pick up the fresh DB state and make isLocked() return false
                // again — clearing these flags closes that re-entry window.
                $this->bPendingTwoFactorAuth = false;
                $this->currentUser = null;
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/session/begin';
                return $authenticationResult;
            }
            if ($this->currentUser->isTwoFACodeValid($AuthenticationRequest->TwoFACode)) {
                $this->prepareSuccessfulLoginOperations();
                $authenticationResult->isAuthenticated = true;
                $this->bPendingTwoFactorAuth = false;
                LoggerUtils::getAuthLogger()->info('User successfully logged in with 2FA', $logCtx);
            } elseif ($this->currentUser->isTwoFaRecoveryCodeValid($AuthenticationRequest->TwoFACode)) {
                $this->prepareSuccessfulLoginOperations();
                $authenticationResult->isAuthenticated = true;
                $this->bPendingTwoFactorAuth = false;
                LoggerUtils::getAuthLogger()->info('User successfully logged in with 2FA Recovery Code', $logCtx);
            } else {
                // Count OTP failures toward account lockout, mirroring the wrong-password branch.
                // Without this, an attacker with a valid password can brute-force the 6-digit
                // TOTP space without limit (GHSA-f2fq-4rmp-9x8c).
                $this->currentUser->setFailedLogins($this->currentUser->getFailedLogins() + 1);
                $this->currentUser->save();
                if (!empty($this->currentUser->getEmail()) && $this->currentUser->isLocked()) {
                    LoggerUtils::getAuthLogger()->warning('Too many failed 2FA attempts. The account has been locked', $logCtx);
                    $lockedEmail = new LockedEmail($this->currentUser);
                    $lockedEmail->send();
                }
                LoggerUtils::getAuthLogger()->info('Invalid 2FA code provided by partially authenticated user', $logCtx);
                $authenticationResult->isAuthenticated = false;
                if ($this->currentUser->isLocked()) {
                    // Account is now locked — clear the pending-2FA state so the session
                    // cannot resume OTP brute-forcing after an admin counter reset,
                    // then redirect back to login.
                    $this->bPendingTwoFactorAuth = false;
                    $this->currentUser = null;
                    $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/session/begin';
                } else {
                    $recoveryParam = $AuthenticationRequest->isRecoveryMode ? '&recovery' : '';
                    $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/session/two-factor?invalid=1' . $recoveryParam;
                }
            }
        }

        return $authenticationResult;
    }

    public function validateUserSessionIsActive(bool $updateLastOperationTimestamp): AuthenticationResult
    {
        $authenticationResult = new AuthenticationResult();

        // First check to see if a `user` key exists on the session.
        if (!$this->currentUser instanceof User) {
            $authenticationResult->isAuthenticated = false;
            LoggerUtils::getAuthLogger()->debug('No active user session.');

            return $authenticationResult;
        }
        $logCtx = [
            'username'     => $this->currentUser->getUserName(),
            'userFullName' => $this->currentUser->getName(),
        ];
        LoggerUtils::getAuthLogger()->debug('Processing session for user', $logCtx);

        // Next, make sure the user in the session still exists in the database.
        try {
            $this->currentUser->reload();
        } catch (\Exception $exc) {
            LoggerUtils::getAuthLogger()->debug(
                'User with active session no longer exists in the database.  Expiring session',
                array_merge($logCtx, ['exception' => $exc])
            );
            AuthenticationManager::endSession();
            $authenticationResult->isAuthenticated = false;

            return $authenticationResult;
        }

        // Next, check for login timeout.  If login has expired, redirect to login page
        if (SystemConfig::getIntValue('iSessionTimeout') > 0) {
            if ((time() - $this->tLastOperationTimestamp) > SystemConfig::getIntValue('iSessionTimeout')) {
                LoggerUtils::getAuthLogger()->debug('User session timed out', $logCtx);
                $authenticationResult->isAuthenticated = false;

                return $authenticationResult;
            } elseif ($updateLastOperationTimestamp) {
                $this->tLastOperationTimestamp = time();
            }
        }

        // Next, if this user needs to change password, send to that page
        // but don't redirect them if they're already on the password change page.
        //
        // NOTE: previously this used strict === against getPasswordChangeURL(),
        // which is fragile — a trailing slash, a query string, or path
        // normalization differences between web servers (FrankenPHP in
        // particular reports REQUEST_URI slightly differently from Apache)
        // cause the comparison to fail, the user gets redirected to the
        // password change page, the check fails again, and the browser hits
        // "too many redirects". See #8405.
        //
        // Use str_contains for a tolerant check, matching the pattern used by
        // the 2FA enrollment branch a few lines below.
        $IsUserOnPasswordChangePageNow = str_contains($_SERVER['REQUEST_URI'] ?? '', '/v2/user/current/changepassword');
        // A masquerading administrator (#9843) is not the account's owner: the
        // account's own obligations — a forced password change, 2FA enrolment —
        // are theirs to meet on their next real login, not the administrator's to
        // meet on their behalf. Enforcing them here trapped the administrator on
        // the change-password page, and the banner's Exit request was redirected
        // there too (review, 2026-09-18).
        $impersonating = ImpersonationService::isActive();
        if ($this->currentUser->getNeedPasswordChange() && !$IsUserOnPasswordChangePageNow && !$impersonating) {
            LoggerUtils::getAuthLogger()->info('User needs password change; redirecting to password change', $logCtx);
            $authenticationResult->isAuthenticated = false;
            $authenticationResult->nextStepURL = $this->getPasswordChangeURL();
        }

        // If 2FA is required and user hasn't enrolled, check grace period status.
        // block only when the grace window has expired or no grace period is configured.
        $enrollmentURL = SystemURLs::getRootPath() . '/v2/user/current/manage2fa';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $isOnEnrollmentPage = str_contains($requestUri, '/v2/user/current/manage2fa')
            || str_contains($requestUri, '/v2/user/current/enroll2fa');
        if (SystemConfig::getBooleanValue('bRequire2FA') && !$this->currentUser->is2FactorAuthEnabled() && !$isOnEnrollmentPage && !$impersonating) {
            $graceStatus = $this->currentUser->getTwoFactorGraceStatus();
            if ($graceStatus === 'expired' || $graceStatus === 'immediate') {
                LoggerUtils::getAuthLogger()->info('2FA grace period expired or immediate; redirecting to enrollment', $logCtx);
                $authenticationResult->nextStepURL = $enrollmentURL;
                // NOTE (pre-existing limitation): setting nextStepURL here does NOT block API or
                // API-key-authenticated requests.  AuthMiddleware only enforces nextStepURL for
                // browser requests (inside the `isBrowserRequest` branch); session-based API
                // calls see isAuthenticated=true and are passed straight to the handler.
                // A follow-up issue should add 403 enforcement in AuthMiddleware for non-browser
                // requests when nextStepURL signals mandatory 2FA enrollment.
            }
            // 'within-grace' → allow through; the banner in Header.php handles the warning.
        }

        // Finally, if the above tests pass, this user "is authenticated"
        $authenticationResult->isAuthenticated = true;
        LoggerUtils::getAuthLogger()->debug('Session validated for user', $logCtx);

        return $authenticationResult;
    }
}
