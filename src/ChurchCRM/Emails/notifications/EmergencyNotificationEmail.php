<?php

namespace ChurchCRM\Emails;


class EmergencyNotificationEmail extends BaseUserEmail
{

    protected function getSubSubject()
    {
        return gettext("Emergency Notification");
    }

    protected function buildMessageBody()
    {
        return gettext("Emergency Notification");
    }
}
