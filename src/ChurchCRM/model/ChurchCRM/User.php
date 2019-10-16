<?php

namespace ChurchCRM;

use ChurchCRM\Base\User as BaseUser;
use ChurchCRM\dto\SystemConfig;
use Propel\Runtime\Connection\ConnectionInterface;
use ChurchCRM\Utils\MiscUtils;
use Defuse\Crypto\Crypto;
use Endroid\QrCode\QrCode;
use PragmaRX\Google2FA\Google2FA;

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
        $note->setEntered($_SESSION['user']->getId());
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

    public function getFormattedShowSince() {
        $showSince = "";
        if ($this->getShowSince() != null) {
            $showSince = $this->getShowSince()->format('Y-m-d');
        }
        return $showSince;
    }

    public function regenerate2FAKey() {
        $google2fa = new Google2FA();
        $encryptedSecret = Crypto::encryptWithPassword($google2fa->generateSecretKey(), KeyManager::GetTwoFASecretKey());
        $this->setTwoFactorAuthSecret($encryptedSecret);
        $this->save();
    }

    public function remove2FAKey() {
        $this->setTwoFactorAuthSecret(null);
        $this->save();
    }

    private function getDecryptedTwoFactorAuthSecret() {
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

    public function getTwoFactorAuthQRCode() {
        if (empty($this->getTwoFactorAuthSecret()))
        {
            return null;
        }
        $google2fa = new Google2FA();
        $g2faUrl = $google2fa->getQRCodeUrl(
            SystemConfig::getValue("s2FAApplicationName"),
            $this->getUserName(),
            $this->getDecryptedTwoFactorAuthSecret()
        );
        $qrCode = new QrCode($g2faUrl );
        $qrCode->setSize(300);
        return $qrCode;
    }

    public function getTwoFactorAuthQRCodeDataUri() {
        $qrCode =  $this->getTwoFactorAuthQRCode();
        if ($qrCode)
        {
            return $qrCode->writeDataUri();
        }
        return null;
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
        return $google2fa->verifyKey($this->getDecryptedTwoFactorAuthSecret(), $twoFACode, $window);
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
}
