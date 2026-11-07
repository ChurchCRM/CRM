<?php

namespace ChurchCRM\Emails\verify;

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
    public function __construct(array $emails, string $familyName, ?Token $token = null, bool $updated = false)
    {
        parent::__construct($emails);
        $this->familyName = $familyName;
        $this->token = $token;
        $this->mail->Subject = $familyName . ': ' . gettext("Please verify your family's information") . ($updated ? ' ** Updated **' : '');
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    public function getTokens(): array
    {
        $myTokens = [
            'toName' => $this->familyName . ' ' . gettext('Family'),
            'body'   => gettext('Please use the link below to review your family information. On the review page, select "Confirm Family Info" to confirm that everything is correct or send corrections to the church office.'),
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
        return gettext('Review Family Information');
    }

    protected function getPreheader(): string
    {
        return gettext("Please verify your family's information");
    }

    protected function getLogKind(): string
    {
        return 'verify';
    }
}
