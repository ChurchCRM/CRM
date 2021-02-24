<?php

namespace ChurchCRM;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\Base\User as BaseUser;
use ChurchCRM\dto\SystemConfig;
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

    public function getName()
    {
        return $this->getPerson()->getFullName();
    }

    public function getEmail()
    {
        return $this->getPerson()->getEmail();
    }

    public function getFullName()
    {
        return $this->getPerson()->getFullName();
    }

    public function isAddRecordsEnabled()
    {
        return $this->isAdmin() || $this->isAddRecords();
    }

    public function isEditRecordsEnabled()
    {
        return $this->isAdmin() || $this->isEditRecords();
    }

    public function isDeleteRecordsEnabled()
    {
        return $this->isAdmin() || $this->isDeleteRecords();
    }

    public function isMenuOptionsEnabled()
    {
        return $this->isAdmin() || $this->isMenuOptions();
    }

    public function isManageGroupsEnabled()
    {
        return $this->isAdmin() || $this->isManageGroups();
    }

    public function isFinanceEnabled()
    {
        if (SystemConfig::getBooleanValue("bEnabledFinance")) {
            return $this->isAdmin() || $this->isFinance();
        }
        return false;
    }

    public function isNotesEnabled()
    {
        return $this->isAdmin() || $this->isNotes();
    }

    public function isEditSelfEnabled()
    {
        return $this->isAdmin() || $this->isEditSelf();
    }

    public function isCanvasserEnabled()
    {
        return $this->isAdmin() || $this->isCanvasser();
    }

    public function updatePassword($password)
    {
        $this->setPassword($this->hashPassword($password));
    }

    public function isPasswordValid($password)
    {
        return $this->getPassword() == $this->hashPassword($password);
    }

    public function hashPassword($password)
    {
        return hash('sha256', $password . $this->getPersonId());
    }

    public function isAddEventEnabled() // TODO: Create permission to manag event deletion see https://github.com/ChurchCRM/CRM/issues/4726
    {
        return $this->isAddEvent();
    }

    public function isAddEvent()
    {
        return $this->isAdmin() || $this->isEnabledSecurity('bAddEvent');
    }

    public function isCSVExportEnabled()
    {
        return $this->isCSVExport();
    }

    public function isCSVExport()
    {
        return $this->isAdmin() || $this->isEnabledSecurity('bExportCSV');
    }

    public function isEmailEnabled()
    {
        return $this->isAdmin() || $this->isEnabledSecurity('bEmailMailto');
    }

    public function isCreateDirectoryEnabled()
    {
        return $this->isAdmin() || $this->isEnabledSecurity('bCreateDirectory');
    }

    public function isbUSAddressVerificationEnabled()
    {
        return $this->isAdmin() || $this->isEnabledSecurity('bUSAddressVerification');
    }


    public function isLocked()
    {
        return SystemConfig::getValue('iMaxFailedLogins') > 0 && $this->getFailedLogins() >= SystemConfig::getValue('iMaxFailedLogins');
    }

    public function resetPasswordToRandom() {
        $password = User::randomPassword();
        $this->updatePassword($password);
        $this->setNeedPasswordChange(true);
        $this->setFailedLogins(0);
        return $password;
    }

    public static function randomPassword()
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < SystemConfig::getValue('iMinPasswordLength'); $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }

    public static function randomApiKey()
    {
        return MiscUtils::randomToken();
    }

    public function postInsert(ConnectionInterface $con = null)
    {
        $this->createTimeLineNote("created");
    }

    public function postDelete(ConnectionInterface $con = null)
    {
        $this->createTimeLineNote("deleted");
    }

    public function createTimeLineNote($type)
    {
        $note = new Note();
        $note->setPerId($this->getPersonId());
        $note->setEntered(AuthenticationManager::GetCurrentUser()->getId());
        $note->setType('user');

        switch ($type) {
            case "created":
                $note->setText(gettext('system user created'));
                break;
            case "updated":
                $note->setText(gettext('system user updated'));
                break;
            case "deleted":
                $note->setText(gettext('system user deleted'));
                break;
            case "password-reset":
                $note->setText(gettext('system user password reset'));
                break;
            case "password-changed":
                $note->setText(gettext('system user changed password'));
                break;
            case "password-changed-admin":
                $note->setText(gettext('system user password changed by admin'));
                break;
            case "login-reset":
                $note->setText(gettext('system user login reset'));
                break;
        }

        $note->save();
    }

    public function isEnabledSecurity($securityConfigName) {
        if ($this->isAdmin()) {
            return true;
        } else if ($securityConfigName == "bAdmin") {
            return false;
        }

        if ($securityConfigName == "bAll") {
            return true;
        }


        if ($securityConfigName == "bAddRecords" && $this->isAddRecordsEnabled()) {
            return true;
        }

        if ($securityConfigName == "bEditRecords" && $this->isEditRecordsEnabled()) {
            return true;
        }

        if ($securityConfigName == "bDeleteRecords" && $this->isDeleteRecordsEnabled()) {
            return true;
        }

        if ($securityConfigName == "bManageGroups" && $this->isManageGroupsEnabled()) {
            return true;
        }

        if ($securityConfigName == "bFinance" && $this->isFinanceEnabled()) {
            return true;
        }

        if ($securityConfigName == "bNotes" && $this->isNotesEnabled()) {
            return true;
        }

        if ($securityConfigName == "bCanvasser" && $this->isCanvasserEnabled()) {
            return true;
        }

        foreach ($this->getUserConfigs() as $userConfig) {
            if ($userConfig->getName() == $securityConfigName) {
                return $userConfig->getPermission() == "TRUE";
            }
        }
        return false;
    }

    public function getUserConfigString($userConfigName) {
      foreach ($this->getUserConfigs() as $userConfig) {
        if ($userConfig->getName() == $userConfigName) {
          return $userConfig->getValue();
        }
      }
    }

    public function setUserConfigString($userConfigName, $value) {
        foreach ($this->getUserConfigs() as $userConfig) {
            if ($userConfig->getName() == $userConfigName) {
                return $userConfig->setValue($value);
            }
        }
    }


    public function setSetting($name, $value) {
        $setting = $this->getSetting($name);
        if (!$setting) {
            $setting = new UserSetting();
            $setting->set($this, $name, $value);
        } else {
            $setting->setValue($value);
        }
        $setting->save();
    }

    public function getSettingValue($name) {
        $userSetting = $this->getSetting($name);
        return (is_null($userSetting) ? "" : $userSetting->getValue());
    }

    public function getSetting($name) {
        foreach ($this->getUserSettings() as $userSetting) {
            if ($userSetting->getName() == $name) {
                return $userSetting;
            }
        }
        return null;
    }

    public function getStyle(){
        $skin = is_null($this->getSetting(UserSetting::UI_STYLE)) ? "skin-red" : $this->getSetting(UserSetting::UI_STYLE);
        $cssClasses = [];
        array_push($cssClasses, $skin);
        array_push($cssClasses, $this->getSetting(UserSetting::UI_BOXED));
        array_push($cssClasses, $this->getSetting(UserSetting::UI_SIDEBAR));
        return implode(" ", $cssClasses);
    }

    public function isShowPledges() {
        return $this->getSettingValue(UserSetting::FINANCE_SHOW_PLEDGES) == "true";
    }

    public function isShowPayments() {
        return $this->getSettingValue(UserSetting::FINANCE_SHOW_PAYMENTS) == "true";
    }

    public function getShowSince() {
        return $this->getSettingValue(UserSetting::FINANCE_SHOW_SINCE);
    }

    public function provisionNew2FAKey() {
        $google2fa = new Google2FA();
        $key = $google2fa->generateSecretKey();
        // store the temporary 2FA key in a private variable on this User object
        // we don't want to update the database with the new key until we've confirmed
        // that the user is capapble of generating valid 2FA codes
        // encrypt the 2FA key since this object and its properties are serialized into the $_SESSION store
        // which is generally written to disk.
        $this->provisional2FAKey = Crypto::encryptWithPassword($key, KeyManager::GetTwoFASecretKey());
        return $key;
    }

    public function confirmProvisional2FACode($twoFACode) {
        $google2fa = new Google2FA();
        $window = 2; //TODO: make this a system config
        $pw = Crypto::decryptWithPassword($this->provisional2FAKey, KeyManager::GetTwoFASecretKey());
        $isKeyValid = $google2fa->verifyKey($pw, $twoFACode, $window);
        if ($isKeyValid) {
            $this->setTwoFactorAuthSecret($this->provisional2FAKey);
            $this->save();
            return true;
        }
        return $isKeyValid;

    }

    public function remove2FAKey() {
        $this->setTwoFactorAuthSecret(null);
        $this->save();
    }

    public function getDecryptedTwoFactorAuthSecret() {
        return Crypto::decryptWithPassword($this->getTwoFactorAuthSecret(), KeyManager::GetTwoFASecretKey());
    }

    private function getDecryptedTwoFactorAuthRecoveryCodes() {
        return explode(",",Crypto::decryptWithPassword($this->getTwoFactorAuthRecoveryCodes(), KeyManager::GetTwoFASecretKey()));
    }

    public function disableTwoFactorAuthentication() {
        $this->setTwoFactorAuthRecoveryCodes(null);
        $this->setTwoFactorAuthSecret(null);
        $this->save();
    }

    public function is2FactorAuthEnabled() {
        return !empty($this->getTwoFactorAuthSecret());
    }

    public function getNewTwoFARecoveryCodes() {
        // generate an array of 2FA recovery codes, and store as an encrypted, comma-seperated list
        $recoveryCodes = array();
        for($i=0; $i < 12; $i++) {
            $recoveryCodes[$i] = base64_encode(random_bytes(10));
        }
        $recoveryCodesString = implode(",",$recoveryCodes);
        $this->setTwoFactorAuthRecoveryCodes(Crypto::encryptWithPassword($recoveryCodesString, KeyManager::GetTwoFASecretKey()));
        $this->save();
        return $recoveryCodes;
    }

    public function isTwoFACodeValid($twoFACode) {
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

    public function isTwoFaRecoveryCodeValid($twoFaRecoveryCode) {
        // checks for validity of a 2FA recovery code
        // if the specified code was valid, the code is also removed.
        $codes = $this->getDecryptedTwoFactorAuthRecoveryCodes();
        if (($key = array_search($twoFaRecoveryCode, $codes)) !== false) {
            unset($codes[$key]);
            $recoveryCodesString = implode(",",$codes);
            $this->setTwoFactorAuthRecoveryCodes(Crypto::encryptWithPassword($recoveryCodesString, KeyManager::GetTwoFASecretKey()));
            return true;
        }
        return false;
    }

    public function adminSetUserPassword($newPassword)
    {
        $this->updatePassword($newPassword);
        $this->setNeedPasswordChange(false);
        $this->save();
        $this->createTimeLineNote("password-changed-admin");
        return;
    }

    public function userChangePassword($oldPassword, $newPassword)
    {
        if (!$this->isPasswordValid($oldPassword)) {
            throw new PasswordChangeException("Old", gettext('Incorrect password supplied for current user'));
        }

        if (!$this->GetIsPasswordPermissible($newPassword)) {
            throw new PasswordChangeException("New", gettext('Your password choice is too obvious. Please choose something else.'));
        }

        if (strlen($newPassword) < SystemConfig::getValue('iMinPasswordLength')) {
            throw new PasswordChangeException("New", gettext('Your new password must be at least') . ' ' . SystemConfig::getValue('iMinPasswordLength') . ' ' . gettext('characters'));
        }

        if ($newPassword == $oldPassword) {
            throw new PasswordChangeException("New", gettext('Your new password must not match your old one.'));
        }

        if (levenshtein(strtolower($newPassword), strtolower($oldPassword)) < SystemConfig::getValue('iMinPasswordChange')) {
            throw new PasswordChangeException("New", gettext('Your new password is too similar to your old one.'));
        }

        $this->updatePassword($newPassword);
        $this->setNeedPasswordChange(false);
        $this->save();
        $this->createTimeLineNote("password-changed");
        return;
    }
    private function GetIsPasswordPermissible($newPassword) {
        $aBadPasswords = explode(',', strtolower(SystemConfig::getValue('aDisallowedPasswords')));
        $aBadPasswords[] = strtolower($this->getPerson()->getFirstName());
        $aBadPasswords[] = strtolower($this->getPerson()->getMiddleName());
        $aBadPasswords[] = strtolower($this->getPerson()->getLastName());
        return ! in_array(strtolower($newPassword), $aBadPasswords);
      }
}
