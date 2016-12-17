<?php
namespace ChurchCRM\Emails;
use ChurchCRM\dto\SystemURLs;

class FamilyVerificationEmail extends BaseFamilyVerification
{

  private $link;

  public function __construct($emails, $familyName, $token)
  {
    $this->link = SystemURLs::getURL() . "external/verify/" . $token;
    parent::__construct($emails, $familyName);
  }

  protected function buildMessageBody() {
    return "<a href='".$this->link."'>".$this->link."</a>";
  }

}
