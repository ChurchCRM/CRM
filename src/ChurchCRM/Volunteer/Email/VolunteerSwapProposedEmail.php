<?php

namespace ChurchCRM\Volunteer\Email;

/**
 * Outbox type `swap_proposed` — a volunteer has found their own replacement and
 * wants it approved (design §2.13, §3.6, Appendix C).
 *
 * Recipient: every coordinator in scope. Reply-To: the volunteer who proposed
 * it. CTA: the coordinator dashboard, where the substitution queue lives.
 */
class VolunteerSwapProposedEmail extends BaseVolunteerEmail
{
    private string $proposerName;

    private string $substituteName;

    /**
     * @param string[] $toAddresses
     */
    public function __construct(
        array $toAddresses,
        string $recipientName,
        VolunteerEmailContext $context,
        string $proposerName,
        string $substituteName
    ) {
        $this->proposerName = $proposerName;
        $this->substituteName = $substituteName;

        parent::__construct($toAddresses, $recipientName, $context);
    }

    protected function getSubSubject(): string
    {
        return gettext('A volunteer has proposed a substitute');
    }

    protected function buildMessageBody(): string
    {
        $opening = sprintf(
            gettext('%1$s cannot serve and has proposed %2$s as a substitute.'),
            $this->proposerName,
            $this->substituteName
        );

        return $this->composeBody(
            $opening,
            gettext('Review the request on the volunteer dashboard and approve or reject it.')
        );
    }

    protected function getFullURL(): string
    {
        return VolunteerEmailContext::getDashboardURL();
    }

    protected function getButtonText(): string
    {
        return gettext('Review this request');
    }
}
