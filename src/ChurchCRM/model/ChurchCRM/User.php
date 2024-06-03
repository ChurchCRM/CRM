<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\KeyManager;
use ChurchCRM\model\ChurchCRM\Base\User as BaseUser;
use ChurchCRM\Utils\MiscUtils;
use Defuse\Crypto\Crypto;
use PragmaRX\Google2FA\Google2FA;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'user_usr' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class User extends BaseUser
{
    private $provisional2FAKey;

    public function getId()
    {
        return $this->getPersonId();
    }

    public function getName(): string
    {
        return $this->getPerson()->getFullName();
    }

    public function getEmail(): ?string
    {
        return $this->getPerson()->getEmail();
    }

    public function getFullName(): string
    {
        return $this->getPerson()->getFullName();
    }

    public function isAddRecordsEnabled(): bool
    {
        return $this->isAdmin() || $this->isAddRecords();
    }

    public function isEditRecordsEnabled(): bool
    {
        return $this->isAdmin() || $this->isEditRecords();
    }

    public function isDeleteRecordsEnabled(): bool
    {
        return $this->isAdmin() || $this->isDeleteRecords();
    }

    public function isMenuOptionsEnabled(): bool
    {
        return $this->isAdmin() || $this->isMenuOptions();
    }

    public function isManageGroupsEnabled(): bool
    {
        return $this->isAdmin() || $this->isManageGroups();
    }

    public function isFinanceEnabled(): bool
    {
        if (SystemConfig::getBooleanValue('bEnabledFinance')) {
            return $this->isAdmin() || $this->isFinance();
        }

        return false;
    }

    public function isNotesEnabled(): bool
    {
        return $this->isAdmin() || $this->isNotes();
    }

    public function isEditSelfEnabled(): bool
    {
        return $this->isAdmin() || $this->isEditSelf();
    }

    public function isCanvasserEnabled(): bool
    {
        return $this->isAdmin() || $this->isCanvasser();
    }

    public function updatePassword(string $password): void
    {
        $this->setPassword($this->hashPassword($password));
    }

    public function isPasswordValid(string $password): bool
    {
        return $this->getPassword() == $this->hashPassword($password);
    }

    public function hashPassword(string $password): string
    {
        return hash('sha256', $password . $this->getPersonId());
    }

    public function isAddEventEnabled(): bool // TODO: Create permission to manag event deletion see https://github.com/ChurchCRM/CRM/issues/4726
    {
        return $this->isAddEvent();
    }

    public function isAddEvent(): bool
    {
        return $this->isAdmin() || $this->isEnabledSecurity('bAddEvent');
    }

    public function isCSVExportEnabled(): bool
    {
        return $this->isCSVExport();
    }

    public function isCSVExport(): bool
    {
        return $this->isAdmin() || $this->isEnabledSecurity('bExportCSV');
    }

    public function isEmailEnabled(): bool
    {
        return $this->isAdmin() || $this->isEnabledSecurity('bEmailMailto');
    }

    public function isCreateDirectoryEnabled(): bool
    {
        return $this->isAdmin() || $this->isEnabledSecurity('bCreateDirectory');
    }

    public function isLocked(): bool
    {
        return SystemConfig::getValue('iMaxFailedLogins') > 0 && $this->getFailedLogins() >= SystemConfig::getValue('iMaxFailedLogins');
    }

    public function resetPasswordToRandom(): string
    {
        $password = User::randomPassword();
        $this->updatePassword($password);
        $this->setNeedPasswordChange(true);
        $this->setFailedLogins(0);

        return $password;
    }

    public static function randomPassword(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = []; //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < SystemConfig::getValue('iMinPasswordLength'); $i++) {
            $n = random_int(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }

        return implode($pass); //turn the array into a string
    }

    public static function randomApiKey(): string
    {
        return MiscUtils::randomToken();
    }

    public function postInsert(ConnectionInterface $con = null): void
    {
        $this->createTimeLineNote('created');
    }

    public function postDelete(ConnectionInterface $con = null): void
    {
        $this->createTimeLineNote('deleted');
    }

    public function createTimeLineNote($type): void
    {
        $note = new Note();
        $note->setPerId($this->getPersonId());
        $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
        $note->setType('user');

        switch ($type) {
            case 'created':
                $note->setText(gettext('system user created'));
                break;
            case 'updated':
                $note->setText(gettext('system user updated'));
                break;
            case 'deleted':
                $note->setText(gettext('system user deleted'));
                break;
            case 'password-reset':
                $note->setText(gettext('system user password reset'));
                break;
            case 'password-changed':
                $note->setText(gettext('system user changed password'));
                break;
            case 'password-changed-admin':
                $note->setText(gettext('system user password changed by admin'));
                break;
            case 'login-reset':
                $note->setText(gettext('system user login reset'));
                break;
        }

        $note->save();
    }

    public function isEnabledSecurity($securityConfigName): bool
    {
        if ($this->isAdmin()) {
            return true;
        } elseif ($securityConfigName == 'bAdmin') {
            return false;
        }

        if ($securityConfigName == 'bAll') {
            return true;
        }

        if ($securityConfigName == 'bAddRecords' && $this->isAddRecordsEnabled()) {
            return true;
        }

        if ($securityConfigName == 'bEditRecords' && $this->isEditRecordsEnabled()) {
            return true;
        }

        if ($securityConfigName == 'bDeleteRecords' && $this->isDeleteRecordsEnabled()) {
            return true;
        }

        if ($securityConfigName == 'bManageGroups' && $this->isManageGroupsEnabled()) {
            return true;
        }

        if ($securityConfigName == 'bFinance' && $this->isFinanceEnabled()) {
            return true;
        }

        if ($securityConfigName == 'bNotes' && $this->isNotesEnabled()) {
            return true;
        }

        if ($securityConfigName == 'bCanvasser' && $this->isCanvasserEnabled()) {
            return true;
        }

        foreach ($this->getUserConfigs() as $userConfig) {
            if ($userConfig->getName() == $securityConfigName) {
                return $userConfig->getPermission() == 'TRUE';
            }
        }

        return false;
    }

    public function getUserConfigString($userConfigName)
    {
        foreach ($this->getUserConfigs() as $userConfig) {
            if ($userConfig->getName() == $userConfigName) {
                return $userConfig->getValue();
            }
        }
    }

    public function setUserConfigString($userConfigName, $value)
    {
        foreach ($this->getUserConfigs() as $userConfig) {
            if ($userConfig->getName() == $userConfigName) {
                return $userConfig->setValue($value);
            }
        }
    }

    public function setSetting($name, $value): void
    {
        $setting = $this->getSetting($name);
        if (!$setting) {
            $setting = new UserSetting();
            $setting->set($this, $name, $value);
        } else {
            $setting->setValue($value);
        }
        $setting->save();
    }

    public function getSettingValue($name)
    {
        $userSetting = $this->getSetting($name);

        return $userSetting === null ? '' : $userSetting->getValue();
    }

    public function getSetting($name)
    {
        foreach ($this->getUserSettings() as $userSetting) {
            if ($userSetting->getName() == $name) {
                return $userSetting;
            }
        }

        return null;
    }

    public function getStyle(): string
    {
        $skin = $this->getSetting(UserSetting::UI_STYLE) ?? 'skin-red';
        $cssClasses = [];
        $cssClasses[] = $skin;
        $cssClasses[] = $this->getSetting(UserSetting::UI_BOXED);
        $cssClasses[] = $this->getSetting(UserSetting::UI_SIDEBAR);

        return implode(' ', $cssClasses);
    }

    public function isShowPledges(): bool
    {
        return $this->getSettingValue(UserSetting::FINANCE_SHOW_PLEDGES) == 'true';
    }

    public function isShowPayments(): bool
    {
        return $this->getSettingValue(UserSetting::FINANCE_SHOW_PAYMENTS) == 'true';
    }

    public function getShowSince()
    {
        return $this->getSettingValue(UserSetting::FINANCE_SHOW_SINCE);
    }

    public function provisionNew2FAKey()
    {
        $google2fa = new Google2FA();
        $key = $google2fa->generateSecretKey();
        // store the temporary 2FA key in a private variable on this User object
        // we don't want to update the database with the new key until we've confirmed
        // that the user is capable of generating valid 2FA codes
        // encrypt the 2FA key since this object and its properties are serialized into the $_SESSION store
        // which is generally written to disk.
        $this->provisional2FAKey = Crypto::encryptWithPassword($key, KeyManager::getTwoFASecretKey());

        return $key;
    }

    public function confirmProvisional2FACode($twoFACode)
    {
        $google2fa = new Google2FA();
        $window = 2; //TODO: make this a system config
        $pw = Crypto::decryptWithPassword($this->provisional2FAKey, KeyManager::getTwoFASecretKey());
        $isKeyValid = $google2fa->verifyKey($pw, $twoFACode, $window);
        if ($isKeyValid) {
            $this->setTwoFactorAuthSecret($this->provisional2FAKey);
            $this->save();

            return true;
        }

        return $isKeyValid;
    }

    public function remove2FAKey(): void
    {
        $this->setTwoFactorAuthSecret(null);
        $this->save();
    }

    public function getDecryptedTwoFactorAuthSecret()
    {
        return Crypto::decryptWithPassword($this->getTwoFactorAuthSecret(), KeyManager::getTwoFASecretKey());
    }

    private function getDecryptedTwoFactorAuthRecoveryCodes(): array
    {
        return explode(',', Crypto::decryptWithPassword($this->getTwoFactorAuthRecoveryCodes(), KeyManager::getTwoFASecretKey()));
    }

    public function disableTwoFactorAuthentication(): void
    {
        $this->setTwoFactorAuthRecoveryCodes(null);
        $this->setTwoFactorAuthSecret(null);
        $this->save();
    }

    public function is2FactorAuthEnabled(): bool
    {
        return !empty($this->getTwoFactorAuthSecret());
    }

    public function getNewTwoFARecoveryCodes(): array
    {
        // generate an array of 2FA recovery codes, and store as an encrypted, comma-separated list
        $recoveryCodes = [];
        for ($i = 0; $i < 12; $i++) {
            $recoveryCodes[$i] = base64_encode(random_bytes(10));
        }
        $recoveryCodesString = implode(',', $recoveryCodes);
        $this->setTwoFactorAuthRecoveryCodes(Crypto::encryptWithPassword($recoveryCodesString, KeyManager::getTwoFASecretKey()));
        $this->save();

        return $recoveryCodes;
    }

    public function isTwoFACodeValid($twoFACode): bool
    {
        $google2fa = new Google2FA();
        $window = 2; //TODO: make this a system config
        $timestamp = $google2fa->verifyKeyNewer($this->getDecryptedTwoFactorAuthSecret(), $twoFACode, $this->getTwoFactorAuthLastKeyTimestamp(), $window);
        if ($timestamp !== false) {
            $this->setTwoFactorAuthLastKeyTimestamp($timestamp);
            $this->save();

            return true;
        } else {
            return false;
        }
    }

    public function isTwoFaRecoveryCodeValid($twoFaRecoveryCode): bool
    {
        // checks for validity of a 2FA recovery code
        // if the specified code was valid, the code is also removed.
        $codes = $this->getDecryptedTwoFactorAuthRecoveryCodes();
        if (($key = array_search($twoFaRecoveryCode, $codes)) !== false) {
            unset($codes[$key]);
            $recoveryCodesString = implode(',', $codes);
            $this->setTwoFactorAuthRecoveryCodes(Crypto::encryptWithPassword($recoveryCodesString, KeyManager::getTwoFASecretKey()));

            return true;
        }

        return false;
    }

    public function adminSetUserPassword(string $newPassword): void
    {
        $this->updatePassword($newPassword);
        $this->setNeedPasswordChange(false);
        $this->save();
        $this->createTimeLineNote('password-changed-admin');
    }

    public function userChangePassword($oldPassword, $newPassword): void
    {
        if (!$this->isPasswordValid($oldPassword)) {
            throw new PasswordChangeException('Old', gettext('Incorrect password supplied for current user'));
        }

        if (!$this->getIsPasswordPermissible($newPassword)) {
            throw new PasswordChangeException('New', gettext('Your password choice is too obvious. Please choose something else.'));
        }

        if (strlen($newPassword) < SystemConfig::getValue('iMinPasswordLength')) {
            throw new PasswordChangeException('New', gettext('Your new password must be at least') . ' ' . SystemConfig::getValue('iMinPasswordLength') . ' ' . gettext('characters'));
        }

        if ($newPassword == $oldPassword) {
            throw new PasswordChangeException('New', gettext('Your new password must not match your old one.'));
        }

        if (levenshtein(strtolower($newPassword), strtolower($oldPassword)) < SystemConfig::getValue('iMinPasswordChange')) {
            throw new PasswordChangeException('New', gettext('Your new password is too similar to your old one.'));
        }

        $this->updatePassword($newPassword);
        $this->setNeedPasswordChange(false);
        $this->save();
        $this->createTimeLineNote('password-changed');
    }

    private function getIsPasswordPermissible($newPassword): bool
    {
        $aBadPasswords = explode(',', strtolower(SystemConfig::getValue('aDisallowedPasswords')));
        $aBadPasswords[] = strtolower($this->getPerson()->getFirstName());
        $aBadPasswords[] = strtolower($this->getPerson()->getMiddleName());
        $aBadPasswords[] = strtolower($this->getPerson()->getLastName());

        return !in_array(strtolower($newPassword), $aBadPasswords);
    }
}
