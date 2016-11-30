<?php

namespace ChurchCRM\Service;
use ChurchCRM\dto\SystemConfig;

class EmailService
{

  private $ChurchName;
  private $ChurchEmail;

  public function __construct()
  {
    $this->ChurchName = SystemConfig::getValue("sChurchName");
    $this->ChurchEmail = SystemConfig::getValue("sChurchEmail");
  }

  function getConnection()
  {

    $mail = new \PHPMailer();
    $mail->IsSMTP();
    $mail->CharSet = 'UTF-8';
    $mail->Host = SystemConfig::getValue("sSMTPHost");
    if (SystemConfig::getBooleanValue("sSMTPAuth")) {
      $mail->SMTPAuth = true;
      $mail->Username = SystemConfig::getValue("sSMTPUser");
      $mail->Password = SystemConfig::getValue("sSMTPPass");
    }

    //$mail->SMTPDebug = 2;

    return $mail;
  }

  function sendRegistration($message)
  {
    $mail = $this->getConnection();
    $mail->setFrom($this->ChurchEmail, $this->ChurchName);

    $mail->Subject = "ChurchCRM Registration - " . $this->ChurchName;
    $mail->msgHTML($message);
    $mail->isHTML(false);
    $mail->addAddress("info@churchcrm.io");
    if (!$mail->send()) {
      throw new \Exception($mail->ErrorInfo);
    }

  }

}
