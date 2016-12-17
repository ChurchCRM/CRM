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

class FamilyVerificationEmail extends BaseEmail
{

  private $link;

  public function __construct($emails, $familyName, $token)
  {
    parent::__construct($emails);
    $this->link = SystemURLs::getURL(0) . "external/verify/" . $token;
    $this->mail->Subject = gettext($familyName . ": " . gettext("Please verify your family's information"));
    $this->mail->isHTML(true);
    $this->mail->msgHTML($this->buildMessage());
  }

  private function buildMessage() {

  }

}
