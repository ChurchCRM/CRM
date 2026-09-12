<?php

namespace ChurchCRM\Emails\volunteer;

/**
 * Outbox type `decline_alert` — a volunteer said no and a gap opened (design
 * §3.6, Appendix C).
 *
 * Recipient: every coordinator in scope. Reply-To: the volunteer who declined,
 * so "can you do the following week instead?" is one keystroke away. Only a
 * decline the VOLUNTEER made produces this — one the coordinator recorded
 * enqueues nothing, because they already know.
 */
class VolunteerDeclineAlertEmail extends BaseVolunteerEmail
{
    private string $volunteerName;

    private int $stillNeeded;

    /**
     * @param string[] $toAddresses
     */
    public function __construct(
        array $toAddresses,
        string $recipientName,
        VolunteerEmailContext $context,
        string $volunteerName,
        int $stillNeeded
    ) {
        $this->volunteerName = $volunteerName;
        $this->stillNeeded = $stillNeeded;

        parent::__construct($toAddresses, $recipientName, $context);
    }

    protected function getSubSubject(): string
    {
        return gettext('A volunteer has declined an assignment');
    }

    protected function buildMessageBody(): string
    {
        $opening = sprintf(
            gettext('%s has declined a volunteer assignment.'),
            $this->volunteerName
        );

        // Appendix C: a gap or decline alert carries the number still needed.
        $nextStep = $this->stillNeeded > 0
            ? sprintf(
                ngettext(
                    '%d more volunteer is still needed for this position. Open the occurrence to fill the gap.',
                    '%d more volunteers are still needed for this position. Open the occurrence to fill the gap.',
                    $this->stillNeeded
                ),
                $this->stillNeeded
            )
            : gettext('The position is still covered, so no action is needed unless you want to adjust the roster.');

        return $this->composeBody($opening, $nextStep);
    }

    protected function getFullURL(): string
    {
        return $this->context->getOccurrenceURL();
    }

    protected function getButtonText(): string
    {
        return gettext('Fill this gap');
    }
}
