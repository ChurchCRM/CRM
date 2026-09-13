<?php

namespace ChurchCRM\Emails\volunteer;

use ChurchCRM\dto\SystemURLs;

/**
 * Outbox type `help_offer` — somebody pressed "I'd like to help" on the Open
 * Opportunities page (D19, design §5.6, Appendix C).
 *
 * The only V2 message that is about a **ministry** rather than an occurrence, so it is
 * also the only one whose context block carries no date, no position and no place: there
 * is nothing scheduled yet, which is the entire point. What the coordinator needs is a
 * name, a ministry and the one screen where they can do something about it — the
 * ministry page's qualification tab, because qualifying the person for a position is the
 * step that turns an offer into somebody the rotation can actually use.
 *
 * Two wordings, decided at the moment of the click and carried through the outbox in
 * `vntf_Context` (it cannot be recomputed at delivery time — by then the person is in
 * the pool either way):
 *
 *   NEW      "{Person} wants to help with {Ministry} and has been added to its
 *            volunteer pool. Qualify them for a position."
 *   EXISTING "{Person} is already in the {Ministry} volunteer pool and wants to help
 *            again. Qualify them for a position."
 *
 * The second is not a lesser version of the first: a coordinator who sees the same name
 * a third week running is being told something, and softening it into the "has been
 * added" sentence would hide it.
 */
class VolunteerHelpOfferEmail extends BaseVolunteerEmail
{
    private string $offeringPersonName;

    private int $ministryId;

    private bool $joinedPool;

    /**
     * @param string[] $toAddresses
     * @param bool     $joinedPool true when this click is what put them in the pool
     */
    public function __construct(
        array $toAddresses,
        string $recipientName,
        VolunteerEmailContext $context,
        string $offeringPersonName,
        int $ministryId,
        bool $joinedPool
    ) {
        $this->offeringPersonName = $offeringPersonName;
        $this->ministryId = $ministryId;
        $this->joinedPool = $joinedPool;

        parent::__construct($toAddresses, $recipientName, $context);
    }

    protected function getSubSubject(): string
    {
        return sprintf(
            gettext('%1$s wants to help with %2$s'),
            $this->offeringPersonName,
            $this->context->getMinistryName()
        );
    }

    protected function buildMessageBody(): string
    {
        $opening = $this->joinedPool
            ? sprintf(
                gettext('%1$s wants to help with %2$s and has been added to its volunteer pool.'),
                $this->offeringPersonName,
                $this->context->getMinistryName()
            )
            : sprintf(
                gettext('%1$s is already in the %2$s volunteer pool and wants to help again.'),
                $this->offeringPersonName,
                $this->context->getMinistryName()
            );

        return $opening . "\n\n" . gettext('Qualify them for a position.');
    }

    /** The qualification tab, not the ministry's front page: that is where the action is. */
    protected function getFullURL(): string
    {
        return SystemURLs::getURL() . '/volunteer/ministries/' . $this->ministryId . '#qualifications';
    }

    protected function getButtonText(): string
    {
        return gettext('Qualify them for a position');
    }
}
