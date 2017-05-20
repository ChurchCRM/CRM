<?php

namespace ChurchCRM\dto;

use ChurchCRM\Person;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\NotificationEmail;
use Nexmo\Client;
use Nexmo\Client\Credentials\Basic as NexmoBasicCred;
use ChurchCRM\Service\OpenLPNotification;

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
    } catch (Exception $ex) {
      return false;
    }
    return true;
  }
  
  private function sendSMS()
  {
    try
      {
     
        $client = new Client(New NexmoBasicCred(SystemConfig::getValue("sNexmoAPIKey"),SystemConfig::getValue("sNexmoAPISecret")));
        
        Foreach ($this->recipients as $recipient)
        {
          $message = $client->message()->send([
              'to' => $recipient->getNumericCellPhone(),
              'from' => SystemConfig::getValue("sNexmoFromNumber"),
              'text' => 'Notification for ' . $this->person->getFullName()
          ]);
        }
        return true;
      } catch (Exception $ex) {
        return false;
      }
      
  }
  
  private function sendProjector()
  {
    return "would projecT";
    try
      {
        $OLPAlert = new OpenLPNotification(SystemConfig::getValue("sOLPURL"),
                SystemConfig::getValue("sOLPUserName"),
                SystemConfig::getValue("sOLPPassword"));
        $OLPAlert->setAlertText($this->projectorText);
        $OLPAlert->send();
      } catch (Exception $ex) {
        return false;
      }
      return true;
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
      $send = $this->sendSMS();
      array_push($methods,"sms: ".$send);
    }
    if(SystemConfig::hasValidOpenLPSerrings())
    {
      $send = $this->sendProjector();
      array_push($methods,"projector: ".$send);
    }
    $sendStatus = [
        "status"=>"",
        "methods"=>$methods
    ];
    
    return json_encode($sendStatus);
    
  }
  
}
