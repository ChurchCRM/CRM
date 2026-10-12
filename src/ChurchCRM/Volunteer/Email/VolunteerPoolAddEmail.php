<?php

namespace ChurchCRM\Volunteer\Email;

use ChurchCRM\dto\SystemURLs;

/**
 * Outbox type `pool_add` (#10531): a team leader added somebody to the ministry's
 * volunteer pool, and each of the ministry's coordinators is told who added whom.
 * Removing someone from the pool stays a coordinator's act, so the button opens the
 * ministry page where that is done.
 */
class VolunteerPoolAddEmail extends BaseVolunteerEmail
{
    /**
     * @param string[] $toAddresses
     */
    public function __construct(
        array $toAddresses,
        string $recipientName,
        VolunteerEmailContext $context,
        private readonly string $addedByName,
        private readonly string $addedPersonName,
        private readonly int $ministryId
    ) {
        parent::__construct($toAddresses, $recipientName, $context);
    }

    protected function getSubSubject(): string
    {
        return sprintf(
            gettext('%1$s added %2$s to the %3$s volunteers'),
            $this->addedByName,
            $this->addedPersonName,
            $this->context->getMinistryName()
        );
    }

    protected function buildMessageBody(): string
    {
        $teamName = $this->context->getTeamName();
        $opening = $teamName === null
            ? sprintf(
                gettext('%1$s added %2$s to the %3$s volunteer pool.'),
                $this->addedByName,
                $this->addedPersonName,
                $this->context->getMinistryName()
            )
            : sprintf(
                gettext('%1$s, who leads %2$s, added %3$s to the %4$s volunteer pool.'),
                $this->addedByName,
                $teamName,
                $this->addedPersonName,
                $this->context->getMinistryName()
            );

        return $opening . "\n\n" . gettext('Only a coordinator can take someone out of the pool.');
    }

    protected function getFullURL(): string
    {
        return SystemURLs::getURL() . '/ministries/' . $this->ministryId;
    }

    protected function getButtonText(): string
    {
        return gettext('Open the ministry');
    }
}
