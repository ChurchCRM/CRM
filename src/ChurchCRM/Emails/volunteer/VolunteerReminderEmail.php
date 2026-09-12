<?php

namespace ChurchCRM\Emails\volunteer;

/**
 * Outbox type `reminder` — UC5, sent `iVolunteerReminderLeadHours` before the
 * occurrence starts (design Appendix B, §3.6).
 *
 * Scheduled by `VolunteerNotificationService::scheduleReminders()`; the drain
 * skips it outright if the occurrence has ended before anyone ran the job.
 */
class VolunteerReminderEmail extends BaseVolunteerEmail
{
    protected function getSubSubject(): string
    {
        return gettext('Reminder: you are scheduled to serve');
    }

    protected function buildMessageBody(): string
    {
        return $this->composeBody(
            gettext('This is a reminder that you are scheduled to serve soon.'),
            gettext('If you can no longer make it, please decline on your volunteer schedule as soon as you can so the position can be filled.')
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
