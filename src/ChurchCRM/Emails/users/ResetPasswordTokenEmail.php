<?php

namespace ChurchCRM\Emails\users;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;

class ResetPasswordTokenEmail extends BaseUserEmail
{
    protected string $token;

    public function __construct(User $user, string $tokenString)
    {
        $this->token = $tokenString;
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
