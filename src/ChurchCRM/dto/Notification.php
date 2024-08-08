<?php

namespace ChurchCRM\dto;

use ChurchCRM\Emails\notifications\NotificationEmail;
use ChurchCRM\model\ChurchCRM\Person;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;

class Notification
{
    protected string $projectorText;
    protected array $recipients;
    protected ?Person $person = null;

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

        $email = new NotificationEmail($emailaddresses, $this->person->getFullName());

        return $email->send();
    }

    private function sendSMS(): bool
    {
        $client = new Client(new Basic(SystemConfig::getValue('sNexmoAPIKey'), SystemConfig::getValue('sNexmoAPISecret')));

        foreach ($this->recipients as $recipient) {
            $client->message()->sendText(
                $recipient->getNumericCellPhone(),
                SystemConfig::getValue('sNexmoFromNumber'),
                gettext('Notification for') . ' ' . $this->person->getFullName()
            );
        }

        return true;
    }

    private function sendProjector(): string
    {
        $OLPAlert = new OpenLPNotification(
            SystemConfig::getValue('sOLPURL'),
            SystemConfig::getValue('sOLPUserName'),
            SystemConfig::getValue('sOLPPassword')
        );
        $OLPAlert->setAlertText($this->projectorText);

        return $OLPAlert->send();
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
        if (SystemConfig::hasValidOpenLPSettings()) {
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
