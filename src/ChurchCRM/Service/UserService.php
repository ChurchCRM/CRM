<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use Propel\Runtime\Collection\ObjectCollection;

class UserService
{
    /**
     * Get all users
     * @return User[]|ObjectCollection
     */
    public function getAllUsers()
    {
        return UserQuery::create()->find();
    }

    /**
     * Get user dashboard statistics efficiently with minimal DB queries
     * @return array
     */
    public function getUserStats(): array
    {
        // Get total count and all failed login counts in one query
        $users = UserQuery::create()
            ->select(['failedLogins', 'twoFactorAuthSecret'])
            ->find();
        
        $maxFailedLogins = SystemConfig::getValue('iMaxFailedLogins');
        $totalUsers = $users->count();
        $activeUsers = 0;
        $lockedUsers = 0;
        $usersWithTwoFactor = 0;
        
        // Process results in memory to avoid multiple DB queries
        foreach ($users as $user) {
            if ($user['failedLogins'] >= $maxFailedLogins) {
                $lockedUsers++;
            } else {
                $activeUsers++;
            }
            
            if (!empty($user['twoFactorAuthSecret'])) {
                $usersWithTwoFactor++;
            }
        }

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'locked' => $lockedUsers,
            'twoFactor' => $usersWithTwoFactor
        ];
    }

    /**
     * Get user by ID
     * @param int $userId
     * @return User|null
     */
    public function getUserById(int $userId)
    {
        return UserQuery::create()->findOneById($userId);
    }

    /**
     * Check if a user is locked due to failed login attempts
     * @param User $user
     * @return bool
     */
    public function isUserLocked(User $user): bool
    {
        $maxFailedLogins = SystemConfig::getValue('iMaxFailedLogins');
        return $maxFailedLogins > 0 && $user->getFailedLogins() >= $maxFailedLogins;
    }

    /**
     * Get users with failed login attempts
     * @return User[]|ObjectCollection
     */
    public function getLockedUsers()
    {
        $maxFailedLogins = SystemConfig::getValue('iMaxFailedLogins');
        return UserQuery::create()
            ->filterByFailedLogins(['min' => $maxFailedLogins])
            ->find();
    }

    /**
     * Get users with two-factor authentication enabled
     * @return User[]|ObjectCollection
     */
    public function getUsersWithTwoFactor()
    {
        return UserQuery::create()
            ->where('User.TwoFactorAuthSecret IS NOT NULL')
            ->find();
    }

    /**
     * Get user settings configuration from SystemConfig
     * @return array Array of setting configurations
     */
    public function getUserSettingsConfig(): array
    {
        // Define user-related settings that should appear in the admin panel
        $userSettings = [
            'iSessionTimeout',
            'iMaxFailedLogins',
            'bEnableLostPassword',
            'bSendUserDeletedEmail',
            'iMinPasswordLength',
            'iMinPasswordChange',
            'aDisallowedPasswords',
            'bEnable2FA',
            'bRequire2FA',
            's2FAApplicationName'
        ];

        return SystemConfig::getSettingsConfig($userSettings);
    }
}