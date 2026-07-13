<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\users\NewAccountEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserConfig;
use ChurchCRM\model\ChurchCRM\UserConfigQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
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

        $maxFailedLogins = SystemConfig::getIntValue('iMaxFailedLogins');
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
     * Get user by ID (User PK = PersonId)
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
        $maxFailedLogins = SystemConfig::getIntValue('iMaxFailedLogins');
        return $maxFailedLogins > 0 && $user->getFailedLogins() >= $maxFailedLogins;
    }

    /**
     * Get users with failed login attempts
     * @return User[]|ObjectCollection
     */
    public function getLockedUsers()
    {
        $maxFailedLogins = SystemConfig::getIntValue('iMaxFailedLogins');
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
            'bRequire2FA',
            's2FAApplicationName'
        ];

        return SystemConfig::getSettingsConfig($userSettings);
    }

    /**
     * Get all people who do not yet have a user account, ordered by last name.
     * Replaces the legacy raw SQL:
     *   SELECT * FROM person_per LEFT JOIN user_usr ON per_ID = usr_per_ID
     *   WHERE usr_per_ID IS NULL ORDER BY per_LastName
     *
     * @return Person[]|ObjectCollection
     */
    public function getAssignablePeople()
    {
        // Fetch only the PersonId column to avoid hydrating full User model objects.
        // The User table's primary key phpName is 'PersonId' (not 'Id').
        $existingUserPersonIds = [];
        foreach (UserQuery::create()->select(['PersonId'])->find() as $row) {
            // Propel select() returns associative arrays when columns are named,
            // e.g. ['PersonId' => 1]. Casting directly to int always yields 1
            // for any non-empty array; extract the scalar value explicitly.
            $existingUserPersonIds[] = is_array($row) ? (int) ($row['PersonId'] ?? 0) : (int) $row;
        }

        // Fetch persons NOT in that set
        return PersonQuery::create()
            ->filterById($existingUserPersonIds ?: [0], Criteria::NOT_IN)
            ->orderByLastName()
            ->find();
    }

    /**
     * Derive the canonical permission flags from a POST body.
     *
     * The three access modes are mutually exclusive:
     *   - admin:  Admin=1, EditSelf=0, all module perms as submitted (ignored by server for admin)
     *   - self:   Admin=0, EditSelf=1, all module perms cleared
     *   - custom: Admin=0, EditSelf=0, module perms as submitted
     *
     * An additional EditSelf-exclusivity rule (defense-in-depth, #9079) clears all
     * module permissions when EditSelf=1 && Admin=0, regardless of what the form sent.
     *
     * @param array $body POST body
     * @return array Normalized permission flags keyed by camelCase name
     */
    public function normalizeAccessMode(array $body): array
    {
        $addRecords   = isset($body['AddRecords'])   ? 1 : 0;
        $editRecords  = isset($body['EditRecords'])  ? 1 : 0;
        $deleteRecords = isset($body['DeleteRecords']) ? 1 : 0;
        $menuOptions  = isset($body['MenuOptions'])  ? 1 : 0;
        $manageGroups = isset($body['ManageGroups']) ? 1 : 0;
        $finance      = isset($body['Finance'])      ? 1 : 0;
        $notes        = isset($body['Notes'])        ? 1 : 0;
        $admin        = isset($body['Admin'])        ? 1 : 0;
        $editSelf     = isset($body['EditSelf'])     ? 1 : 0;

        $accessMode = $body['accessMode'] ?? 'custom';

        if ($accessMode === 'admin') {
            $admin    = 1;
            $editSelf = 0;
        } elseif ($accessMode === 'self') {
            $admin        = 0;
            $editSelf     = 1;
            $addRecords   = 0;
            $editRecords  = 0;
            $deleteRecords = 0;
            $menuOptions  = 0;
            $manageGroups = 0;
            $finance      = 0;
            $notes        = 0;
        } else {
            // 'custom' or any unrecognised value: use submitted module perms as-is
            $admin    = 0;
            $editSelf = 0;
        }

        // Defense-in-depth: EditSelf is exclusive; a non-admin EditSelf user has no
        // other permissions regardless of what the UI submitted (#9079).
        if ($editSelf === 1 && $admin === 0) {
            $addRecords   = 0;
            $editRecords  = 0;
            $deleteRecords = 0;
            $menuOptions  = 0;
            $manageGroups = 0;
            $finance      = 0;
            $notes        = 0;
        }

        return compact(
            'admin',
            'editSelf',
            'addRecords',
            'editRecords',
            'deleteRecords',
            'menuOptions',
            'manageGroups',
            'finance',
            'notes'
        );
    }

    /**
     * Create a new user account for the given person.
     *
     * Validates username length (>= 3 chars) and uniqueness, then creates the
     * account with a random password. Sends a NewAccountEmail when email is
     * configured.
     *
     * @param int    $personId Person this account belongs to
     * @param array  $perms    Normalized perms from normalizeAccessMode()
     * @param string $userName Desired login name
     * @return User The newly created user
     * @throws \RuntimeException on validation failure (duplicate username, too short)
     */
    public function createUser(int $personId, array $perms, string $userName): User
    {
        if ($personId <= 0) {
            throw new \RuntimeException(gettext('A valid person must be selected.'));
        }

        if (PersonQuery::create()->findPk($personId) === null) {
            throw new \RuntimeException(gettext('The selected person was not found.'));
        }

        if (UserQuery::create()->findPk($personId) !== null) {
            throw new \RuntimeException(gettext('This person already has a user account.'));
        }

        if (strlen($userName) < 3) {
            throw new \RuntimeException(gettext('Login must be at least 3 characters!'));
        }

        $dupCount = UserQuery::create()
            ->filterByUserName($userName)
            ->filterByPersonId($personId, Criteria::NOT_EQUAL)
            ->count();

        if ($dupCount > 0) {
            throw new \RuntimeException(gettext('Login already in use, please select a different login!'));
        }

        $rawPassword = User::randomPassword();
        $defaultFY   = FiscalYearUtils::getCurrentFiscalYearId();

        $con = \Propel\Runtime\Propel::getWriteConnection('default');
        $con->beginTransaction();
        try {
            $newUser = new User();
            $newUser->setPersonId($personId)
                ->setNeedPasswordChange(true)
                ->setLastLogin(date('Y-m-d H:i:s'))
                ->setAddRecords($perms['addRecords'])
                ->setEditRecords($perms['editRecords'])
                ->setDeleteRecords($perms['deleteRecords'])
                ->setMenuOptions($perms['menuOptions'])
                ->setManageGroups($perms['manageGroups'])
                ->setFinance($perms['finance'])
                ->setNotes($perms['notes'])
                ->setAdmin($perms['admin'])
                ->setDefaultFY($defaultFY)
                ->setUserName($userName)
                ->setEditSelf($perms['editSelf']);
            $newUser->updatePassword($rawPassword);
            $newUser->save();
            $newUser->createTimeLineNote('created');
            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();
            throw $e;
        }

        if (SystemConfig::isEmailEnabled()) {
            $email = new NewAccountEmail($newUser, $rawPassword);
            if (!$email->send()) {
                LoggerUtils::getAppLogger()->warning(
                    'New account email failed for user ' . $userName . ': ' . $email->getError()
                );
            }
        }

        return $newUser;
    }

    /**
     * Atomically update a user account and persist their per-user config.
     *
     * Both operations are wrapped in a single DB transaction: if saving the
     * config fails after the account has been updated, the account update is
     * rolled back so the DB is never left in a partial state.
     *
     * @param int    $personId      Person whose user account to update
     * @param array  $perms         Normalized perms from normalizeAccessMode()
     * @param string $userName      New login name
     * @param array  $newValue      Keyed by ucfg_id — new setting value
     * @param array  $newPermission Keyed by ucfg_id — 'TRUE' or 'FALSE'
     * @param array  $types         Keyed by ucfg_id — field type string
     * @throws \RuntimeException on validation failure
     */
    public function updateUserWithConfig(
        int    $personId,
        array  $perms,
        string $userName,
        array  $newValue,
        array  $newPermission,
        array  $types
    ): void {
        $con = \Propel\Runtime\Propel::getWriteConnection('default');
        $con->beginTransaction();
        try {
            $this->updateUser($personId, $perms, $userName);
            if ($types !== []) {
                $this->saveUserConfig($personId, $newValue, $newPermission, $types);
            }
            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing user account.
     *
     * Validates username length and uniqueness (excluding the user being updated),
     * then persists permission changes.
     *
     * @param int    $personId Person whose user account to update
     * @param array  $perms    Normalized perms from normalizeAccessMode()
     * @param string $userName New login name
     * @return User The updated user
     * @throws \RuntimeException on validation failure
     */
    public function updateUser(int $personId, array $perms, string $userName): User
    {
        if (strlen($userName) < 3) {
            throw new \RuntimeException(gettext('Login must be at least 3 characters!'));
        }

        $dupCount = UserQuery::create()
            ->filterByUserName($userName)
            ->filterByPersonId($personId, Criteria::NOT_EQUAL)
            ->count();

        if ($dupCount > 0) {
            throw new \RuntimeException(gettext('Login already in use, please select a different login!'));
        }

        $user = UserQuery::create()->findPk($personId);
        if ($user === null) {
            throw new \RuntimeException(gettext('User not found.'));
        }

        $user->setAddRecords($perms['addRecords'])
            ->setEditRecords($perms['editRecords'])
            ->setDeleteRecords($perms['deleteRecords'])
            ->setMenuOptions($perms['menuOptions'])
            ->setManageGroups($perms['manageGroups'])
            ->setFinance($perms['finance'])
            ->setNotes($perms['notes'])
            ->setAdmin($perms['admin'])
            ->setUserName($userName)
            ->setEditSelf($perms['editSelf']);
        $user->save();
        $user->reload();
        $user->createTimeLineNote('updated');

        return $user;
    }

    /**
     * Get merged default + per-user config rows for the editor view.
     *
     * Returns each default config row (per_id=0) with the user's override values
     * applied where they exist. Replaces the N+1 raw SQL pattern in the legacy editor.
     *
     * Each row in the returned array has keys:
     *   id, name, value, type, tooltip, permission
     *
     * @param int $personId Person ID of the user whose config to fetch
     * @return array
     */
    public function getUserConfigRows(int $personId): array
    {
        // Load all default config rows in one query
        $defaults = UserConfigQuery::create()
            ->filterByPeronId(0)
            ->orderById()
            ->find();

        // Index the user's overrides by config id in one query
        $userOverrides = [];
        foreach (UserConfigQuery::create()->filterByPeronId($personId)->find() as $uc) {
            $userOverrides[$uc->getId()] = $uc;
        }

        $rows = [];
        foreach ($defaults as $default) {
            $id = $default->getId();
            if (isset($userOverrides[$id])) {
                $override = $userOverrides[$id];
                $rows[] = [
                    'id'         => $id,
                    'name'       => $default->getName(),
                    'value'      => $override->getValue(),
                    'type'       => $default->getType(),
                    'tooltip'    => $default->getTooltip(),
                    'permission' => $override->getPermission(),
                ];
            } else {
                $rows[] = [
                    'id'         => $id,
                    'name'       => $default->getName(),
                    'value'      => $default->getValue(),
                    'type'       => $default->getType(),
                    'tooltip'    => $default->getTooltip(),
                    'permission' => $default->getPermission(),
                ];
            }
        }

        return $rows;
    }

    /**
     * Persist per-user configuration settings.
     *
     * Mirrors the logic of the legacy save loop (from the now-deleted `src/UserEditor.php`) but uses ORM exclusively
     * and throws on error instead of echo/exit.
     *
     * Access is already restricted to admin-only callers via `AdminRoleAuthMiddleware`;
     * no per-row permission gate is applied here.
     *
     * @param int   $personId     User's person ID
     * @param array $newValue     Keyed by ucfg_id — new setting value
     * @param array $newPermission Keyed by ucfg_id — 'TRUE' or 'FALSE'
     * @param array $types        Keyed by ucfg_id — 'text','textarea','number','date','boolean'
     * @throws \RuntimeException if a default config row cannot be found
     */
    public function saveUserConfig(int $personId, array $newValue, array $newPermission, array $types): void
    {
        ksort($types);

        foreach ($types as $idRaw => $currentType) {
            $id = (int) $idRaw;

            // Filter value by type (mirrors legacy input filtering)
            $rawVal = $newValue[$id] ?? '';
            $value  = match ($currentType) {
                'text', 'textarea' => InputUtils::legacyFilterInput($rawVal),
                'number'           => InputUtils::legacyFilterInput($rawVal, 'float'),
                'date'             => InputUtils::legacyFilterInput($rawVal, 'date'),
                'boolean'          => ($rawVal != '1') ? '' : '1',
                default            => InputUtils::legacyFilterInput($rawVal),
            };

            $permission = ($newPermission[$id] ?? '') === 'TRUE' ? 'TRUE' : 'FALSE';

            // Try to find the user's existing row
            $userConfig = UserConfigQuery::create()
                ->filterById($id)
                ->filterByPeronId($personId)
                ->findOne();

            if ($userConfig === null) {
                // First time: clone from default row
                $defaultConfig = UserConfigQuery::create()
                    ->filterById($id)
                    ->filterByPeronId(0)
                    ->findOne();

                if ($defaultConfig === null) {
                    throw new \RuntimeException(
                        sprintf('Default user config row not found for id %d', $id)
                    );
                }

                $userConfig = new UserConfig();
                $userConfig->setPeronId($personId)
                    ->setId($id)
                    ->setName($defaultConfig->getName())
                    ->setValue($value)
                    ->setType($defaultConfig->getType())
                    ->setTooltip($defaultConfig->getTooltip())
                    ->setPermission($permission)
                    ->setCat($defaultConfig->getCat());
                $userConfig->save();
            } else {
                $userConfig->setValue($value);
                $userConfig->setPermission($permission);
                $userConfig->save();
            }
        }
    }
}
