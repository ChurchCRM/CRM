<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemURLs;

class ResetPasswordTokenEmail extends BaseUserEmail
{
    protected $token;

    public function __construct($user, $token)
    {
        $this->token = $token;
        parent::__construct($user);
    }

    protected function getSubSubject(): string
    {
        return gettext('Password Reset Link');
    }

    protected function buildMessageBody(): string
    {
        return gettext('You can reset your ChurchCRM password by clicking this link') . ':';
    }

    protected function getFullURL(): string
    {
        return SystemURLs::getURL() . '/session/forgot-password/set/' . $this->token;
    }

    protected function getButtonText(): string
    {
        return gettext('Reset Password');
    }
}
