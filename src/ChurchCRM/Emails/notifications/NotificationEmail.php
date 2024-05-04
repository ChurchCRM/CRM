<?php

namespace ChurchCRM\Emails\notifications;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\BaseEmail;

class NotificationEmail extends BaseEmail
{
    private string $notificationSource;

    /**
     * @param string[] $toAddresses
     */
    public function __construct(array $toAddresses, string $notificationSource)
    {
        $this->notificationSource = $notificationSource;
        parent::__construct($toAddresses);
        $this->mail->Subject = SystemConfig::getValue('sChurchName') . ': ' . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject(): string
    {
        return gettext('Notification');
    }

    public function getTokens(): array
    {
        $myTokens = [
            'toName' => gettext('Guardian(s) of') . ' ' . $this->notificationSource,
            'body'   => gettext('A notification was triggered by the classroom teacher at') . ' ' . date('Y-m-d H:i:s') . ' ' . gettext('Please go to this location'),
        ];

        return array_merge($this->getCommonTokens(), $myTokens);
    }

    protected function getFullURL(): string
    {
        return '';
    }

    protected function getButtonText(): string
    {
        return '';
    }
}
