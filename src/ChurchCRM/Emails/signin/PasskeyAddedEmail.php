<?php

namespace ChurchCRM\Emails\signin;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\BaseEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeInterface;

/**
 * Tells a member that a passkey was added to their Member Portal sign-in
 * (member sign-in design §6.3), so a passkey they didn't add doesn't go
 * unnoticed.
 */
final class PasskeyAddedEmail extends BaseEmail
{
    public function __construct(
        private readonly Person $person,
        private readonly ?string $label,
        private readonly DateTimeInterface $addedAt,
    ) {
        parent::__construct([trim((string) $person->getEmail())]);
        $this->setLogContext((int) $person->getId());
        $this->mail->Subject = ChurchMetaData::getChurchName() . ': ' . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    private function getSubSubject(): string
    {
        return gettext('A passkey was added to your Member Portal sign-in');
    }

    public function getTokens(): array
    {
        $when = DateTimeUtils::formatDate($this->addedAt, true);
        $body = $this->label === null
            ? sprintf(gettext('A passkey was added to your Member Portal sign-in at %1$s on %2$s.'), ChurchMetaData::getChurchName(), $when)
            : sprintf(gettext('A passkey was added to your Member Portal sign-in at %1$s on %2$s, from %3$s.'), ChurchMetaData::getChurchName(), $when, $this->label);

        return array_merge($this->getCommonTokens(), [
            'toName' => (string) $this->person->getFirstName(),
            'body' => $body . "\n\n" . gettext("If you didn't add it, remove it on the Sign-in and devices page and contact the church office."),
        ]);
    }

    protected function getFullURL(): string
    {
        return SystemURLs::getURL() . '/portal/devices';
    }

    protected function getButtonText(): string
    {
        return gettext('Sign-in and devices');
    }

    protected function getPreheader(): string
    {
        return $this->getSubSubject();
    }

    protected function getLogKind(): string
    {
        return 'signin.passkey-added';
    }
}
