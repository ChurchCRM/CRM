<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\PersonQuery;
use ChurchCRM\FamilyQuery;
use ChurchCRM\dto\SystemURLs;

class NewPersonOrFamilyEmail extends BaseEmail
{
    private $notificationSource;
    
    const FAMILY = 1;
    const PERSON = 2;
    
    private $notificationType;
    private $relatedId;
    
    public function __construct($notificationType,$RelatedId)
    {
       
        $this->notificationType = $notificationType;
        $this->relatedId = $RelatedId;
        $toAddresses = [];
        $recipientPeople = explode(",",SystemConfig::getValue("sNewPersonNotificationRecipients") );

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
      if ($this->notificationType == self::PERSON)
      {
        return gettext("New Person Added");
      }
      else if ($this->notificationType == self::FAMILY)
      {
        return gettext("New Family Added");
      }
        
    }
   
     public function getTokens()
    {
        $myTokens =  [
            "toName" => gettext("Church Greeter")
        ];
        if ($this->notificationType == self::FAMILY)
        {
          $family = FamilyQuery::create()->findOneById($this->relatedId);
          $myTokens['body'] = "<strong>".gettext("New Family Added")."</strong><br/>".
                  gettext("Family Name").": ".$family->getName() .
                  "<p>".gettext("Family Link").": <a href=\"".SystemURLs::getURL()."/FamilyView.php?FamilyID=".$this->relatedId."\">".gettext("View Family Page")."</a></p>";
        }
        else if ($this->notificationType == self::PERSON)
        {
          $person = PersonQuery::create()->findOneById($this->relatedId);
          $myTokens['body'] = "<strong>".gettext("New Person Added")."</strong><br/>".
                  gettext("Name").": ".$person->getFullName() .
                  "<p>".gettext("Person Link").": <a href=\"".SystemURLs::getURL()."/PersonView.php?PersonID=".$this->relatedId."\">".gettext("View Person Page")."</a></p>";
        }
        
        return array_merge($this->getCommonTokens(), $myTokens);
    }
}
