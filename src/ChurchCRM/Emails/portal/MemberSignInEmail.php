<?php

namespace ChurchCRM\Emails\portal;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\BaseEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeInterface;

/**
 * The member sign-in email (member sign-in design §6.1): one code and one link,
 * sent only to the person's own address. The wording is fixed; nothing a
 * visitor types appears in it. Its body is never kept in the email history.
 */
class MemberSignInEmail extends BaseEmail
{
    public function __construct(
        private readonly Person $person,
        private readonly string $code,
        private readonly string $token,
        private readonly DateTimeInterface $expiresAt,
    ) {
        parent::__construct([trim((string) $person->getEmail())]);
        $this->setLogContext((int) $person->getId());
        $this->mail->Subject = ChurchMetaData::getChurchName() . ': ' . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject(): string
    {
        return gettext('Your Member Portal sign-in code');
    }

    protected function getTemplateName(): string
    {
        return 'MemberSignInEmail.html.twig';
    }

    public function getTokens(): array
    {
        $body = gettext('Here is your code to sign in to the Member Portal. Type it on the sign-in page, in the browser where you asked for it. Or press the button to sign in on this device.')
            . "\n\n"
            . sprintf(gettext('The code and the button work once, until %s.'), DateTimeUtils::formatDate($this->expiresAt, true));

        return array_merge($this->getCommonTokens(), [
            'toName' => (string) $this->person->getFirstName(),
            'body' => $body,
            'codeLabel' => gettext('Your code'),
            'code' => $this->code,
        ]);
    }

    protected function getFullURL(): string
    {
        return SystemURLs::getURL() . '/portal/login/confirm/' . $this->token;
    }

    protected function getButtonText(): string
    {
        return gettext('Sign in');
    }

    protected function getPreheader(): string
    {
        return $this->getSubSubject();
    }

    protected function getLogKind(): string
    {
        return 'portal.sign-in';
    }
}
