<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemURLs;

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
        $myTokens = [
            "resetPasswordText" => gettext('Reset Password'),
            "passwordResetLinkURL" => SystemURLs::getURL() . "/session/forgot-password/set/".$this->token
        ];
        return array_merge($parentTokens, $myTokens);
    }
}
