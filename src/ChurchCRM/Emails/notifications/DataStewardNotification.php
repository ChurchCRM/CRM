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
      if (get_class($this->notificationObject) == "Person")
      {
        return gettext("Data Notification") . $this->notificationType;
      }
    }
   
     public function getTokens()
    {
        $myTokens =  [
            "toName" => SystemConfig::getValue("sChurchName") . gettext("Data Steward,"),
            "body" => "New Notification"
        ];
        return array_merge($this->getCommonTokens(), $myTokens);
    }
}
