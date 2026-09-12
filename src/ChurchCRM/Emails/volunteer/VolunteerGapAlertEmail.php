<?php

namespace ChurchCRM\Emails\volunteer;

/**
 * Outbox type `gap_alert` — this occurrence is short (design §3.6, Appendix C).
 *
 * Occurrence-scoped, not assignment-scoped: `vntf_vasg_ID` is null, so there is
 * no single volunteer this is about and therefore **no Reply-To at all**. The
 * dedupe key carries the date, so a ministry that loses three people in one
 * afternoon gets one alert per coordinator, not three.
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
        $total = array_sum($this->shortPositions);

        $opening = sprintf(
            ngettext(
                '%d volunteer is still needed for an upcoming occurrence.',
                '%d volunteers are still needed for an upcoming occurrence.',
                max(1, $total)
            ),
            $total
        );

        $body = $this->composeBody($opening, gettext('Open the occurrence to assign the people you need.'));

        if ($this->shortPositions === []) {
            return $body;
        }

        // Which positions, and how short each one is, on their own lines — the
        // shared template has one body slot, so the list is prose (Appendix C).
        $lines = [gettext('Positions still short') . ':'];
        foreach ($this->shortPositions as $positionName => $needed) {
            $lines[] = sprintf('%s — %d', $positionName, $needed);
        }

        return $body . "\n\n" . implode("\n", $lines);
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
