<?php

namespace ChurchCRM\Volunteer\Email;

/**
 * Outbox type `signup_confirm` — the volunteer is on the schedule and has already
 * said yes (design Appendix C).
 *
 * Enqueued by `selfSignup()`, which #9712 exposes, and by an assignment a
 * coordinator recorded as accepted in the Assign dialog (#10528). The second
 * names who recorded it, because the volunteer did not sign themselves up.
 */
class VolunteerSignupConfirmEmail extends BaseVolunteerEmail
{
    private ?string $recordedByName;

    /**
     * @param string[] $toAddresses
     */
    public function __construct(
        array $toAddresses,
        string $recipientName,
        VolunteerEmailContext $context,
        ?string $recordedByName = null
    ) {
        $this->recordedByName = $recordedByName;

        parent::__construct($toAddresses, $recipientName, $context);
    }

    protected function getSubSubject(): string
    {
        return $this->recordedByName === null
            ? gettext('Thank you for signing up to serve')
            : gettext('You are on the schedule');
    }

    protected function buildMessageBody(): string
    {
        return $this->composeBody(
            $this->recordedByName === null
                ? gettext('Thank you for signing up to serve. You are on the schedule.')
                : sprintf(gettext('%s recorded that you will serve. You are on the schedule.'), $this->recordedByName),
            gettext('You can review or change this on your volunteer schedule.')
        );
    }

    protected function getFullURL(): string
    {
        return VolunteerEmailContext::getMyScheduleURL();
    }

    protected function getButtonText(): string
    {
        return gettext('View my schedule');
    }
}
