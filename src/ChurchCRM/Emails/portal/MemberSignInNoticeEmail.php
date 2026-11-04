<?php

namespace ChurchCRM\Emails\portal;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\BaseEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\Auth\SignInReason;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeInterface;

/**
 * Tells the office list that someone could not sign in to the Member Portal
 * (member sign-in design §6.1 step 3, A6). Staff read it, so it carries the
 * typed address and the reason in plain words; the person who typed the
 * address is never told either.
 */
class MemberSignInNoticeEmail extends BaseEmail
{
    /**
     * @param string[] $toAddresses
     * @param Person[] $matchedPeople everyone whose personal email is the typed address
     */
    public function __construct(
        array $toAddresses,
        private readonly string $typedEmail,
        private readonly SignInReason $reason,
        private readonly array $matchedPeople,
        private readonly DateTimeInterface $when,
    ) {
        parent::__construct($toAddresses);
        $this->mail->Subject = ChurchMetaData::getChurchName() . ': ' . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected function getSubSubject(): string
    {
        return gettext('Someone could not sign in to the Member Portal');
    }

    public function getTokens(): array
    {
        $lines = [
            gettext('Someone asked for a Member Portal sign-in email, but none was sent.'),
            '',
            sprintf(gettext('Email address typed: %s'), $this->typedEmail),
            sprintf(gettext('Reason: %s'), $this->reason->label()),
        ];
        if ($this->matchedPeople !== []) {
            $names = array_map(static fn (Person $person): string => $person->getFullName(), $this->matchedPeople);
            $lines[] = sprintf(gettext('People with this email: %s'), implode(', ', $names));
        }
        $lines[] = sprintf(gettext('Time: %s'), DateTimeUtils::formatDate($this->when, true));
        $lines[] = '';
        $lines[] = gettext('More attempts with this address in the next hour will not send another notice.');

        return array_merge($this->getCommonTokens(), [
            'toName' => gettext('Church Office'),
            'body' => implode("\n", $lines),
        ]);
    }

    protected function getFullURL(): string
    {
        return count($this->matchedPeople) === 1
            ? SystemURLs::getURL() . '/people/view/' . (int) $this->matchedPeople[0]->getId()
            : '';
    }

    protected function getButtonText(): string
    {
        return count($this->matchedPeople) === 1 ? gettext('View Person Page') : '';
    }

    protected function getPreheader(): string
    {
        return $this->getSubSubject();
    }

    protected function getLogKind(): string
    {
        return 'notification.portal-sign-in';
    }
}
