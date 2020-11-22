<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\PersonQuery;
use ChurchCRM\FamilyQuery;
use ChurchCRM\dto\SystemURLs;

class NewPersonOrFamilyEmail extends BaseEmail
{
    private $relatedObject;

    public function __construct($RelatedObject)
    {
      $this->relatedObject = $RelatedObject;

      $toAddresses = [];
      $recipientPeople = explode(",",SystemConfig::getValue("sNewPersonNotificationRecipientIDs") );

      foreach($recipientPeople as $PersonID) {
        $Person = PersonQuery::create()->findOneById($PersonID);
        if(!empty($Person)) {
          $email = $Person->getEmail();
          if (!empty($email)) {
            array_push($toAddresses,$email);
          }
        }
      }

      parent::__construct($toAddresses);
      $this->mail->Subject = SystemConfig::getValue("sChurchName") . ": " . $this->getSubSubject();
      $this->mail->isHTML(true);
      $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject()
    {
      if (get_class($this->relatedObject) == "ChurchCRM\Person")
      {
        return gettext("New Person Added");
      }
      else if (get_class($this->relatedObject) == "ChurchCRM\Family")
      {
        return gettext("New Family Added");
      }

    }

     public function getTokens()
    {
        $myTokens =  [
            "toName" => gettext("Church Greeter")
        ];
        if (get_class($this->relatedObject) == "ChurchCRM\Family")
        {
          /* @var $family ChurchCRM\Family */
          $family = $this->relatedObject;
          $myTokens['body'] = gettext("New Family Added")."\r\n".
            gettext("Family Name").": ". $family->getName();
          $myTokens['FamilyEmail'] =  $family->getEmail();
          $myTokens['FamilyPhone'] = $family->getCellPhone();
          $myTokens['FamilyAddress'] =  $family->getAddress();
          $myTokens['IncludeDataInNewFamilyNotifications'] = SystemConfig::getBooleanValue("IncludeDataInNewPersonNotifications");
        }
        elseif (get_class($this->relatedObject) == "ChurchCRM\Person")
        {
          /* @var $person ChurchCRM\Person */
          $person = $this->relatedObject;
          $myTokens['body'] = gettext("New Person Added")."\r\n".
            gettext("Person Name").": ". $person->getFullName();
          $myTokens['PersonEmail'] = $person->getEmail();
          $myTokens['PersonPhone'] = $person->getCellPhone();
          $myTokens['PersonAddress'] = $person->getAddress();
          $myTokens['PersonAge'] = $person->getAge();
            $myTokens['IncludeDataInNewPersonNotifications'] = SystemConfig::getBooleanValue("IncludeDataInNewPersonNotifications");
        }
        $myTokens['sGreeterCustomMsg1'] = SystemConfig::getValue("sGreeterCustomMsg1");
        $myTokens['sGreeterCustomMsg2'] = SystemConfig::getValue("sGreeterCustomMsg2");

        return array_merge($this->getCommonTokens(), $myTokens);
    }

    function getFullURL()
    {
        if (get_class($this->relatedObject) == "ChurchCRM\Family") {
            return SystemURLs::getURL() . "/v2/family/" . $this->relatedObject->getId();
        }
        elseif (get_class($this->relatedObject) == "ChurchCRM\Person") {
            return SystemURLs::getURL()."/PersonView.php?PersonID=". $this->relatedObject->getId();
        }
    }

    function getButtonText()
    {
        if (get_class($this->relatedObject) == "ChurchCRM\Family") {
            return gettext("View Family Page");
        }
        elseif (get_class($this->relatedObject) == "ChurchCRM\Person") {
            return gettext("View Person Page");
        }

    }
}
