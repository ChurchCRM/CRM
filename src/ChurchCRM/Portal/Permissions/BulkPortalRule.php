<?php

namespace ChurchCRM\Portal\Permissions;

/**
 * A rule that can also answer for many people at once, from its own data, so
 * the Permissions tab on Admin → Member Portal can count holders without
 * asking the rule about each person in turn (member sign-in design §8.6).
 * Core rules implement it; a plugin rule may. A rule that does not is asked
 * person by person.
 */
interface BulkPortalRule extends PortalRule
{
    /** The rule as the Permissions tab names it, e.g. "Adult of the family". */
    public function getSourceName(): string;

    /**
     * Every capability the rule can give.
     *
     * @return string[]
     */
    public function getCapabilityKeys(): array;

    /**
     * The grants the rule gives these people now, keyed by person id. A person
     * with several grants appears several times.
     *
     * @param int[] $personIds
     *
     * @return iterable<int, PortalRuleGrant>
     */
    public function grantsForPeople(array $personIds): iterable;
}
