<?php

namespace ChurchCRM\Emails;


class AccountDeletedEmail extends BaseUserEmail
{

    protected function getSubSubject()
    {
        return gettext("Your Account was Deleted");
    }

    protected function buildMessageBody()
    {
        return gettext("Your ChurchCRM Account was Deleted.");
    }
}
