<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;

abstract class BaseUserEmail extends BaseEmail
{
    protected $user;

    /**
     * BaseUserEmail constructor.
     *
     * @param $user User
     */
    public function __construct($user)
    {
        parent::__construct([$user->getEmail()]);
        $this->user = $user;
        $this->mail->Subject = SystemConfig::getValue('sChurchName') . ': ' . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    abstract protected function getSubSubject() :string;

    public function getTokens(): array
    {
        $myTokens = ['toName' => $this->user->getPerson()->getFirstName(),
            'userName'        => $this->user->getUserName(),
            'userNameText'    => gettext('Email/Username'),
            'body'            => $this->buildMessageBody(),
        ];

        return array_merge($this->getCommonTokens(), $myTokens);
    }

    protected function getFullURL(): string
    {
        return SystemURLs::getURL() . '/session/begin?username=' . $this->user->getUserName();
    }

    protected function getButtonText(): string
    {
        return $this->user->getUserName();
    }

    abstract protected function buildMessageBody() : string;
}
