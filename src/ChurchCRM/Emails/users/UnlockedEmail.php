<?php

namespace ChurchCRM\Emails;


class UnlockedEmail extends BaseUserEmail
{

    protected function getSubSubject()
    {
        return gettext("Account Unlocked");
    }

    protected function buildMessageBody()
    {
        return gettext("We wanted to let you know that your ChurchCRM account was unlocked.");
    }
}
