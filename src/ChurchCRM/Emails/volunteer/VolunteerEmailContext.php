<?php

namespace ChurchCRM\Emails\volunteer;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\DateTimeUtils;

/**
 * The "enough context to act" block every Volunteer v2 message carries (#9710,
 * design Appendix C): ministry and team, the position, the church wall-clock
 * date and time, and the location when the linked event has one.
 *
 * A value object, deliberately not an email base class. Appendix C requires the
 * seven subclasses to stay thin and reuse `BaseEmail` + the single Twig
 * template; without something like this each of them would carry its own copy
 * of the same five labelled lines and its own date formatting, which is exactly
 * how one message ends up printing a raw database string while the others do
 * not. The drain (`VolunteerNotificationService`) builds one of these per
 * outbox row and hands it to the subclass, so nothing here touches the ORM,
 * PHPMailer or the outbox.
 *
 * **Times are church wall-clock.** `$start` / `$end` come from
 * `VolunteerScheduleService::resolveOccurrenceWindow()`, i.e. wall-clock in
 * `sTimeZone` like every other V2 timestamp (timezone-handling.md), and are
 * rendered through `DateTimeUtils::formatDate($d, true)` so the site's
 * configured long-date format and a 12-hour clock are used — never a raw DB
 * string and never UTC.
 */
final class VolunteerEmailContext
{
    public function __construct(
        private readonly string $ministryName,
        private readonly ?string $teamName = null,
        private readonly ?string $positionName = null,
        private readonly ?\DateTimeInterface $start = null,
        private readonly ?\DateTimeInterface $end = null,
        private readonly ?string $locationName = null,
        private readonly ?int $occurrenceId = null
    ) {
    }

    public function getMinistryName(): string
    {
        return $this->ministryName;
    }

    public function getTeamName(): ?string
    {
        return $this->teamName;
    }

    public function getPositionName(): ?string
    {
        return $this->positionName;
    }

    public function getOccurrenceId(): ?int
    {
        return $this->occurrenceId;
    }

    /** The occurrence's start in church wall-clock, or '' when it has none. */
    public function getWhen(): string
    {
        return $this->start === null ? '' : DateTimeUtils::formatDate($this->start, true);
    }

    /** Has the occurrence already finished? Used by the drain, not by the templates. */
    public function hasEnded(): bool
    {
        return $this->end !== null && $this->end < DateTimeUtils::getToday();
    }

    /**
     * The labelled context lines, one per line, ready to drop into the single
     * `{{body|nl2br}}` slot the shared template gives us.
     *
     * Colons sit outside `gettext()` (code-standards.md): they are UI
     * punctuation, not content for a translator to reproduce.
     */
    public function describe(): string
    {
        $lines = [gettext('Ministry') . ': ' . $this->ministryName];

        if ($this->teamName !== null && $this->teamName !== '') {
            $lines[] = gettext('Team') . ': ' . $this->teamName;
        }

        if ($this->positionName !== null && $this->positionName !== '') {
            $lines[] = gettext('Position') . ': ' . $this->positionName;
        }

        $when = $this->getWhen();
        if ($when !== '') {
            $lines[] = gettext('When') . ': ' . $when;
        }

        if ($this->locationName !== null && $this->locationName !== '') {
            $lines[] = gettext('Where') . ': ' . $this->locationName;
        }

        return implode("\n", $lines);
    }

    /** Absolute link to this occurrence's staffing view, for the coordinator alerts. */
    public function getOccurrenceURL(): string
    {
        if ($this->occurrenceId === null) {
            return '';
        }

        return SystemURLs::getURL() . '/ministries/occurrences/' . $this->occurrenceId;
    }

    /**
     * Absolute link to the volunteer's own schedule, for the volunteer-facing mail.
     *
     * The Member Portal (#9867): a volunteer's schedule lives at
     * `/portal/volunteer/schedule`, never in the admin shell. The old
     * `/volunteer/my-schedule` still redirects here, so mail already in someone's
     * inbox keeps working, but new mail links straight at the page.
     */
    public static function getMyScheduleURL(): string
    {
        return SystemURLs::getURL() . '/portal/volunteer/schedule';
    }

    /** Absolute link to the coordinator dashboard, where swap requests are reviewed. */
    public static function getDashboardURL(): string
    {
        return SystemURLs::getURL() . '/ministries/dashboard';
    }
}
