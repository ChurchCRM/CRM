<?php

namespace ChurchCRM\Emails;

class ResetPasswordEmail extends BaseUserEmail
{

    protected $password;

    public function __construct($user, $password) {
        $this->password = $password;
        parent::__construct($user);
    }

    protected function getSubSubject()
    {
        return gettext("Password Reset");
    }

    protected function buildMessageBody()
    {
        return gettext("You ChurchCRM updated password has been changed").":";
    }

    public function getTokens()
    {
        $parentTokens = parent::getTokens();
        $myTokens = ["password" => $this->password,
            "passwordText" => gettext('New Password')];
        return array_merge($parentTokens, $myTokens);
    }
}
