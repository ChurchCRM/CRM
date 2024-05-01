<?php

namespace ChurchCRM\Emails\users;

class UnlockedEmail extends BaseUserEmail
{
    protected function getSubSubject(): string
    {
        return gettext('Account Unlocked');
    }

    protected function buildMessageBody(): string
    {
        return gettext('Your ChurchCRM account was unlocked.');
    }
}
