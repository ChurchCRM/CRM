<?php

namespace ChurchCRM\Emails\volunteer;

/**
 * Outbox type `signup_confirm` — the volunteer signed themselves up (design
 * Appendix C, UC1's self-service half).
 *
 * Enqueued by `selfSignup()`, which #9712 exposes; the mail exists here so that
 * issue adds a caller rather than a message type.
 */
class VolunteerSignupConfirmEmail extends BaseVolunteerEmail
{
    protected function getSubSubject(): string
    {
        return gettext('Thank you for signing up to serve');
    }

    protected function buildMessageBody(): string
    {
        return $this->composeBody(
            gettext('Thank you for signing up to serve. You are on the schedule.'),
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
