<?php

namespace ChurchCRM\Emails\users;

class AccountDeletedEmail extends BaseUserEmail
{
    protected function getSubSubject(): string
    {
        return gettext('Your Account was Deleted');
    }

    protected function buildMessageBody(): string
    {
        return gettext('Your ChurchCRM Account was Deleted.');
    }
}
