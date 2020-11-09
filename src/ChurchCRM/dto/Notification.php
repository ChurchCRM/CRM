<?php

namespace ChurchCRM\dto;


use ChurchCRM\Emails\NotificationEmail;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;

class Notification
{

  protected $projectorText;
  protected $recipients;
  protected $person;

  public function __construct()
  {

  }

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

  public function setPerson(\ChurchCRM\Person $Person)
  {
    $this->person  = $Person;
  }

  public function setProjectorText($text)
  {
    $this->projectorText=$text;
  }

  private function sendEmail()
  {
    $emailaddresses = [];
    Foreach ($this->recipients as $recipient)
    {
      array_push($emailaddresses,$recipient->getEmail());
    }
    try
    {
      $email = new NotificationEmail($emailaddresses,$this->person->getFullName());
      $emailStatus=$email->send();
      return $emailStatus;
    } catch (Exception $ex) {
      return false;
    }

  }

  private function sendSMS()
  {
    try
      {

        $client = new Client(new Basic(SystemConfig::getValue("sNexmoAPIKey"),SystemConfig::getValue("sNexmoAPISecret")));

        Foreach ($this->recipients as $recipient)
        {
          $message = $client->message()->sendText([
              'to' => $recipient->getNumericCellPhone(),
              'from' => SystemConfig::getValue("sNexmoFromNumber"),
              'text' => gettext('Notification for') . " " . $this->person->getFullName()
          ]);
        }
        return $message;
      } catch (Exception $ex) {
        return false;
      }

  }

  private function sendProjector()
  {
    try
      {
        $OLPAlert = new OpenLPNotification(SystemConfig::getValue("sOLPURL"),
                SystemConfig::getValue("sOLPUserName"),
                SystemConfig::getValue("sOLPPassword"));
        $OLPAlert->setAlertText($this->projectorText);
        return $OLPAlert->send();
      } catch (Exception $ex) {
        return false;
      }

  }

  public function send()
  {

    $methods = [];
    if(SystemConfig::hasValidMailServerSettings())
    {
      $send = $this->sendEmail();
      array_push($methods,"email: ".$send);
    }
    if (SystemConfig::hasValidSMSServerSettings())
    {
      $send = (boolean)$this->sendSMS();
      array_push($methods,"sms: ".$send);
    }
    if(SystemConfig::hasValidOpenLPSettings())
    {
      $send = (boolean)($this->sendProjector());
      array_push($methods,"projector: ".$send);
    }
    $sendStatus = [
        "status"=>"",
        "methods"=>$methods
    ];

    return json_encode($sendStatus);

  }

}
