<?php

namespace ChurchCRM\Shepherd;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\BaseEmail;

final class ShepherdEmail extends BaseEmail
{
    public function __construct(
        string $recipient,
        private readonly string $recipientName,
        private readonly string $subject,
        private readonly string $body,
        private readonly string $actionUrl = '',
        private readonly string $actionLabel = ''
    ) {
        parent::__construct([$recipient]);
        $this->mail->Subject = 'Shepherd: ' . $this->subject;
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    public function getTokens(): array
    {
        return array_merge($this->getCommonTokens(), [
            'toName' => $this->recipientName,
            'body' => $this->body,
            'userName' => '',
            'userNameText' => '',
        ]);
    }

    protected function getFullURL(): string
    {
        return $this->actionUrl;
    }

    protected function getButtonText(): string
    {
        return $this->actionLabel;
    }

    protected function getPreheader(): string
    {
        return $this->subject ?: SystemConfig::getValue('sEmailPreheader');
    }
}
