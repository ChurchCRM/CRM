<?php

namespace ChurchCRM\Emails;

class NewAccountEmail extends BaseUserEmail
{
    protected $password;

    public function __construct($user, $password)
    {
        $this->password = $password;
        parent::__construct($user);
    }

    protected function getSubSubject(): string
    {
        return gettext('Your New Account');
    }

    protected function buildMessageBody(): string
    {
        return gettext('A ChurchCRM account was created for you') . ':';
    }

    public function getTokens(): array
    {
        $parentTokens = parent::getTokens();
        $myTokens = ['password' => $this->password,
            'passwordText'      => gettext('New Password')];

        return array_merge($parentTokens, $myTokens);
    }
}
