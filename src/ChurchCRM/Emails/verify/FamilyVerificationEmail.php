<?php

namespace ChurchCRM\Emails\verify;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\BaseEmail;
use ChurchCRM\model\ChurchCRM\Token;

class FamilyVerificationEmail extends BaseEmail
{
    private ?Token $token;
    protected string $familyName;

    /**
     * @param string[] $emails
     */
    public function __construct(array $emails, string $familyName, ?Token $token = null)
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
        $myTokens = [
            'toName' => $this->familyName . ' ' . gettext('Family'),
            'body'   => SystemConfig::getValue('sConfirm1'),
        ];

        return array_merge($this->getCommonTokens(), $myTokens);
    }

    protected function getFullURL(): string
    {
        if ($this->token) {
            return SystemURLs::getURL() . '/external/verify/' . $this->token->getToken();
        }

        return '';
    }

    protected function getButtonText(): string
    {
        return gettext('Verify');
    }
}
