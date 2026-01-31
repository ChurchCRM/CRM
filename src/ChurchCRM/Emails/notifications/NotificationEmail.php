<?php

namespace ChurchCRM\Emails\notifications;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\BaseEmail;

class NotificationEmail extends BaseEmail
{
    private string $notificationSource;
    private string $eventName;

    /**
     * @param string[] $toAddresses
     */
    public function __construct(array $toAddresses, string $notificationSource, string $eventName = '')
    {
        $this->notificationSource = $notificationSource;
        $this->eventName = $eventName;
        parent::__construct($toAddresses);
        $this->mail->Subject = SystemConfig::getValue('sChurchName') . ': ' . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject(): string
    {
        return gettext('Pickup Request') . ' - ' . $this->notificationSource;
    }

    public function getTokens(): array
    {
        // Format time in a user-friendly way
        $formattedTime = date('g:i A'); // e.g., "12:33 PM"
        
        // Build the message body (plain text - HTML is escaped by template)
        $body = gettext('Your child') . ' ' . $this->notificationSource . ' ' . 
                gettext('needs to be picked up from') . ' ';
        
        if (!empty($this->eventName)) {
            $body .= $this->eventName . '.';
        } else {
            $body .= gettext('their classroom') . '.';
        }
        
        $body .= "\n\n" . gettext('The teacher sent this alert at') . ' ' . $formattedTime . '.';
        $body .= "\n\n" . gettext('Please proceed to the classroom as soon as possible.');
        
        $myTokens = [
            'toName' => gettext('Guardian(s) of') . ' ' . $this->notificationSource,
            'body'   => $body,
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
