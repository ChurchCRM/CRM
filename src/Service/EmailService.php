<?php

namespace ChurchCRM\Service;

class EmailService
{

  private $SMTPHost;
  private $SMTPUser;
  private $SMTPPass;
  private $ChurchName;
  private $ChurchEmail;

  public function __construct()
  {
    // Read in report settings from database
    $rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_name like 'sSMTP%' or cfg_name like 'sChurch%'");
    if ($rsConfig) {
      while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
        $$cfg_name = $cfg_value;
      }
    }


    $this->SMTPHost = $sSMTPHost;
    $this->SMTPAuth = sSMTPAuth;
    $this->SMTPUser = $sSMTPUser;
    $this->SMTPPass = $sSMTPPass;
    $this->ChurchName = $sChurchName;
    $this->ChurchEmail = $sChurchEmail;
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
