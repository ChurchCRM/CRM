<?php
/**
 * Created by PhpStorm.
 * User: georg
 * Date: 12/17/2016
 * Time: 9:27 AM
 */

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class FamilyVerificationPDFEmail extends BaseEmail
{

  public function __construct($emails, $familyName)
  {
    parent::__construct($emails);
    $this->mail->Subject = gettext($familyName . ": " . gettext("Please verify your family's information"));
    $this->mail->isHTML(true);
    $this->mail->msgHTML("Dear " . $familyName . " Family <p>" . SystemConfig::getValue("sConfirm1") . "</p>Sincerely, <br/>" . SystemConfig::getValue("sConfirmSigner"));
  }
}
