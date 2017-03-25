<?php

namespace ChurchCRM\Emails;


class AccountDeletedEmail extends BaseUserEmail
{

    protected function getSubSubject()
    {
        return gettext("Your ChurchCRM Account was Deleted");
    }

    protected function buildMessageBody()
    {
        $msg = array();
        array_push($msg, gettext("Your ChurchCRM Account was Deleted."));
        array_push($msg, gettext("If you think this is an error") . " " . gettext("please contact your admin"));
        return implode("<p/>", $msg);
    }
}
