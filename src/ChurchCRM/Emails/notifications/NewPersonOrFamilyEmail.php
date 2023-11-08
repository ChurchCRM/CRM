<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;

class NewPersonOrFamilyEmail extends BaseEmail
{
    private $relatedObject;

    public function __construct($RelatedObject)
    {
        $this->relatedObject = $RelatedObject;

        $toAddresses = [];
        $recipientPeople = explode(',', SystemConfig::getValue('sNewPersonNotificationRecipientIDs'));

        foreach ($recipientPeople as $PersonID) {
            $Person = PersonQuery::create()->findOneById($PersonID);
            if (!empty($Person)) {
                $email = $Person->getEmail();
                if (!empty($email)) {
                    array_push($toAddresses, $email);
                }
            }
        }

        parent::__construct($toAddresses);
        $this->mail->Subject = SystemConfig::getValue('sChurchName').': '.$this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject()
    {
        if (get_class($this->relatedObject) == \ChurchCRM\Person::class) {
            return gettext('New Person Added');
        } elseif (get_class($this->relatedObject) == \ChurchCRM\Family::class) {
            return gettext('New Family Added');
        }
    }

    public function getTokens()
    {
        $myTokens = [
            'toName' => gettext('Church Greeter'),
        ];
        if (get_class($this->relatedObject) == \ChurchCRM\Family::class) {
            /* @var $family ChurchCRM\Family */
            $family = $this->relatedObject;
            $myTokens['body'] = gettext('New Family Added')."\r\n".
            gettext('Family Name').': '.$family->getName();
            $myTokens['FamilyEmail'] = $family->getEmail();
            $myTokens['FamilyPhone'] = $family->getCellPhone();
            $myTokens['FamilyAddress'] = $family->getAddress();
            $myTokens['IncludeDataInNewFamilyNotifications'] = SystemConfig::getBooleanValue('IncludeDataInNewPersonNotifications');
        } elseif (get_class($this->relatedObject) == \ChurchCRM\Person::class) {
            /* @var $person ChurchCRM\Person */
            $person = $this->relatedObject;
            $myTokens['body'] = gettext('New Person Added')."\r\n".
            gettext('Person Name').': '.$person->getFullName();
            $myTokens['PersonEmail'] = $person->getEmail();
            $myTokens['PersonPhone'] = $person->getCellPhone();
            $myTokens['PersonAddress'] = $person->getAddress();
            $myTokens['PersonAge'] = $person->getAge();
            $myTokens['IncludeDataInNewPersonNotifications'] = SystemConfig::getBooleanValue('IncludeDataInNewPersonNotifications');
        }
        $myTokens['sGreeterCustomMsg1'] = SystemConfig::getValue('sGreeterCustomMsg1');
        $myTokens['sGreeterCustomMsg2'] = SystemConfig::getValue('sGreeterCustomMsg2');

        return array_merge($this->getCommonTokens(), $myTokens);
    }

    protected function getFullURL()
    {
        if (get_class($this->relatedObject) == \ChurchCRM\Family::class) {
            return SystemURLs::getURL().'/v2/family/'.$this->relatedObject->getId();
        } elseif (get_class($this->relatedObject) == \ChurchCRM\Person::class) {
            return SystemURLs::getURL().'/PersonView.php?PersonID='.$this->relatedObject->getId();
        }
    }

    protected function getButtonText()
    {
        if (get_class($this->relatedObject) == \ChurchCRM\Family::class) {
            return gettext('View Family Page');
        } elseif (get_class($this->relatedObject) == \ChurchCRM\Person::class) {
            return gettext('View Person Page');
        }
    }
}
