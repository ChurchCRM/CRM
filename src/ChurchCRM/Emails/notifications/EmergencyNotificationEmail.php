<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;


class EmergencyNotificationEmail extends BaseEmail
{
  
    public function __construct($toAddresses)
    {
        parent::__construct($toAddresses);
        $this->mail->Subject = SystemConfig::getValue("sChurchName") . ": " . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject()
    {
        return gettext("Emergency Notification");
    }
   
     public function getTokens()
    {
        $myTokens =  [
            "toName" => "test",
            "body" => gettext("Emergency Notification")
        ];
        return array_merge($this->getCommonTokens(), $myTokens);
    }
}
