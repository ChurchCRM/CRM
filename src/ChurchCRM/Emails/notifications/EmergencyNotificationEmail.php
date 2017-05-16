<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;


class EmergencyNotificationEmail extends BaseEmail
{
    private $notificationSource;
    
    public function __construct($toAddresses,$notificationSource)
    {
        $this->notificationSource = $notificationSource;
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
            "toName" => "Guardian(s) of ". $this->notificationSource,
            "body" => gettext("An Emergency Notification was triggered")
        ];
        return array_merge($this->getCommonTokens(), $myTokens);
    }
}
