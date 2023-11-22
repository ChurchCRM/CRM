<?php

namespace ChurchCRM\dto;

use ChurchCRM\Emails\NotificationEmail;
use ChurchCRM\model\ChurchCRM\Person;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;

class Notification
{
    protected $projectorText;
    protected $recipients;
    protected $person;

    public function setRecipients($recipients)
    {
        $this->recipients = $recipients;
    }

    public function setSMSText($text)
    {
    }

    public function setEmailText($text)
    {
    }

    public function setPerson(Person $Person)
    {
        $this->person = $Person;
    }

    public function setProjectorText($text)
    {
        $this->projectorText = $text;
    }

    private function sendEmail()
    {
        $emailaddresses = [];
        foreach ($this->recipients as $recipient) {
            array_push($emailaddresses, $recipient->getEmail());
        }

        try {
            $email = new NotificationEmail($emailaddresses, $this->person->getFullName());
            $emailStatus = $email->send();

            return $emailStatus;
        } catch (\Exception $ex) {
            return false;
        }
    }

    private function sendSMS()
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

    public function send()
    {
        $methods = [];
        if (SystemConfig::hasValidMailServerSettings()) {
            $send = $this->sendEmail();
            array_push($methods, 'email: ' . $send);
        }
        if (SystemConfig::hasValidSMSServerSettings()) {
            $send = (bool) $this->sendSMS();
            array_push($methods, 'sms: ' . $send);
        }
        if (SystemConfig::hasValidOpenLPSettings()) {
            $send = (bool) $this->sendProjector();
            array_push($methods, 'projector: ' . $send);
        }
        $sendStatus = [
            'status'  => '',
            'methods' => $methods,
        ];

        return json_encode($sendStatus, JSON_THROW_ON_ERROR);
    }
}
