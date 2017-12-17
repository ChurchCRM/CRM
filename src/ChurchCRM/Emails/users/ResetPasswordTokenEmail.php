<?php

namespace ChurchCRM\Emails;

class ResetPasswordTokenEmail extends BaseUserEmail
{

    protected $token;

    public function __construct($user, $token) {
        $this->token = $token;
        parent::__construct($user);
    }

    protected function getSubSubject()
    {
        return gettext("Password Reset Link");
    }

    protected function buildMessageBody()
    {
        return gettext("You can reset your ChurchCRM password by clicking this link").":";
    }

    public function getTokens()
    {
        $parentTokens = parent::getTokens();
        $myTokens = ["passwordToken" => $this->token,
            "resetPasswordText" => gettext('Reset Password')];
        return array_merge($parentTokens, $myTokens);
    }
}
