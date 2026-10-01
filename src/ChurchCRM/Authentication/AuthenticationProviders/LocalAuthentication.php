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
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Endroid\QrCode\QrCode;
use PragmaRX\Google2FA\Google2FA;

class LocalAuthentication implements IAuthenticationProvider
{
    private ?int $currentUserId = null;
    private ?User $currentUser = null;
    private ?bool $bPendingTwoFactorAuth = null;
    private bool $authenticated = false;
    private ?int $tLastOperationTimestamp = null;

    public function __serialize(): array
    {
        // Only the user id is stored. The User row holds the password hash, API key and
        // 2FA secret, which must not be written to session files.
        return [
            'currentUserId' => $this->currentUserId,
            'bPendingTwoFactorAuth' => $this->bPendingTwoFactorAuth,
            'authenticated' => $this->authenticated,
            'tLastOperationTimestamp' => $this->tLastOperationTimestamp,
        ];
    }

    public function __unserialize(array $data): void
    {
        // Restore the properties from serialized data
        $this->currentUserId = $data['currentUserId'] ?? null;
        $this->bPendingTwoFactorAuth = $data['bPendingTwoFactorAuth'] ?? null;
        $this->authenticated = $data['authenticated'] ?? false;
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
        if ($this->currentUserId === null) {
            return null;
        }
        if (!$this->currentUser instanceof User || $this->currentUser->getId() !== $this->currentUserId) {
            $this->currentUser = UserQuery::create()->findPk($this->currentUserId);
        }

        return $this->currentUser;
    }

    private function setCurrentUser(?User $user): void
    {
        $this->currentUser = $user;
        $this->currentUserId = $user?->getId();
    }

    public function endSession(): void
    {
        $user = $this->getCurrentUser();
        if ($user instanceof User && isset($_SESSION['iCurrentDeposit'])) {
            $user->setCurrentDeposit($_SESSION['iCurrentDeposit']);
            $user->save();
        }
        $this->setCurrentUser(null);
        $this->authenticated = false;
        $this->bPendingTwoFactorAuth = false;
    }

