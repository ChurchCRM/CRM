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
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Endroid\QrCode\QrCode;
use PragmaRX\Google2FA\Google2FA;

class LocalAuthentication implements IAuthenticationProvider
{
    private const SESSION_STATE_VERSION = 7;
    private const PENDING_TWO_FACTOR_TTL_SECONDS = 600;
    private const SECURITY_ACTION_AUTHENTICATION_TTL_SECONDS = 300;

    private ?User $currentUser = null;
    private ?bool $bPendingTwoFactorAuth = null;
    private ?int $tLastOperationTimestamp = null;
    private bool $isPrimaryAuthenticationComplete = false;
    private ?string $primaryAuthenticationPasswordHash = null;
    private ?string $primaryAuthenticationTwoFactorSecret = null;
    private ?string $primaryAuthenticationRecoveryCodes = null;
    private ?int $securityActionAuthenticationTimestamp = null;
    private ?string $securityActionAuthenticationPasswordHash = null;
    private ?string $securityActionAuthenticationTwoFactorSecret = null;
    private ?string $securityActionAuthenticationRecoveryCodes = null;

    public function __serialize(): array
    {
        // Explicitly serialize only the essential properties that need to persist across requests
        return [
            'sessionStateVersion' => self::SESSION_STATE_VERSION,
            'currentUserId' => $this->currentUser?->getId(),
            'bPendingTwoFactorAuth' => $this->bPendingTwoFactorAuth,
            'tLastOperationTimestamp' => $this->tLastOperationTimestamp,
            'isPrimaryAuthenticationComplete' => $this->isPrimaryAuthenticationComplete,
            'primaryAuthenticationPasswordHash' => $this->primaryAuthenticationPasswordHash,
            'primaryAuthenticationTwoFactorSecret' => $this->primaryAuthenticationTwoFactorSecret,
            'primaryAuthenticationRecoveryCodes' => $this->primaryAuthenticationRecoveryCodes,
            'securityActionAuthenticationTimestamp' => $this->securityActionAuthenticationTimestamp,
            'securityActionAuthenticationPasswordHash' => $this->securityActionAuthenticationPasswordHash,
            'securityActionAuthenticationTwoFactorSecret' => $this->securityActionAuthenticationTwoFactorSecret,
            'securityActionAuthenticationRecoveryCodes' => $this->securityActionAuthenticationRecoveryCodes,
        ];
    }

