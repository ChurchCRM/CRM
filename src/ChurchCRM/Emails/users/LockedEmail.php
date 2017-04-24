<?php

namespace ChurchCRM\Emails;


class LockedEmail extends BaseUserEmail
{

    protected function getSubSubject()
    {
        return gettext("Account Locked");
    }

    protected function buildMessageBody()
    {
        $msg = array();
        array_push($msg, gettext("Your ChurchCRM account was locked."));
        array_push($msg, gettext("If you think this is an error") . " " . gettext("please contact your admin"));
        return implode("<p/>", $msg);
    }
}
