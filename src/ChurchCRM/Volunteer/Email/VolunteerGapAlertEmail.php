<?php

namespace ChurchCRM\Volunteer\Email;

/**
 * Outbox type `gap_alert` — this occurrence is short (design §3.6, Appendix C).
 *
 * Occurrence-scoped, not assignment-scoped: `vntf_vasg_ID` is null, so there is
 * no single volunteer this is about and therefore **no Reply-To at all**. A
 * decline's alert lists every short position, and its dedupe key carries the
 * date, so a ministry that loses three people in one afternoon gets one alert per
 * coordinator, not three. The unfilled-position alert (#10372) lists only its own
 * position.
 */
class VolunteerGapAlertEmail extends BaseVolunteerEmail
{
    /** @var array<string, int> position name → how many more are needed */
    private array $shortPositions;

    /**
     * @param string[] $toAddresses
     * @param array<string, int> $shortPositions
     */
    public function __construct(
        array $toAddresses,
        string $recipientName,
        VolunteerEmailContext $context,
        array $shortPositions
    ) {
        $this->shortPositions = $shortPositions;

        parent::__construct($toAddresses, $recipientName, $context);
    }

    protected function getSubSubject(): string
    {
        return gettext('Volunteer positions still need to be filled');
    }

    protected function buildMessageBody(): string
    {
        $opening = sprintf(gettext('Volunteers still needed for this occurrence: %d'), $this->totalNeeded());

        if ($this->shortPositions === []) {
            return $opening . "\n\n" . $this->context->describe();
        }

        // Which positions, and how short each one is, on their own lines — the
        // shared template has one body slot, so the list is prose (Appendix C).
        $lines = [gettext('Positions still short') . ':'];
        foreach ($this->shortPositions as $positionName => $needed) {
            $lines[] = sprintf('%s — %d', $positionName, $needed);
        }

        return $this->composeBody($opening, implode("\n", $lines));
    }

    private function totalNeeded(): int
    {
        return array_sum($this->shortPositions);
    }

    protected function getFullURL(): string
    {
        return $this->context->getOccurrenceURL();
    }

    protected function getButtonText(): string
    {
        return $this->totalNeeded() > 0 ? gettext('Fill this gap') : gettext('View Assignments');
    }
}
