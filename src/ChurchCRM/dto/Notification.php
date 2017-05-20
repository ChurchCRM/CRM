<?php

namespace ChurchCRM\dto;

use ChurchCRM\Person;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\NotificationEmail;

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
         Foreach ($NotificationRecipients as $recipient)
        {        
          $message = $client->message()->send([
              'to' => 'num',
              'from' => SystemConfig::getValue("sNexmoFromNumber"),
              'text' => 'Notification for ' . $this->getFullName()
          ]);
        }
        
      } catch (Exception $ex) {

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
        $OLPAlert->send();
      } catch (Exception $ex) {

      }
  }
  
  public function send()
  {
    $sendStatus = [
        "status"=>"",
        "methods"=>[]
    ];
    if(SystemConfig::hasValidMailServerSettings())
    {
      array_push($sendStatus->methods,"email: ".$this->sendEmail());
    }
    if (SystemConfig::hasValidSMSServerSettings())
    {
      $this->sendSMS();
    }
    if(SystemConfig::hasValidOpenLPSerrings())
    {
      $this->sendProjector();
    }
    
    return json_encode($sendStatus);
    
  }
  
}