    public function __unserialize(array $data): void
    {
        // Invalidate every provider serialized before the fail-closed session
        // schema. Some legacy failed-login sessions contain a candidate User.
        if (($data['sessionStateVersion'] ?? null) !== self::SESSION_STATE_VERSION) {
            $this->clearAuthenticationState();
            return;
        }

        // Restore the properties from serialized data
        $this->currentUser = isset($data['currentUserId']) ? UserQuery::create()->findPk((int) $data['currentUserId']) : null;
        $this->bPendingTwoFactorAuth = $data['bPendingTwoFactorAuth'] ?? null;
        $this->tLastOperationTimestamp = $data['tLastOperationTimestamp'] ?? null;
        $this->isPrimaryAuthenticationComplete = $data['isPrimaryAuthenticationComplete'] ?? false;
        $this->primaryAuthenticationPasswordHash = $data['primaryAuthenticationPasswordHash'] ?? null;
        $this->primaryAuthenticationTwoFactorSecret = $data['primaryAuthenticationTwoFactorSecret'] ?? null;
        $this->primaryAuthenticationRecoveryCodes = $data['primaryAuthenticationRecoveryCodes'] ?? null;
        $this->securityActionAuthenticationTimestamp = $data['securityActionAuthenticationTimestamp'] ?? null;
        $this->securityActionAuthenticationPasswordHash = $data['securityActionAuthenticationPasswordHash'] ?? null;
        $this->securityActionAuthenticationTwoFactorSecret = $data['securityActionAuthenticationTwoFactorSecret'] ?? null;
        $this->securityActionAuthenticationRecoveryCodes = $data['securityActionAuthenticationRecoveryCodes'] ?? null;

        if (!$this->isPrimaryAuthenticationComplete && $this->bPendingTwoFactorAuth !== true) {
            $this->clearAuthenticationState();
        }
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

    /** @return array{passwordHash: string, twoFactorSecret: ?string, recoveryCodes: ?string} */
    public function getAuthenticatedSecurityMarkers(): array
    {
        if (!$this->currentUser instanceof User
            || !$this->isPrimaryAuthenticationComplete
            || $this->bPendingTwoFactorAuth === true
            || $this->primaryAuthenticationPasswordHash === null) {
            throw new \LogicException('No completed local authentication markers are available');
        }

        return [
            'passwordHash' => $this->primaryAuthenticationPasswordHash,
            'twoFactorSecret' => $this->primaryAuthenticationTwoFactorSecret,
            'recoveryCodes' => $this->primaryAuthenticationRecoveryCodes,
        ];
    }

    public function isPrimaryAuthenticationComplete(): bool
    {
        return $this->currentUser instanceof User
            && $this->isPrimaryAuthenticationComplete
            && $this->bPendingTwoFactorAuth !== true
            && $this->primaryAuthenticationPasswordHash !== null;
    }

    /**
     * A security-action grant has a fixed lifetime and is bound to the exact
     * password/factor state that was authenticated. Ordinary session activity
     * never extends it.
     */
    public function hasRecentSecurityActionAuthentication(): bool
    {
        if (!$this->isPrimaryAuthenticationComplete()
            || $this->securityActionAuthenticationTimestamp === null
            || $this->securityActionAuthenticationPasswordHash === null) {
            $this->clearSecurityActionAuthentication();

            return false;
        }

        $age = time() - $this->securityActionAuthenticationTimestamp;
        if ($age < 0 || $age > self::SECURITY_ACTION_AUTHENTICATION_TTL_SECONDS) {
            $this->clearSecurityActionAuthentication();

            return false;
        }

        $passwordStateMatches = hash_equals(
            $this->primaryAuthenticationPasswordHash,
            $this->securityActionAuthenticationPasswordHash
        );
        $twoFactorStateMatches = $this->securityActionAuthenticationTwoFactorSecret === null
            ? $this->primaryAuthenticationTwoFactorSecret === null
            : $this->primaryAuthenticationTwoFactorSecret !== null
                && hash_equals(
                    $this->primaryAuthenticationTwoFactorSecret,
                    $this->securityActionAuthenticationTwoFactorSecret
                );
        $recoveryStateMatches = $this->securityActionAuthenticationRecoveryCodes === null
            ? $this->primaryAuthenticationRecoveryCodes === null
            : $this->primaryAuthenticationRecoveryCodes !== null
                && hash_equals(
                    $this->primaryAuthenticationRecoveryCodes,
                    $this->securityActionAuthenticationRecoveryCodes
                );
        if (!$passwordStateMatches || !$twoFactorStateMatches || !$recoveryStateMatches) {
            $this->clearSecurityActionAuthentication();

            return false;
        }

        return true;
    }

    /** Re-enter the current password to obtain a short, fixed security grant. */
    public function reauthenticateForSecurityAction(string $password): bool
    {
        if (!$this->isPrimaryAuthenticationComplete()) {
            return false;
        }

        try {
            $this->currentUser->reload();
        } catch (\Throwable $exception) {
            $this->clearAuthenticationState();

            return false;
        }

        $storedSecret = $this->currentUser->getTwoFactorAuthSecret();
        $storedRecoveryCodes = $this->currentUser->getTwoFactorAuthRecoveryCodes();
        $passwordStateMatches = hash_equals(
            $this->currentUser->getPassword(),
            $this->primaryAuthenticationPasswordHash
        );
        $twoFactorStateMatches = $storedSecret === null
            ? $this->primaryAuthenticationTwoFactorSecret === null
            : $this->primaryAuthenticationTwoFactorSecret !== null
                && hash_equals($storedSecret, $this->primaryAuthenticationTwoFactorSecret);
        $recoveryStateMatches = $storedRecoveryCodes === null
            ? $this->primaryAuthenticationRecoveryCodes === null
            : $this->primaryAuthenticationRecoveryCodes !== null
                && hash_equals($storedRecoveryCodes, $this->primaryAuthenticationRecoveryCodes);
        if (!$passwordStateMatches || !$twoFactorStateMatches || !$recoveryStateMatches) {
            $this->clearAuthenticationState();

            return false;
        }

        $primaryAuthentication = $this->currentUser->authenticatePrimaryPassword($password);
        if (!$primaryAuthentication['isPasswordValid']
            || $primaryAuthentication['isLocked']
            || $primaryAuthentication['passwordHash'] === null) {
            if ($primaryAuthentication['isLocked']) {
                $this->clearAuthenticationState();
            } else {
                $this->clearSecurityActionAuthentication();
            }

            return false;
        }

        $authenticatedSecret = $primaryAuthentication['twoFactorSecret'];
        $authenticatedRecoveryCodes = $primaryAuthentication['recoveryCodes'];
        $secretStillMatches = $authenticatedSecret === null
            ? $this->primaryAuthenticationTwoFactorSecret === null
            : $this->primaryAuthenticationTwoFactorSecret !== null
                && hash_equals($authenticatedSecret, $this->primaryAuthenticationTwoFactorSecret);
        $recoveryStillMatches = $authenticatedRecoveryCodes === null
            ? $this->primaryAuthenticationRecoveryCodes === null
            : $this->primaryAuthenticationRecoveryCodes !== null
                && hash_equals($authenticatedRecoveryCodes, $this->primaryAuthenticationRecoveryCodes);
        if (!$secretStillMatches
            || !$recoveryStillMatches
            || !$this->currentUser->finalizeSuccessfulAuthentication(
                $primaryAuthentication['passwordHash'],
                $authenticatedSecret,
                $authenticatedRecoveryCodes
            )) {
            $this->clearAuthenticationState();

            return false;
        }

        if (!$this->rotateSessionIdentifier()) {
            $this->clearAuthenticationState();
            LoggerUtils::getAuthLogger()->warning('Security reauthentication failed because the session ID could not be rotated');

            return false;
        }

        $this->primaryAuthenticationPasswordHash = $primaryAuthentication['passwordHash'];
        $this->primaryAuthenticationTwoFactorSecret = $authenticatedSecret;
        $this->primaryAuthenticationRecoveryCodes = $authenticatedRecoveryCodes;
        $this->issueSecurityActionAuthentication();

        return true;
    }

    /**
     * Rebind this one authenticated session after it changes its own password
     * or 2FA enrollment state. Other sessions retain their old markers and are
     * revoked on their next validation.
     */
    public function synchronizeAuthenticatedPasswordHash(string $expectedPasswordHash): void
    {
        $this->synchronizeAuthenticatedSecurityState(
            $expectedPasswordHash,
            $this->primaryAuthenticationTwoFactorSecret,
            $this->primaryAuthenticationRecoveryCodes
        );
        $this->rotateAuthenticatedSessionAfterSecurityMutation();
        // A successful self-service password change includes current-password
        // verification, so it establishes a fresh security-action grant.
        $this->issueSecurityActionAuthentication();
    }

    public function synchronizeAuthenticatedTwoFactorSecret(?string $expectedTwoFactorSecret): void
    {
        if ($this->primaryAuthenticationPasswordHash === null) {
            throw new \LogicException('Cannot synchronize authentication state without a password marker');
        }

        $grantWasValid = $this->hasRecentSecurityActionAuthentication();
        $this->synchronizeAuthenticatedSecurityState(
            $this->primaryAuthenticationPasswordHash,
            $expectedTwoFactorSecret,
            null
        );
        if ($grantWasValid) {
            $this->rebindSecurityActionAuthenticationMarkers();
        } else {
            $this->clearSecurityActionAuthentication();
        }
        $this->rotateAuthenticatedSessionAfterSecurityMutation();
    }

    public function synchronizeAuthenticatedRecoveryCodes(?string $expectedRecoveryCodes): void
    {
        if ($this->primaryAuthenticationPasswordHash === null) {
            throw new \LogicException('Cannot synchronize authentication state without a password marker');
        }

        $grantWasValid = $this->hasRecentSecurityActionAuthentication();
        $this->synchronizeAuthenticatedSecurityState(
            $this->primaryAuthenticationPasswordHash,
            $this->primaryAuthenticationTwoFactorSecret,
            $expectedRecoveryCodes
        );
        if ($grantWasValid) {
            $this->rebindSecurityActionAuthenticationMarkers();
        } else {
            $this->clearSecurityActionAuthentication();
        }
        $this->rotateAuthenticatedSessionAfterSecurityMutation();
    }

    /**
     * Invalidate any cloned copy of the acting browser cookie after a security
     * credential is changed. The CSRF token remains in the rotated session.
     */
    public function rotateAuthenticatedSessionAfterSecurityMutation(): void
    {
        if (!$this->isPrimaryAuthenticationComplete() || !$this->rotateSessionIdentifier()) {
            $this->clearAuthenticationState();
            LoggerUtils::getAuthLogger()->warning(
                'Security credential mutation failed closed because the session ID could not be rotated'
            );

            throw new \RuntimeException('Unable to rotate the authenticated session');
        }
    }

    private function synchronizeAuthenticatedSecurityState(
        string $expectedPasswordHash,
        ?string $expectedTwoFactorSecret,
        ?string $expectedRecoveryCodes
    ): void {
        if (!$this->currentUser instanceof User
            || !$this->isPrimaryAuthenticationComplete
            || $this->bPendingTwoFactorAuth === true) {
            throw new \LogicException('Cannot synchronize incomplete authentication state');
        }

        $this->currentUser->reload();
        $currentPasswordHash = $this->currentUser->getPassword();
        $currentTwoFactorSecret = $this->currentUser->getTwoFactorAuthSecret();
        $currentRecoveryCodes = $this->currentUser->getTwoFactorAuthRecoveryCodes();
        $passwordStateMatches = hash_equals($currentPasswordHash, $expectedPasswordHash);
        $twoFactorStateMatches = $currentTwoFactorSecret === null
            ? $expectedTwoFactorSecret === null
            : $expectedTwoFactorSecret !== null
                && hash_equals($currentTwoFactorSecret, $expectedTwoFactorSecret);
        $recoveryStateMatches = $currentRecoveryCodes === null
            ? $expectedRecoveryCodes === null
            : $expectedRecoveryCodes !== null
                && hash_equals($currentRecoveryCodes, $expectedRecoveryCodes);
        if (!$passwordStateMatches || !$twoFactorStateMatches || !$recoveryStateMatches) {
            $this->clearAuthenticationState();
            throw new \RuntimeException('Account security state changed while synchronizing the session');
        }

        $this->primaryAuthenticationPasswordHash = $expectedPasswordHash;
        $this->primaryAuthenticationTwoFactorSecret = $expectedTwoFactorSecret;
        $this->primaryAuthenticationRecoveryCodes = $expectedRecoveryCodes;
    }

    public function endSession(): void
    {
        try {
            if ($this->currentUser instanceof User) {
                //$this->currentUser->setDefaultFY($_SESSION['idefaultFY']);
                if (isset($_SESSION['iCurrentDeposit'])) {
                    $this->currentUser->setCurrentDeposit($_SESSION['iCurrentDeposit']);
                    $this->currentUser->save();
                }
            }
        } catch (\Throwable $exception) {
            LoggerUtils::getAuthLogger()->warning(
                'Unable to persist user preferences while ending session',
                ['exception' => $exception]
            );
        } finally {
            $this->clearAuthenticationState();
        }
    }

    private function clearAuthenticationState(): void
    {
        $this->clearSecurityActionAuthentication();
        $this->currentUser = null;
        $this->bPendingTwoFactorAuth = false;
        $this->tLastOperationTimestamp = null;
        $this->isPrimaryAuthenticationComplete = false;
        $this->primaryAuthenticationPasswordHash = null;
        $this->primaryAuthenticationTwoFactorSecret = null;
        $this->primaryAuthenticationRecoveryCodes = null;
    }

    private function issueSecurityActionAuthentication(): void
    {
        if (!$this->isPrimaryAuthenticationComplete()) {
            $this->clearSecurityActionAuthentication();

            return;
        }

        $this->securityActionAuthenticationTimestamp = time();
        $this->securityActionAuthenticationPasswordHash = $this->primaryAuthenticationPasswordHash;
        $this->securityActionAuthenticationTwoFactorSecret = $this->primaryAuthenticationTwoFactorSecret;
        $this->securityActionAuthenticationRecoveryCodes = $this->primaryAuthenticationRecoveryCodes;
    }

    /** Preserve the fixed grant deadline while binding it to new factor state. */
    private function rebindSecurityActionAuthenticationMarkers(): void
    {
        if (!$this->isPrimaryAuthenticationComplete()
            || $this->securityActionAuthenticationTimestamp === null) {
            $this->clearSecurityActionAuthentication();

            return;
        }

        $this->securityActionAuthenticationPasswordHash = $this->primaryAuthenticationPasswordHash;
        $this->securityActionAuthenticationTwoFactorSecret = $this->primaryAuthenticationTwoFactorSecret;
        $this->securityActionAuthenticationRecoveryCodes = $this->primaryAuthenticationRecoveryCodes;
    }

    private function clearSecurityActionAuthentication(): void
    {
        if ($this->currentUser instanceof User) {
            $this->currentUser->clearProvisional2FAKey();
        }
        $this->securityActionAuthenticationTimestamp = null;
        $this->securityActionAuthenticationPasswordHash = null;
        $this->securityActionAuthenticationTwoFactorSecret = null;
        $this->securityActionAuthenticationRecoveryCodes = null;
    }

    private function rotateSessionIdentifier(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE && session_regenerate_id(true);
    }

    private function preparePendingTwoFactorAuthentication(): bool
    {
        // Rotate once after the password succeeds, then rotate again after the
        // second factor succeeds in prepareSuccessfulLoginOperations().
        if (!$this->rotateSessionIdentifier()) {
            $this->clearAuthenticationState();
            LoggerUtils::getAuthLogger()->warning('Primary authentication failed because the session ID could not be rotated');

            return false;
        }

        $this->bPendingTwoFactorAuth = true;
        $this->tLastOperationTimestamp = time();
        $this->isPrimaryAuthenticationComplete = false;

        return true;
    }

    private function hasPendingTwoFactorAuthenticationExpired(): bool
    {
        if ($this->tLastOperationTimestamp === null) {
            return true;
        }

        $configuredSessionTimeout = SystemConfig::getIntValue('iSessionTimeout');
        $pendingTimeout = self::PENDING_TWO_FACTOR_TTL_SECONDS;
        if ($configuredSessionTimeout > 0) {
            $pendingTimeout = min($pendingTimeout, $configuredSessionTimeout);
        }

        return (time() - $this->tLastOperationTimestamp) > $pendingTimeout;
    }

    private function prepareSuccessfulLoginOperations(): bool
    {
        $date = new \DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
        if ($this->primaryAuthenticationPasswordHash === null
            || !$this->currentUser->finalizeSuccessfulAuthentication(
                $this->primaryAuthenticationPasswordHash,
                $this->primaryAuthenticationTwoFactorSecret,
                $this->primaryAuthenticationRecoveryCodes,
                $date->format('Y-m-d H:i:s')
            )) {
            $this->clearAuthenticationState();

            return false;
        }

        // Regenerate session ID to prevent session fixation attacks.
        // delete_old_session=true ensures the old session file is removed.
        if (!$this->rotateSessionIdentifier()) {
            $this->clearAuthenticationState();
            LoggerUtils::getAuthLogger()->warning('Authentication failed because the session ID could not be rotated');

            return false;
        }

        $this->isPrimaryAuthenticationComplete = true;
        $this->bPendingTwoFactorAuth = false;
        CSRFUtils::regenerateToken();
        $this->issueSecurityActionAuthentication();

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

        return true;
    }

    public function authenticate(AuthenticationRequest $AuthenticationRequest): AuthenticationResult
    {
        if (!($AuthenticationRequest instanceof LocalUsernamePasswordRequest || $AuthenticationRequest instanceof LocalTwoFactorTokenRequest)) {
            throw new \Exception('Unable to process request as LocalUsernamePasswordRequest or LocalTwoFactorTokenRequest');
        }

        $authenticationResult = new AuthenticationResult();
        $logCtx = [
            'username' => $AuthenticationRequest instanceof LocalUsernamePasswordRequest
                ? $AuthenticationRequest->username
                : ($this->currentUser?->getUserName() ?? 'Unknown'),
        ];
        if ($AuthenticationRequest instanceof LocalUsernamePasswordRequest) {
            // Authentication providers are serialized into the PHP session, so a
            // failed primary-authentication attempt must not retain a User object.
            $this->clearAuthenticationState();
            LoggerUtils::getAuthLogger()->debug('Processing local login', $logCtx);
            // Get the information for the selected user
            $candidateUser = UserQuery::create()->findOneByUserName($AuthenticationRequest->username);
            $primaryAuthentication = null;
            if ($candidateUser === null) {
                // Set the error text
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Invalid login or password');
            } else {
                $primaryAuthentication = $candidateUser->authenticatePrimaryPassword($AuthenticationRequest->password);
            }

            if ($candidateUser instanceof User && ($candidateUser->getSignInBlockedReason() !== null || $primaryAuthentication['isLocked'])) {
                // Block the login if a maximum login failure count has been reached
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Too many failed logins: your account has been locked.  Please contact an administrator.');
                LoggerUtils::getAuthLogger()->warning('Authentication attempt for locked account', $logCtx);
                if ($primaryAuthentication['accountBecameLocked']) {
                    LoggerUtils::getAuthLogger()->warning('Too many failed logins. The account has been locked', $logCtx);
                    if (!empty($candidateUser->getEmail())) {
                        $lockedEmail = new LockedEmail($candidateUser);
                        $lockedEmail->send();
                    }
                }
            } elseif ($candidateUser instanceof User && !$primaryAuthentication['isPasswordValid']) {
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Invalid login or password');
                LoggerUtils::getAuthLogger()->warning('Invalid login attempt', $logCtx);
            } elseif ($candidateUser instanceof User) {
                // The password has been accepted. Persist the user only for a fully
                // authenticated session or for the explicit pending-2FA challenge.
                $this->currentUser = $candidateUser;
                $this->primaryAuthenticationPasswordHash = $primaryAuthentication['passwordHash'];
                $this->primaryAuthenticationTwoFactorSecret = $primaryAuthentication['twoFactorSecret'];
                $this->primaryAuthenticationRecoveryCodes = $primaryAuthentication['recoveryCodes'];
            }
            if ($this->currentUser instanceof User && $this->primaryAuthenticationTwoFactorSecret !== null && $this->currentUser->isTwoFactorAuthRateLimited()) {
                $this->clearAuthenticationState();
                $authenticationResult->message = gettext('Invalid login or password');
                LoggerUtils::getAuthLogger()->warning('Rejected login while two-factor rate limit is active', $logCtx);
            } elseif ($this->currentUser instanceof User && $this->primaryAuthenticationTwoFactorSecret !== null) {
                // User has enrolled in 2FA — redirect to verification step
                if ($this->preparePendingTwoFactorAuthentication()) {
                    $authenticationResult->isAuthenticated = false;
                    $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/session/two-factor';
                    LoggerUtils::getAuthLogger()->info('User partially authenticated, pending 2FA', $logCtx);
                } else {
                    $authenticationResult->message = gettext('Unable to establish a secure session. Please try again.');
                }
            } elseif ($this->currentUser instanceof User && (SystemConfig::getBooleanValue('bRequire2FA') || $this->currentUser->isAdmin())) {
                // Allow login but force enrollment — user will be redirected on every request until enrolled
                if ($this->prepareSuccessfulLoginOperations()) {
                    $authenticationResult->isAuthenticated = true;
                    LoggerUtils::getAuthLogger()->info('User logged in, redirecting to mandatory 2FA enrollment', $logCtx);
                }
            } elseif ($this->currentUser instanceof User) {
                if ($this->prepareSuccessfulLoginOperations()) {
                    $authenticationResult->isAuthenticated = true;
                    LoggerUtils::getAuthLogger()->info('User successfully logged in without 2FA', $logCtx);
                }
            }
        } elseif ($AuthenticationRequest instanceof LocalTwoFactorTokenRequest) {
            if (!$this->bPendingTwoFactorAuth || !$this->currentUser instanceof User || $this->hasPendingTwoFactorAuthenticationExpired()) {
                $this->clearAuthenticationState();
                $authenticationResult->nextStepURL = AuthenticationManager::getSessionBeginURL();
                LoggerUtils::getAuthLogger()->warning('Rejected two-factor code without an active first-factor session', $logCtx);

                return $authenticationResult;
            }

            // The account may have changed since the password was accepted. Do not
            // trust the serialized pending user when completing the second factor.
            try {
                $this->currentUser->reload();
            } catch (\Exception $exception) {
                $this->clearAuthenticationState();
                $authenticationResult->nextStepURL = AuthenticationManager::getSessionBeginURL();
                LoggerUtils::getAuthLogger()->warning('Rejected two-factor code for a deleted user', $logCtx);

                return $authenticationResult;
            }

            if ($this->currentUser->isLocked()
                || !$this->currentUser->is2FactorAuthEnabled()
                || $this->primaryAuthenticationPasswordHash === null
                || $this->primaryAuthenticationTwoFactorSecret === null) {
                $this->clearAuthenticationState();
                $authenticationResult->nextStepURL = AuthenticationManager::getSessionBeginURL();
                LoggerUtils::getAuthLogger()->warning('Rejected two-factor code after the account security state changed', $logCtx);

                return $authenticationResult;
            }

            $factorAuthenticationResult = $this->currentUser->authenticateTwoFactorCode(
                $AuthenticationRequest->TwoFACode,
                $this->primaryAuthenticationPasswordHash,
                $this->primaryAuthenticationTwoFactorSecret
            );

            if ($factorAuthenticationResult === User::TWO_FACTOR_AUTHENTICATION_RATE_LIMITED) {
                $this->clearAuthenticationState();
                $authenticationResult->nextStepURL = AuthenticationManager::getSessionBeginURL();
                LoggerUtils::getAuthLogger()->warning('Rejected two-factor attempt while account rate limit is active', $logCtx);
            } elseif ($factorAuthenticationResult === User::TWO_FACTOR_AUTHENTICATION_REVOKED) {
                $this->clearAuthenticationState();
                $authenticationResult->nextStepURL = AuthenticationManager::getSessionBeginURL();
                LoggerUtils::getAuthLogger()->warning('Rejected two-factor attempt after primary authentication was revoked', $logCtx);
            } elseif ($factorAuthenticationResult === User::TWO_FACTOR_AUTHENTICATION_TOTP) {
                if ($this->prepareSuccessfulLoginOperations()) {
                    $authenticationResult->isAuthenticated = true;
                    LoggerUtils::getAuthLogger()->info('User successfully logged in with 2FA', $logCtx);
                } else {
                    $authenticationResult->nextStepURL = AuthenticationManager::getSessionBeginURL();
                    LoggerUtils::getAuthLogger()->warning('Rejected two-factor completion after account lock', $logCtx);
                }
            } elseif ($factorAuthenticationResult === User::TWO_FACTOR_AUTHENTICATION_RECOVERY) {
                $this->primaryAuthenticationRecoveryCodes = $this->currentUser->getTwoFactorAuthRecoveryCodes();
                if ($this->prepareSuccessfulLoginOperations()) {
                    $authenticationResult->isAuthenticated = true;
                    LoggerUtils::getAuthLogger()->info('User successfully logged in with 2FA Recovery Code', $logCtx);
                } else {
                    $authenticationResult->nextStepURL = AuthenticationManager::getSessionBeginURL();
                    LoggerUtils::getAuthLogger()->warning('Rejected recovery-code completion after account lock', $logCtx);
            }
            } else {
                LoggerUtils::getAuthLogger()->info('Invalid 2FA code provided by partially authenticated user', $logCtx);
                $authenticationResult->isAuthenticated = false;
                if ($this->currentUser->isTwoFactorAuthRateLimited()) {
                    $this->clearAuthenticationState();
                    $authenticationResult->nextStepURL = AuthenticationManager::getSessionBeginURL();
                    LoggerUtils::getAuthLogger()->warning('Two-factor account rate limit reached; first-factor session cleared', $logCtx);
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

        if ($this->currentUser->isLocked() || $this->currentUser->getSignInBlockedReason() !== null) {
            LoggerUtils::getAuthLogger()->warning('Expiring session after account lock', $logCtx);
            AuthenticationManager::endSession(true);
            $authenticationResult->isAuthenticated = false;

            return $authenticationResult;
        }

        $currentPasswordHash = $this->currentUser->getPassword();
        $currentTwoFactorSecret = $this->currentUser->getTwoFactorAuthSecret();
        $currentRecoveryCodes = $this->currentUser->getTwoFactorAuthRecoveryCodes();
        $passwordStateMatches = $this->primaryAuthenticationPasswordHash !== null
            && hash_equals($currentPasswordHash, $this->primaryAuthenticationPasswordHash);
        $twoFactorStateMatches = $currentTwoFactorSecret === null
            ? $this->primaryAuthenticationTwoFactorSecret === null
            : $this->primaryAuthenticationTwoFactorSecret !== null
                && hash_equals($currentTwoFactorSecret, $this->primaryAuthenticationTwoFactorSecret);
        $recoveryStateMatches = $currentRecoveryCodes === null
            ? $this->primaryAuthenticationRecoveryCodes === null
            : $this->primaryAuthenticationRecoveryCodes !== null
                && hash_equals($currentRecoveryCodes, $this->primaryAuthenticationRecoveryCodes);
        if (!$passwordStateMatches || !$twoFactorStateMatches || !$recoveryStateMatches) {
            $this->clearAuthenticationState();
            LoggerUtils::getAuthLogger()->warning('Expired session after account security state changed', $logCtx);

            return $authenticationResult;
        }

        // A password-only 2FA session carries the candidate User so the
        // challenge can be completed, but it is not an authenticated session.
        if ($this->bPendingTwoFactorAuth === true) {
            if ($this->isPrimaryAuthenticationComplete) {
                $this->clearAuthenticationState();
                LoggerUtils::getAuthLogger()->warning('Rejected inconsistent two-factor session state', $logCtx);

                return $authenticationResult;
            }

            if ($this->hasPendingTwoFactorAuthenticationExpired()) {
                $this->clearAuthenticationState();
                LoggerUtils::getAuthLogger()->debug('Pending two-factor authentication expired', $logCtx);

                return $authenticationResult;
            }

            $authenticationResult->isAuthenticated = false;
            $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/session/two-factor';
            LoggerUtils::getAuthLogger()->debug('Session pending two-factor authentication', $logCtx);

            return $authenticationResult;
        }

        if (!$this->isPrimaryAuthenticationComplete) {
            $this->clearAuthenticationState();
            LoggerUtils::getAuthLogger()->warning('Rejected session without completed primary authentication', $logCtx);

            return $authenticationResult;
        }

        // Next, check for login timeout.  If login has expired, redirect to login page
        if (SystemConfig::getIntValue('iSessionTimeout') > 0) {
            if ((time() - $this->tLastOperationTimestamp) > SystemConfig::getIntValue('iSessionTimeout')) {
                LoggerUtils::getAuthLogger()->debug('User session timed out', $logCtx);
                $this->clearAuthenticationState();
                $authenticationResult->isAuthenticated = false;

                return $authenticationResult;
            } elseif ($updateLastOperationTimestamp) {
                $this->tLastOperationTimestamp = time();
            }
        }

        // Keep required steps explicit in every validation result. AuthMiddleware
        // owns the exact path/method allowlist needed to complete each step.
        if ($this->currentUser->getNeedPasswordChange()) {
            LoggerUtils::getAuthLogger()->info('User needs password change; redirecting to password change', $logCtx);
            $authenticationResult->nextStepURL = $this->getPasswordChangeURL();
        } elseif ((SystemConfig::getBooleanValue('bRequire2FA') || $this->currentUser->isAdmin()) && !$this->currentUser->is2FactorAuthEnabled()) {
            // A forced password change takes precedence. Once the account no
            // longer uses its temporary password, keep mandatory enrollment
            // active until confirmation persists the key.
            LoggerUtils::getAuthLogger()->info('User must enroll in mandatory 2FA before accessing system', $logCtx);
            $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/v2/user/current/manage2fa';
        }

        // Finally, if the above tests pass, this user "is authenticated"
        $authenticationResult->isAuthenticated = true;
        LoggerUtils::getAuthLogger()->debug('Session validated for user', $logCtx);

        return $authenticationResult;
    }
}
