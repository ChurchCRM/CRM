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
        $msg = array();
        array_push($msg, gettext("We wanted to let you know that your ChurchCRM account was unlocked."));
        array_push($msg, "<a href='" . $this->getLink() . "'>" . gettext("Login"). "</a>");
        array_push($msg, gettext('Username') . ": " . $this->user->getUserName());
        return implode("<p/>", $msg);
    }
}
