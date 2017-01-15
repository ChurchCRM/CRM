<?php
namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;

abstract class BaseFamilyVerification extends BaseEmail
{

  protected $familyName;

  public function __construct($emails, $familyName)
  {
    parent::__construct($emails);
    $this->familyName = $familyName;
    $this->mail->Subject = gettext($familyName . ": " . gettext("Please verify your family's information"));
    $this->mail->isHTML(true);
    $this->mail->msgHTML($this->buildMessage());
  }

  protected function buildMessage(){
      $msg = array();
      array_push($msg, $this->buildMessageHeader());
      array_push($msg, $this->buildMessageBody());
      array_push($msg, $this->buildMessageFooter());
      return implode("<p/>", $msg);
  }

  protected function buildMessageHeader()
  {
    return gettext("Dear") ." " . $this->familyName . " " . gettext("Family");
  }

  protected function buildMessageFooter()
  {
    return SystemConfig::getValue('sConfirmSincerely') . ",<br/>" . SystemConfig::getValue("sConfirmSigner");
  }

  protected abstract function buildMessageBody();

}
