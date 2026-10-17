<?php

namespace ChurchCRM\Volunteer\Email;

/**
 * Outbox type `gap_alert`, the daily batch (#10529, design §3.6, Appendix C): every
 * position still short on the occurrences of the coming lead + 24 hours that this
 * coordinator has not been told about yet, in one message.
 *
 * One `gap_alert` row per position stands behind it; the drain groups a recipient's
 * batched rows and drops the positions filled since they were queued. Like the single
 * alert it names no volunteer, so it carries no Reply-To.
 */
class VolunteerGapAlertBatchEmail extends BaseVolunteerEmail
{
    /** @var array<int, array{context: VolunteerEmailContext, positions: array<string, int>}> */
    private array $occurrences;

    private bool $portalLinks;

    /**
     * @param string[] $toAddresses
     * @param array<int, array{context: VolunteerEmailContext, positions: array<string, int>}> $occurrences
     *        in date order, each with position name → how many more are needed
     */
    public function __construct(array $toAddresses, string $recipientName, array $occurrences, bool $portalLinks)
    {
        $this->occurrences = $occurrences;
        $this->portalLinks = $portalLinks;

        parent::__construct($toAddresses, $recipientName, $occurrences[0]['context']);
    }

    protected function getSubSubject(): string
    {
        return gettext('Volunteer positions to fill in the coming days');
    }

    protected function buildMessageBody(): string
    {
        $total = 0;
        foreach ($this->occurrences as $occurrence) {
            $total += array_sum($occurrence['positions']);
        }

        $paragraphs = [sprintf(gettext('Volunteers still needed for these upcoming occurrences: %d'), $total)];

        // One paragraph per occurrence: when, whose, which positions and the link, as
        // prose, because the shared template has one body slot (Appendix C).
        foreach ($this->occurrences as $occurrence) {
            $context = $occurrence['context'];
            $lines = [];
            if ($context->getWhen() !== '') {
                $lines[] = $context->getWhen();
            }
            $team = $context->getTeamName();
            $lines[] = $team === null || $team === ''
                ? $context->getMinistryName()
                : $context->getMinistryName() . ' / ' . $team;
            foreach ($occurrence['positions'] as $positionName => $needed) {
                $lines[] = sprintf(gettext('%1$s — still needed: %2$d'), $positionName, $needed);
            }
            if ($context->getOccurrenceURL() !== '') {
                $lines[] = $context->getOccurrenceURL();
            }
            $paragraphs[] = implode("\n", $lines);
        }

        $paragraphs[] = gettext('Open each occurrence to assign the people you need.');

        return implode("\n\n", $paragraphs);
    }

    protected function getFullURL(): string
    {
        return $this->portalLinks
            ? VolunteerEmailContext::getMyTeamsURL()
            : VolunteerEmailContext::getDashboardURL();
    }

    protected function getButtonText(): string
    {
        return $this->portalLinks ? gettext('My Teams') : gettext('Open the Ministry Dashboard');
    }
}
