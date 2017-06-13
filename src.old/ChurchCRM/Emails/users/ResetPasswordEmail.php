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
        return gettext("You can reset your ChurchCRM password by clicking this link").":";
    }

    public function getTokens()
    {
        $parentTokens = parent::getTokens();
        $myTokens = ["password" => $this->password,
            "passwordText" => gettext('New Password')];
        return array_merge($parentTokens, $myTokens);
    }
}
