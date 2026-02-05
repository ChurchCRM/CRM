<?php

namespace ChurchCRM\dto;

use ChurchCRM\Emails\notifications\NotificationEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Plugin\PluginManager;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;

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
        $client = new Client(new Basic(
            SystemConfig::getValue('plugin.vonage.apiKey'),
            SystemConfig::getValue('plugin.vonage.apiSecret')
        ));

        foreach ($this->recipients as $recipient) {
            $client->message()->sendText(
                $recipient->getNumericCellPhone(),
                SystemConfig::getValue('plugin.vonage.fromNumber'),
                gettext('Notification for') . ' ' . $this->person->getFullName()
            );
        }

        return true;
    }

    private function sendProjector(): string
    {
        $pluginManager = PluginManager::getInstance();
        $openLpPlugin = $pluginManager->getPlugin('openlp');

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
        if (SystemConfig::hasValidSMSServerSettings()) {
            $sendSms = false;
            try {
                $sendSms = $this->sendSMS();
            } catch (\Throwable) {
                // do nothing
            }
            $methods[] = 'sms: ' . $sendSms;
        }
        // Check if OpenLP plugin is enabled and configured
        $pluginManager = PluginManager::getInstance();
        $openLpPlugin = $pluginManager->getPlugin('openlp');
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
