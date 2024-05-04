<?php

namespace ChurchCRM\dto;

use ChurchCRM\Emails\notifications\NotificationEmail;
use ChurchCRM\model\ChurchCRM\Person;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;

class Notification
{
    protected $projectorText;
    protected $recipients;
    protected ?Person $person = null;

    public function setRecipients($recipients): void
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

    private function sendEmail()
    {
        $emailaddresses = [];
        foreach ($this->recipients as $recipient) {
            $emailaddresses[] = $recipient->getEmail();
        }

        try {
            $email = new NotificationEmail($emailaddresses, $this->person->getFullName());
            $emailStatus = $email->send();

            return $emailStatus;
        } catch (\Exception $ex) {
            return false;
        }
    }

    private function sendSMS(): bool
    {
        try {
            $client = new Client(new Basic(SystemConfig::getValue('sNexmoAPIKey'), SystemConfig::getValue('sNexmoAPISecret')));

            foreach ($this->recipients as $recipient) {
                $message = $client->message()->sendText([
                    'to'   => $recipient->getNumericCellPhone(),
                    'from' => SystemConfig::getValue('sNexmoFromNumber'),
                    'text' => gettext('Notification for') . ' ' . $this->person->getFullName(),
                ]);
            }

            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    private function sendProjector()
    {
        try {
            $OLPAlert = new OpenLPNotification(
                SystemConfig::getValue('sOLPURL'),
                SystemConfig::getValue('sOLPUserName'),
                SystemConfig::getValue('sOLPPassword')
            );
            $OLPAlert->setAlertText($this->projectorText);

            return $OLPAlert->send();
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function send(): array
    {
        $methods = [];
        if (SystemConfig::hasValidMailServerSettings()) {
            $send = $this->sendEmail();
            $methods[] = 'email: ' . $send;
        }
        if (SystemConfig::hasValidSMSServerSettings()) {
            $send = (bool) $this->sendSMS();
            $methods[] = 'sms: ' . $send;
        }
        if (SystemConfig::hasValidOpenLPSettings()) {
            $send = (bool) $this->sendProjector();
            $methods[] = 'projector: ' . $send;
        }
        return [
            'status'  => '',
            'methods' => $methods,
        ];
    }
}
