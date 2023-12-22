<?php

namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class FamilyVerificationEmail extends BaseEmail
{
    private $token;
    protected $familyName;

    public function __construct($emails, $familyName, $token = '')
    {
        parent::__construct($emails);
        $this->familyName = $familyName;
        $this->token = $token;
        $this->mail->Subject = $familyName . ': ' . gettext("Please verify your family's information");
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    public function getTokens(): array
    {
        $myTokens = ['toName' => $this->familyName . ' ' . gettext('Family'),
            'body'            => SystemConfig::getValue('sConfirm1'),
        ];

        return array_merge($this->getCommonTokens(), $myTokens);
    }

    protected function getFullURL(): string
    {
        return SystemURLs::getURL() . '/external/verify/' . $this->token->getToken();
    }

    protected function getButtonText(): string
    {
        return gettext('Verify');
    }
}
