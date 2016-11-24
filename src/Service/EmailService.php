<?php

namespace ChurchCRM\Service;
use ChurchCRM\dto\SystemConfig;

class EmailService
{

  private $SMTPHost;
  private $SMTPUser;
  private $SMTPPass;
  private $ChurchName;
  private $ChurchEmail;

  public function __construct()
  {
    $this->SMTPHost = SystemConfig::getValue("sSMTPHost");
    $this->SMTPAuth = SystemConfig::getValue("sSMTPAuth");
    $this->SMTPUser = SystemConfig::getValue("sSMTPUser");
    $this->SMTPPass = SystemConfig::getValue("sSMTPPass");
    $this->ChurchName = SystemConfig::getValue("sChurchName");
    $this->ChurchEmail = SystemConfig::getValue("sChurchEmail");
  }

  function getConnection()
  {

    $mail = new \PHPMailer();
    $mail->IsSMTP();
    $mail->CharSet = 'UTF-8';
    $mail->Host = $this->SMTPHost;
    if ($this->SMTPAuth == 1) {
      $mail->SMTPAuth = true;
      $mail->Username = $this->SMTPUser;
      $mail->Password = $this->SMTPPass;
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
