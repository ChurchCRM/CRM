<?php

namespace ChurchCRM\Emails\users;

use ChurchCRM\model\ChurchCRM\User;

class ResetPasswordEmail extends BaseUserEmail
{
    protected string $password;

    public function __construct(User $user, string $password)
    {
        $this->password = $password;
        parent::__construct($user);
    }

    protected function getSubSubject(): string
    {
        return gettext('Password Reset');
    }

    protected function buildMessageBody(): string
    {
        return gettext('You ChurchCRM updated password has been changed') . ':';
    }

    public function getTokens(): array
    {
        $parentTokens = parent::getTokens();
        $myTokens = ['password' => $this->password,
            'passwordText'      => gettext('New Password')];

        return array_merge($parentTokens, $myTokens);
    }
}
