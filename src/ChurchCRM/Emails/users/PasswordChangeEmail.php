<?php

namespace ChurchCRM\Emails;


class PasswordChangeEmail extends BaseUserEmail
{

    public function __construct($user) {
        parent::__construct($user);
    }

    protected function getSubSubject()
    {
        return gettext("Password Changed");
    }

    protected function buildMessageBody()
    {
        $msg = array();
        array_push($msg, gettext("We wanted to let you know that your password was changed."));
        return implode("<p/>", $msg);
    }
}
