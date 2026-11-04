<?php

namespace ChurchCRM\Emails\signin;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Emails\BaseEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeInterface;

/**
 * The link that makes an address a person's sign-in email (member sign-in
 * design §9), sent to that address: after the member changes their email in
 * the portal, or to confirm an unconfirmed address.
 */
final class EmailChangeConfirmEmail extends BaseEmail
{
    public function __construct(
        private readonly string $toAddress,
        private readonly Person $person,
        private readonly string $confirmUrl,
        private readonly DateTimeInterface $linkExpiresAt,
    ) {
        parent::__construct([$toAddress]);
        $this->setLogContext((int) $person->getId());
        $this->mail->Subject = ChurchMetaData::getChurchName() . ': ' . gettext('Confirm your email address');
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getTemplateName(): string
    {
        return 'AccountEmail.html.twig';
    }

    public function getTokens(): array
    {
        return array_merge($this->getCommonTokens(), [
            'toName' => (string) $this->person->getFirstName(),
            'body' => sprintf(
                gettext('Use the button below to confirm that %1$s is your email address at %2$s. The link works once, until %3$s.'),
                $this->toAddress,
                ChurchMetaData::getChurchName(),
                DateTimeUtils::formatDate($this->linkExpiresAt, true)
            ),
        ]);
    }

    protected function getFullURL(): string
    {
        return $this->confirmUrl;
    }

    protected function getButtonText(): string
    {
        return gettext('Confirm Email Address');
    }

    protected function getPreheader(): string
    {
        return gettext('Confirm your email address');
    }

    protected function getLogKind(): string
    {
        return 'signin.email-confirm';
    }
}
