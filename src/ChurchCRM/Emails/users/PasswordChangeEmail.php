<?php

namespace ChurchCRM\Emails;


class PasswordChangeEmail extends BaseUserEmail
{

    protected function getSubSubject()
    {
        return gettext("Password Changed");
    }

    protected function buildMessageBody()
    {
        $msg = array();
        array_push($msg, gettext("We wanted to let you know that your ChurchCRM password was changed."));
        return implode("<p/>", $msg);
    }
}
