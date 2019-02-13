<?php

namespace ChurchCRM;

use ChurchCRM\Base\User as BaseUser;
use ChurchCRM\dto\SystemConfig;
use Propel\Runtime\Connection\ConnectionInterface;
use ChurchCRM\Utils\MiscUtils;

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
        return $this->isAdmin() || $this->isFinance();
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

    public function isEnabledSecurity($securityConfigName){
        if ($this->isAdmin()) {
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
}
