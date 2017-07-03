<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;

class DataStewardNotification extends BaseEmail
{
    private $notificationObject;
    
    public function __construct($toAddresses,$notificationObject)
    {
        $this->notificationObject = $notificationObject;
        parent::__construct($toAddresses);
        $this->mail->Subject = SystemConfig::getValue("sChurchName") . ": " . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject()
    {
      if (get_class($this->notificationObject) == "ChurchCRM\Person")
      {
        return gettext("New Person");
      }
    }
    
    protected function getBody()
    {
      if (get_class($this->notificationObject) == "ChurchCRM\Person")
      {
        return gettext("There is a new Person."). "<a href=\"".SystemURLs::getURL().$this->notificationObject->getViewURI()."\">Click Here to View " . $this->notificationObject->getFullName()."</a>";
      }
    }
   
     public function getTokens()
    {
        $myTokens =  [
            "toName" => SystemConfig::getValue("sChurchName") . " " . gettext("Data Steward"),
            "body" => $this->getBody()
        ];
        return array_merge($this->getCommonTokens(), $myTokens);
    }
}
