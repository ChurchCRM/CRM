<?php

namespace ChurchCRM\Emails;


class PasswordChangeEmail extends BaseUserEmail
{
    protected $password;

    public function __construct($user, $password) {
        $this->password = $password;
        parent::__construct($user);
    }

    protected function getSubSubject()
    {
        return gettext("Password Changed");
    }

    protected function buildMessageBody()
    {
        return gettext("Your ChurchCRM password was changed").":";
    }

    public function getTokens()
    {
        $parentTokens = parent::getTokens();
        $myTokens = ["password" => $this->password,
            "passwordText" => gettext('New Password')];
        return array_merge($parentTokens, $myTokens);
    }
}