    private function prepareSuccessfulLoginOperations(): void
    {
        // Regenerate session ID to prevent session fixation attacks.
        // delete_old_session=true ensures the old session file is removed.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->authenticated = true;

        // Set the LastLogin and Increment the LoginCount
        $date = new \DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
        $this->currentUser->setLastLogin($date->format('Y-m-d H:i:s'));
        $this->currentUser->setLoginCount($this->currentUser->getLoginCount() + 1);
        $this->currentUser->setFailedLogins(0);
        $this->currentUser->save();

        // Create the Cart
        $_SESSION['aPeopleCart'] = [];

        // Initialize session variables (global message will be set only when needed)
        $this->tLastOperationTimestamp = time();

        // Pledge and payment preferences
        //$_SESSION['idefaultFY'] = CurrentFY(); // Improve the chance of getting the correct fiscal year assigned to new transactions
        $_SESSION['iCurrentDeposit'] = $this->currentUser->getCurrentDeposit();
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
            // Only a completed login may leave a user on the provider. A failed or
            // pending attempt must not, or validateUserSessionIsActive() could treat
            // the half-authenticated session as a logged-in one.
            $this->setCurrentUser(null);
            $this->authenticated = false;
            $this->bPendingTwoFactorAuth = false;
            $user = UserQuery::create()->findOneByUserName($AuthenticationRequest->username);
            if ($user === null) {
                // Set the error text
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Invalid login or password');
            } elseif ($user->isLocked()) {
                // Block the login if a maximum login failure count has been reached
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Too many failed logins: your account has been locked.  Please contact an administrator.');
                LoggerUtils::getAuthLogger()->warning('Authentication attempt for locked account', $logCtx);
            } elseif (!$user->isPasswordValid($AuthenticationRequest->password)) {
                // Does the password match?

                // Increment the FailedLogins
                $user->setFailedLogins($user->getFailedLogins() + 1);
                $user->save();
                if (!empty($user->getEmail()) && $user->isLocked()) {
                    LoggerUtils::getAuthLogger()->warning('Too many failed logins. The account has been locked', $logCtx);
                    $lockedEmail = new LockedEmail($user);
                    $lockedEmail->send();
                }
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Invalid login or password');
                LoggerUtils::getAuthLogger()->warning('Invalid login attempt', $logCtx);
            } elseif (($blockedReason = $user->getSignInBlockedReason()) !== null) {
                LoggerUtils::getAuthLogger()->warning('Login refused: account cannot sign in', $logCtx + ['reason' => $blockedReason]);
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Invalid login or password');
            } elseif ($user->is2FactorAuthEnabled()) {
                // User has enrolled in 2FA — redirect to verification step
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/session/two-factor';
                $this->setCurrentUser($user);
                $this->bPendingTwoFactorAuth = true;
                LoggerUtils::getAuthLogger()->info('User partially authenticated, pending 2FA', $logCtx);
            } elseif (SystemConfig::getBooleanValue('bRequire2FA') && !$user->is2FactorAuthEnabled()) {
                // Mandate is active but user has not enrolled. Stamp the grace period start (if not already
                // set) as a side-effect of getTwoFactorGraceStatus(), then let the user log in.
                // validateUserSessionIsActive() handles blocking once the window has expired.
                $this->setCurrentUser($user);
                $this->prepareSuccessfulLoginOperations();
                $authenticationResult->isAuthenticated = true;
                $this->currentUser->getTwoFactorGraceStatus(); // side-effect: lazy-stamps start timestamp
                LoggerUtils::getAuthLogger()->info('User logged in under 2FA mandate; grace period check applied', $logCtx);
            } else {
                $this->setCurrentUser($user);
                $this->prepareSuccessfulLoginOperations();
                $authenticationResult->isAuthenticated = true;
                LoggerUtils::getAuthLogger()->info('User successfully logged in without 2FA', $logCtx);
            }
        } elseif ($AuthenticationRequest instanceof LocalTwoFactorTokenRequest && $this->bPendingTwoFactorAuth && $this->getCurrentUser() instanceof User) {
            // Guard: if the account is already locked (e.g. from a prior OTP failure in
            // this session), reject without incrementing the counter or re-sending email.
            if ($this->currentUser->isLocked()) {
                // Clear the pending-2FA state so the session cannot resume OTP
                // brute-forcing after an admin resets usr_FailedLogins.
                // The user is re-read from the database on every request, which would
                // pick up the fresh state and make isLocked() return false again —
                // clearing these flags closes that re-entry window.
                $this->bPendingTwoFactorAuth = false;
                $this->setCurrentUser(null);
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/session/begin';
                return $authenticationResult;
            }
            if (($blockedReason = $this->currentUser->getSignInBlockedReason()) !== null) {
                LoggerUtils::getAuthLogger()->warning('2FA login refused: account cannot sign in', $logCtx + ['reason' => $blockedReason]);
                $this->bPendingTwoFactorAuth = false;
                $this->currentUser = null;
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Invalid login or password');
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
                    $this->setCurrentUser(null);
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

        // A session is only authenticated once a login fully completed. Holding a
        // user is not enough: a failed password or a pending 2FA step leaves the
        // provider in the session too.
        if (!$this->authenticated || $this->currentUserId === null) {
            $authenticationResult->isAuthenticated = false;
            LoggerUtils::getAuthLogger()->debug('No active user session.');

            return $authenticationResult;
        }

        // The session stores only the user id, so the user is read from the database
        // on the first call of each request. A deleted user ends the session.
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            LoggerUtils::getAuthLogger()->debug(
                'User with active session no longer exists in the database.  Expiring session',
                ['userId' => $this->currentUserId]
            );
            AuthenticationManager::endSession();
            $authenticationResult->isAuthenticated = false;

            return $authenticationResult;
        }
        $logCtx = [
            'username'     => $user->getUserName(),
            'userFullName' => $user->getName(),
        ];
        LoggerUtils::getAuthLogger()->debug('Processing session for user', $logCtx);

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
        if ($user->getNeedPasswordChange() && !$IsUserOnPasswordChangePageNow) {
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
        if (SystemConfig::getBooleanValue('bRequire2FA') && !$user->is2FactorAuthEnabled() && !$isOnEnrollmentPage) {
            $graceStatus = $user->getTwoFactorGraceStatus();
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
