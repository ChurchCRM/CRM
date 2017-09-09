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
          $myTokens['body'] = gettext("New Family Added")."\r\n".
                  gettext("Family Name").": ".$this->relatedObject->getName();
          $myTokens["familyLink"] = SystemURLs::getURL()."/FamilyView.php?FamilyID=".$this->relatedObject->getId();
        }
        elseif (get_class($this->relatedObject) == "ChurchCRM\Person")
        {
          $myTokens['body'] = gettext("New Person Added")."\r\n". gettext("Name").": ". $this->relatedObject->getFullName();
          $myTokens['personLink'] = SystemURLs::getURL()."/PersonView.php?PersonID=".$this->relatedObject->getId();
        }
        
        return array_merge($this->getCommonTokens(), $myTokens);
    }
}
