<?php

namespace ChurchCRM\Emails;

class PasswordChangeEmail extends BaseUserEmail
{
    protected $password;

    public function __construct($user, $password)
    {
        $this->password = $password;
        parent::__construct($user);
    }

    protected function getSubSubject(): string
    {
        return gettext('Password Changed');
    }

    protected function buildMessageBody(): string
    {
        return gettext('Your ChurchCRM password was changed') . ':';
    }

    public function getTokens(): array
    {
        $parentTokens = parent::getTokens();
        $myTokens = ['password' => $this->password,
            'passwordText'      => gettext('New Password')];

        return array_merge($parentTokens, $myTokens);
    }
}
