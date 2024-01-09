<?php

namespace ChurchCRM\Authentication\AuthenticationProviders;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationResult;
use ChurchCRM\Authentication\Requests\AuthenticationRequest;
use ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest;
use ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\LockedEmail;
use ChurchCRM\KeyManager;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\LoggerUtils;
use DateTimeZone;
use Endroid\QrCode\QrCode;
use PragmaRX\Google2FA\Google2FA;

class LocalAuthentication implements IAuthenticationProvider
{
    private ?User $currentUser = null;
    private ?bool $bPendingTwoFactorAuth = null;
    private ?int $tLastOperationTimestamp = null;

    public function getPasswordChangeURL(): string
    {
        // this shouldn't really be called, but it's necessary to implement the IAuthenticationProvider interface
        return SystemURLs::getRootPath() . '/v2/user/current/changepassword';
    }

    public static function getIsTwoFactorAuthSupported(): bool
    {
        return SystemConfig::getBooleanValue('bEnable2FA') && KeyManager::getAreAllSecretsDefined();
    }

    public static function getTwoFactorQRCode($username, $secret): QrCode
    {
        $google2fa = new Google2FA();
        $g2faUrl = $google2fa->getQRCodeUrl(
            SystemConfig::getValue('s2FAApplicationName'),
            $username,
            $secret
        );
        $qrCode = new QrCode($g2faUrl);
        $qrCode->setSize(300);

        return $qrCode;
    }

    public function getCurrentUser(): ?User
    {
        return $this->currentUser;
    }

    public function endSession(): void
    {
        if ($this->currentUser instanceof User) {
            //$this->currentUser->setDefaultFY($_SESSION['idefaultFY']);
            $this->currentUser->setCurrentDeposit($_SESSION['iCurrentDeposit']);
            $this->currentUser->save();
            $this->currentUser = null;
        }
    }

    private function prepareSuccessfulLoginOperations(): void
    {
        // Set the LastLogin and Increment the LoginCount
        $date = new \DateTimeImmutable('now', new DateTimeZone(SystemConfig::getValue('sTimeZone')));
        $this->currentUser->setLastLogin($date->format('Y-m-d H:i:s'));
        $this->currentUser->setLoginCount($this->currentUser->getLoginCount() + 1);
        $this->currentUser->setFailedLogins(0);
        $this->currentUser->save();

        $_SESSION['bManageGroups'] = $this->currentUser->isManageGroupsEnabled();
        $_SESSION['bFinance'] = $this->currentUser->isFinanceEnabled();

        // Create the Cart
        $_SESSION['aPeopleCart'] = [];

        // Create the variable for the Global Message
        $_SESSION['sGlobalMessage'] = '';

        // Initialize the last operation time
        $this->tLastOperationTimestamp = time();

        $_SESSION['bHasMagicQuotes'] = 0;

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
            } elseif (SystemConfig::getBooleanValue('bEnable2FA') && $this->currentUser->is2FactorAuthEnabled()) {
                // Only redirect the user to the 2FA sign-ing page if it's
                // enabled both at system AND user level.
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/session/two-factor';
                $this->bPendingTwoFactorAuth = true;
                LoggerUtils::getAuthLogger()->info('User partially authenticated, pending 2FA', $logCtx);
            } elseif (SystemConfig::getBooleanValue('bRequire2FA') && !$this->currentUser->is2FactorAuthEnabled()) {
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->message = gettext('Invalid login or password');
                LoggerUtils::getAuthLogger()->warning('User attempted login with valid credentials, but missing mandatory 2FA enrollment.  Denying access for user', $logCtx);
            } else {
                $this->prepareSuccessfulLoginOperations();
                $authenticationResult->isAuthenticated = true;
                LoggerUtils::getAuthLogger()->debug('User successfully logged in without 2FA', $logCtx);
            }
        } elseif ($AuthenticationRequest instanceof LocalTwoFactorTokenRequest && $this->bPendingTwoFactorAuth) {
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
                LoggerUtils::getAuthLogger()->info('Invalid 2FA code provided by partially authenticated user', $logCtx);
                $authenticationResult->isAuthenticated = false;
                $authenticationResult->nextStepURL = SystemURLs::getRootPath() . '/session/two-factor';
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

        // Next, make sure the user in the sesion still exists in the database.
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
        if (SystemConfig::getValue('iSessionTimeout') > 0) {
            if ((time() - $this->tLastOperationTimestamp) > SystemConfig::getValue('iSessionTimeout')) {
                LoggerUtils::getAuthLogger()->debug('User session timed out', $logCtx);
                $authenticationResult->isAuthenticated = false;

                return $authenticationResult;
            } elseif ($updateLastOperationTimestamp) {
                $this->tLastOperationTimestamp = time();
            }
        }

        // Next, if this user needs to change password, send to that page
        // but don't redirect them if they're already on the password change page
        $IsUserOnPasswordChangePageNow = $_SERVER['REQUEST_URI'] === $this->getPasswordChangeURL();
        if ($this->currentUser->getNeedPasswordChange() && !$IsUserOnPasswordChangePageNow) {
            LoggerUtils::getAuthLogger()->info('User needs password change; redirecting to password change', $logCtx);
            $authenticationResult->isAuthenticated = false;
            $authenticationResult->nextStepURL = $this->getPasswordChangeURL();
        }

        // Finally, if the above tests pass, this user "is authenticated"
        $authenticationResult->isAuthenticated = true;
        LoggerUtils::getAuthLogger()->debug('Session validated for user', $logCtx);

        return $authenticationResult;
    }
}
