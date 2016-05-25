<?php
require_once dirname(dirname(__FILE__)) . "/Include/phpmailer/class.phpmailer.php";


class EmailService
{

  private $SMTPHost;
  private $SMTPPort;
  private $SMTPUser;
  private $SMTPPass;
  private $ChurchName;
  private $ChurchEmail;

  public function __construct() {
    // Read in report settings from database
    $rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_name like 'sSMTP%' or cfg_name like 'sChurch%'");
    if ($rsConfig) {
      while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
        $$cfg_name = $cfg_value;
      }
    }


    $this->SMTPHost = $sSMTPHost;
 //   $this->SMTPPort = $sSMTPPort;
    $this->SMTPUser = $sSMTPUser;
    $this->SMTPPass = $sSMTPPass;
    $this->ChurchName = $sChurchName;
    $this->ChurchEmail = $sChurchEmail;
  }

  function getConnection() {

    $mail = new PHPMailer();
    $mail->IsSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = $this->SMTPHost;
    $mail->Port = $this->SMTPPort;
    $mail->Username = $this->SMTPUser;
    $mail->Password = $this->SMTPPass;

    // $mail->SMTPDebug  = 2;

    return $mail;
  }

  function sentRegistration($message) {
    $mail = $this->getConnection();
    $mail->SetFrom($this->ChurchEmail, $this->ChurchName);

    $mail->Subject = "ChurchCRM Registration - ". $this->ChurchName;
    $mail->MsgHTML($message);
    $mail->isHTML(false);
    $mail->AddAddress("info@churchcrm.io");
    $mail->Send();
  }

}
