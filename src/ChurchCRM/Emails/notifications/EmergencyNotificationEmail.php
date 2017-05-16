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
    }

    protected function getSubSubject()
    {
        return gettext("Emergency Notification");
    }

    protected function buildMessageBody()
    {
        return gettext("Emergency Notification");
    }
    
    public function  getTokens()
    {

    }
}
