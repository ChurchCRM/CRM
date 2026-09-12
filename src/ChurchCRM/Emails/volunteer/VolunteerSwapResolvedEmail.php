<?php

namespace ChurchCRM\Emails\volunteer;

/**
 * Outbox type `swap_resolved` — the coordinator decided (design §2.13, §3.6,
 * Appendix C).
 *
 * Recipients: BOTH the proposer and the proposed substitute, because the
 * outcome changes what each of them is expected to do. Reply-To: the
 * responsible coordinator, set by the drain. Appendix C requires both names and
 * the decision to appear in the body.
 */
class VolunteerSwapResolvedEmail extends BaseVolunteerEmail
{
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    private string $proposerName;

    private string $substituteName;

    private string $decision;

    /**
     * @param string[] $toAddresses
     * @param string $decision self::DECISION_APPROVED or self::DECISION_REJECTED
     */
    public function __construct(
        array $toAddresses,
        string $recipientName,
        VolunteerEmailContext $context,
        string $proposerName,
        string $substituteName,
        string $decision
    ) {
        $this->proposerName = $proposerName;
        $this->substituteName = $substituteName;
        $this->decision = $decision;

        parent::__construct($toAddresses, $recipientName, $context);
    }

    private function isApproved(): bool
    {
        return $this->decision === self::DECISION_APPROVED;
    }

    protected function getSubSubject(): string
    {
        return $this->isApproved()
            ? gettext('Your substitute request was approved')
            : gettext('Your substitute request was not approved');
    }

    protected function buildMessageBody(): string
    {
        if ($this->isApproved()) {
            return $this->composeBody(
                sprintf(
                    gettext('The request for %1$s to serve in place of %2$s has been approved.'),
                    $this->substituteName,
                    $this->proposerName
                ),
                gettext('The schedule has been updated, so there is nothing further to do.')
            );
        }

        return $this->composeBody(
            sprintf(
                gettext('The request for %1$s to serve in place of %2$s was not approved.'),
                $this->substituteName,
                $this->proposerName
            ),
            sprintf(
                gettext('%s is still expected to serve. Please contact the coordinator if that is not possible.'),
                $this->proposerName
            )
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
