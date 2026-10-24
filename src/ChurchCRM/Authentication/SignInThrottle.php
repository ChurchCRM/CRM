<?php

namespace ChurchCRM\Authentication;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\users\LockedEmail;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Service\RateLimiter;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Throttling for staff sign-in (#10197): the password step, the 2FA step, the
 * public login API and password reset requests.
 *
 * - An account locks after iMaxFailedLogins consecutive failures and unlocks
 *   by itself after iLoginLockoutMinutes. Attempts on a locked account are
 *   refused unchecked and do not extend the lock.
 * - An IP address is refused after iMaxFailedLoginsPerIP failures and reset
 *   requests within iLoginLockoutMinutes, whatever iMaxFailedLogins says.
 * - An account gets at most three reset emails an hour.
 *
 * Every refusal answers exactly like a wrong password.
 */
final class SignInThrottle
{
    public const BUCKET_IP = 'signin.ip';
    public const BUCKET_LOCKOUT = 'signin.lockout';
    public const BUCKET_PASSWORD_RESET = 'password-reset.account';

    private const PASSWORD_RESETS_PER_HOUR = 3;
    private const MAX_FAILED_LOGINS_STORED = 255;

    // Checked instead of a real hash so a refused sign-in takes as long as a wrong password.
    private const TIMING_HASH = '$2y$12$dWkDuZyb9136YiL7gTJtFudIhwFpvr7flI3yJoY3.mrG.HQrEcfiu';

    public static function isIpThrottled(): bool
    {
        return self::ipLimiter()->tooManyAttempts(RateLimiter::clientIpKey());
    }

    /**
     * A failed sign-in. With a user it counts toward the account lock, which
     * starts, with a notice to the user, when the count reaches the maximum.
     * The client address is always counted.
     */
    public static function recordFailure(?User $user): void
    {
        self::recordIpAttempt();
        if ($user === null) {
            return;
        }

        $max = SystemConfig::getIntValue('iMaxFailedLogins');
        if ($max > 0 && $user->getFailedLogins() >= $max && !self::isAccountLocked($user)) {
            $user->setFailedLogins(0);
        }
        $user->setFailedLogins(min(self::MAX_FAILED_LOGINS_STORED, $user->getFailedLogins() + 1));
        $user->save();

        if ($max > 0 && $user->getFailedLogins() >= $max) {
            $lockout = self::lockoutLimiter();
            $lockout->clear((string) $user->getId());
            $lockout->hit((string) $user->getId());
            LoggerUtils::getAuthLogger()->warning('Too many failed logins. The account has been locked', ['username' => $user->getUserName()]);
            if (!empty($user->getEmail())) {
                (new LockedEmail($user))->send();
            }
        }
    }

    /**
     * Counts a request against the client address without a failure, such as a
     * password reset request.
     */
    public static function recordIpAttempt(): void
    {
        self::ipLimiter()->hit(RateLimiter::clientIpKey());
    }

    public static function isAccountLocked(User $user): bool
    {
        $max = SystemConfig::getIntValue('iMaxFailedLogins');

        return $max > 0
            && $user->getFailedLogins() >= $max
            && self::lockoutLimiter()->tooManyAttempts((string) $user->getId());
    }

    public static function minutesUntilUnlock(User $user): int
    {
        if (!self::isAccountLocked($user)) {
            return 0;
        }

        return (int) ceil(self::lockoutLimiter()->availableIn((string) $user->getId()) / 60);
    }

    public static function unlockAccount(User $user): void
    {
        $user->setFailedLogins(0);
        $user->save();
        self::lockoutLimiter()->clear((string) $user->getId());
    }

    public static function allowPasswordResetEmail(User $user): bool
    {
        return (new RateLimiter(self::BUCKET_PASSWORD_RESET, self::PASSWORD_RESETS_PER_HOUR, 3600))
            ->attempt((string) $user->getId());
    }

    public static function spendPasswordCheckTime(string $password): void
    {
        password_verify($password, self::TIMING_HASH);
    }

    public static function lockoutSeconds(): int
    {
        return max(1, SystemConfig::getIntValue('iLoginLockoutMinutes')) * 60;
    }

    private static function ipLimiter(): RateLimiter
    {
        return new RateLimiter(self::BUCKET_IP, SystemConfig::getIntValue('iMaxFailedLoginsPerIP'), self::lockoutSeconds());
    }

    private static function lockoutLimiter(): RateLimiter
    {
        return new RateLimiter(self::BUCKET_LOCKOUT, 1, self::lockoutSeconds());
    }
}
