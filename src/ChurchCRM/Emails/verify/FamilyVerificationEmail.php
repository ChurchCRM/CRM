<?php

namespace ChurchCRM\Emails\verify;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\BaseEmail;

class FamilyVerificationEmail extends BaseEmail
{
    protected string $familyName;

    /**
     * @param string[] $emails
     */
    public function __construct(array $emails, string $familyName, bool $updated = false)
    {
        parent::__construct($emails);
        $this->familyName = $familyName;
        $this->mail->Subject = $familyName . ': ' . gettext("Please verify your family's information") . ($updated ? ' ** Updated **' : '');
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    public function getTokens(): array
    {
        $myTokens = [
            'toName' => $this->familyName . ' ' . gettext('Family'),
            'body'   => gettext('Sign in to the member portal with this email address to review your family information. Confirm that everything is correct, or update your details, from the My Family page.'),
        ];

        return array_merge($this->getCommonTokens(), $myTokens);
    }

    /**
     * The email always points at the member portal's My Family page, where the
     * family reviews and edits its details live (#11145). No bearer token and
     * no PDF snapshot: the link only works for a signed-in member, and what
     * they see there is current rather than stale on arrival.
     */
    protected function getFullURL(): string
    {
        return SystemURLs::getURL() . '/portal/family';
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
