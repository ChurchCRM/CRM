<?php

namespace ChurchCRM\Emails;


class UnlockedEmail extends BaseUserEmail
{

    public function __construct($user) {
        parent::__construct($user);
    }

    protected function getSubSubject()
    {
        return gettext("Account Unlocked");
    }

    protected function buildMessageBody()
    {
        $msg = array();
        array_push($msg, gettext("We wanted to let you know that your account was unlocked."));
        return implode("<p/>", $msg);
    }
}
