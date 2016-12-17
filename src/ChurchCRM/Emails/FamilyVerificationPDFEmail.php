<?php
namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class FamilyVerificationPDFEmail extends BaseFamilyVerification
{

  protected function buildMessageBody() {
    return SystemConfig::getValue("sConfirm1");
  }

}
