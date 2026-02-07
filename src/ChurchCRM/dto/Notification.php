<?php

namespace ChurchCRM\dto;

use ChurchCRM\Emails\notifications\NotificationEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Plugin\PluginManager;

class Notification
{
    protected string $projectorText;
    protected array $recipients;
    protected ?Person $person = null;
    protected string $eventName = '';

    public function setRecipients(array $recipients): void
    {
        $this->recipients = $recipients;
    }

    public function setSMSText($text): void
    {
    }

    public function setEmailText($text): void
    {
    }

    public function setPerson(Person $Person): void
    {
        $this->person = $Person;
    }

    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setProjectorText(string $text): void
    {
        $this->projectorText = $text;
    }

    private function sendEmail(): bool
    {
        $emailaddresses = [];
        foreach ($this->recipients as $recipient) {
            $emailaddresses[] = $recipient->getEmail();
        }

        $email = new NotificationEmail($emailaddresses, $this->person->getFullName(), $this->eventName);

        return $email->send();
    }

    private function sendSMS(): bool
    {
        $vonagePlugin = PluginManager::getPlugin('vonage');

        if ($vonagePlugin === null || !$vonagePlugin->isEnabled()) {
            throw new \RuntimeException('Vonage SMS plugin is not enabled');
        }

        $notificationMessage = gettext('Notification for') . ' ' . $this->person->getFullName();

        foreach ($this->recipients as $recipient) {
            $vonagePlugin->sendSMS(
                $recipient->getNumericCellPhone(),
                $notificationMessage
            );
        }

        return true;
    }

    private function sendProjector(): string
    {
        $openLpPlugin = PluginManager::getPlugin('openlp');

        if ($openLpPlugin === null || !$openLpPlugin->isEnabled()) {
            throw new \RuntimeException('OpenLP plugin is not enabled');
        }

        return $openLpPlugin->sendAlert($this->projectorText);
    }

    public function send(): array
    {
        $methods = [];

        if (SystemConfig::hasValidMailServerSettings()) {
            $sendEmail = false;
            try {
                $sendEmail = $this->sendEmail();
            } catch (\Throwable) {
                // do nothing
            }
            $methods[] = 'email: ' . $sendEmail;
        }

        // Check if Vonage SMS plugin is enabled and configured
        $vonagePlugin = PluginManager::getPlugin('vonage');
        if ($vonagePlugin !== null && $vonagePlugin->isEnabled() && $vonagePlugin->isConfigured()) {
            $sendSms = false;
            try {
                $sendSms = $this->sendSMS();
            } catch (\Throwable) {
                // do nothing
            }
            $methods[] = 'sms: ' . $sendSms;
        }

        // Check if OpenLP plugin is enabled and configured
        $openLpPlugin = PluginManager::getPlugin('openlp');
        if ($openLpPlugin !== null && $openLpPlugin->isEnabled() && $openLpPlugin->isConfigured()) {
            $sendOpenLp = false;
            try {
                $sendOpenLp = (bool) $this->sendProjector();
            } catch (\Throwable) {
                // do nothing
            }
            $methods[] = 'projector: ' . $sendOpenLp;
        }
        return [
            'status'  => '',
            'methods' => $methods,
        ];
    }
}
