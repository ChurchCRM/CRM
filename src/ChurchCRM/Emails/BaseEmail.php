<?php
/**
 * Created by PhpStorm.
 * User: georg
 * Date: 12/17/2016
 * Time: 9:22 AM
 */

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;

class BaseEmail
{
  protected $ChurchName;
  /** @var \PHPMailer */
  protected $mail;

  public function __construct($emails)
  {
    $this->setConnection();
    $this->ChurchName = SystemConfig::getValue("sChurchName");
    $this->mail->setFrom(SystemConfig::getValue("sChurchEmail"), $this->ChurchEmail);
    foreach ($emails as $email) {
      $this->mail->addAddress($email);
    }
  }

  private function setConnection()
  {

    $this->mail = new \PHPMailer();
    $this->mail->IsSMTP();
    $this->mail->CharSet = 'UTF-8';
    $this->mail->Host = SystemConfig::getValue("sSMTPHost");
    if (SystemConfig::getBooleanValue("sSMTPAuth")) {
      $this->mail->SMTPAuth = true;
      $this->mail->Username = SystemConfig::getValue("sSMTPUser");
      $this->mail->Password = SystemConfig::getValue("sSMTPPass");
    }
    //$this->mail->SMTPDebug = 2;
  }

  public function send(){
   return $this->mail->send();
  }

  public function getError(){
    return $this->mail->ErrorInfo;
  }

  public function addStringAttachment($string,$filename) {
    $this->mail->addStringAttachment($string,$filename);
  }
}
