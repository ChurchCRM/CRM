<?php

namespace ChurchCRM\Emails\volunteer;

/**
 * Outbox type `assignment` — "you have been scheduled" (design Appendix C).
 *
 * Recipient: the volunteer. Reply-To: the responsible coordinator, set by the
 * drain. CTA: the volunteer's own schedule, which is where accepting and
 * declining happen.
 */
class VolunteerAssignmentEmail extends BaseVolunteerEmail
{
    protected function getSubSubject(): string
    {
        return gettext('You have been scheduled to serve');
    }

    protected function buildMessageBody(): string
    {
        return $this->composeBody(
            gettext('You have been scheduled to serve.'),
            gettext('Please let us know whether you can make it by accepting or declining on your volunteer schedule.')
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
