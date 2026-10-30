<?php

namespace ChurchCRM\Volunteer\Email;

/**
 * Outbox type `decline_alert` — a volunteer said no and a gap opened (design
 * §3.6, Appendix C).
 *
 * Recipient: every coordinator in scope. Reply-To: the volunteer who declined,
 * so "can you do the following week instead?" is one keystroke away. Only a
 * decline the VOLUNTEER made produces this — one the coordinator recorded
 * enqueues nothing, because they already know.
 *
 * Built at send time, so the gap count and the people who took the slot since are current.
 */
class VolunteerDeclineAlertEmail extends BaseVolunteerEmail
{
    private string $volunteerName;

    private int $stillNeeded;

    private ?string $reason;

    /** @var array<int, array{name: string, accepted: bool}> */
    private array $assignedSince;

    /**
     * @param string[]                                        $toAddresses
     * @param array<int, array{name: string, accepted: bool}> $assignedSince who took the slot after the decline
     */
    public function __construct(
        array $toAddresses,
        string $recipientName,
        VolunteerEmailContext $context,
        string $volunteerName,
        int $stillNeeded,
        ?string $reason = null,
        array $assignedSince = []
    ) {
        $this->volunteerName = $volunteerName;
        $this->stillNeeded = $stillNeeded;
        $this->reason = $reason === null || trim($reason) === '' ? null : trim($reason);
        $this->assignedSince = $assignedSince;

        parent::__construct($toAddresses, $recipientName, $context);
    }

    protected function getSubSubject(): string
    {
        return gettext('A volunteer has declined an assignment');
    }

    protected function buildMessageBody(): string
    {
        // Plain text: BaseEmail.html.twig escapes {{body}} before nl2br, so the names and the
        // reason go in raw; InputUtils::escapeHTML() here would escape them twice.
        $opening = sprintf(
            gettext('%s has declined a volunteer assignment.'),
            $this->volunteerName
        );
        if ($this->reason !== null) {
            $opening .= "\n\n" . sprintf(gettext('Their reason: %s'), $this->reason);
        }

        $nowAssigned = array_map(
            static fn (array $holder): string => sprintf(
                $holder['accepted']
                    ? gettext('The position is now assigned to %s, who has accepted it.')
                    : gettext('The position is now assigned to %s, who has not yet accepted it.'),
                $holder['name']
            ),
            $this->assignedSince
        );

        $replacementsAccepted = array_filter(
            $this->assignedSince,
            static fn (array $holder): bool => !$holder['accepted']
        ) === [];

        // Appendix C: a gap or decline alert carries the number still needed.
        $paragraphs = $nowAssigned === [] ? [] : [implode("\n", $nowAssigned)];
        if ($this->stillNeeded > 0) {
            $paragraphs[] = sprintf(gettext('Volunteers still needed for this position: %d'), $this->stillNeeded);
        } elseif ($replacementsAccepted) {
            $paragraphs[] = gettext('The position is still covered, so no action is needed unless you want to adjust the roster.');
        }

        return $this->composeBody($opening, implode("\n\n", $paragraphs));
    }

    protected function getFullURL(): string
    {
        return $this->context->getOccurrenceURL();
    }

    protected function getButtonText(): string
    {
        return $this->stillNeeded > 0 ? gettext('Fill this gap') : gettext('View Assignments');
    }
}
