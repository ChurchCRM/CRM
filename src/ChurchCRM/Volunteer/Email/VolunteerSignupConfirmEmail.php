<?php

namespace ChurchCRM\Volunteer\Email;

/**
 * Outbox type `signup_confirm`: the volunteer is on the schedule and has already said yes.
 * Sent after a self-signup, or after a coordinator recorded the acceptance, in which case it
 * names who did.
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
