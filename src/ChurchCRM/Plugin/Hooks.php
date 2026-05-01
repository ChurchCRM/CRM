<?php

namespace ChurchCRM\Plugin;

/**
 * Defines all available hook points in ChurchCRM.
 *
 * This class serves as documentation for plugin developers
 * and provides constants for hook names to prevent typos.
 */
final class Hooks
{
    // =========================================================================
    // Person Hooks
    // =========================================================================
    /**
     * Action: Called after a person is created.
     * Receives: Person $person
     */
    public const PERSON_CREATED = 'person.created';

    /**
     * Action: Called after a person is updated.
     * Receives: Person $person, array $oldData
     */
    public const PERSON_UPDATED = 'person.updated';

    /**
     * Action: Called after a person is deleted.
     * Receives: int $personId, array $personData
     */
    public const PERSON_DELETED = 'person.deleted';

    // =========================================================================
    // Family Hooks
    // =========================================================================

    /**
     * Action: Called after a family is created.
     * Receives: Family $family
     */
    public const FAMILY_CREATED = 'family.created';

    /**
     * Action: Called after a family is updated.
     * Receives: Family $family, array $oldData
     */
    public const FAMILY_UPDATED = 'family.updated';

    /**
     * Action: Called after a family is deleted.
     * Receives: int $familyId, array $familyData
     */
    public const FAMILY_DELETED = 'family.deleted';

    // =========================================================================
    // Financial Hooks
    // =========================================================================

    /**
     * Action: Called after a donation/pledge is recorded.
     * Receives: Pledge $pledge
     */
    public const DONATION_RECEIVED = 'donation.received';

    /**
     * Action: Called after a deposit slip is closed.
     * Receives: Deposit $deposit
     */
    public const DEPOSIT_CLOSED = 'deposit.closed';

    // =========================================================================
    // Event Hooks
    // =========================================================================

    /**
     * Action: Called after an event is created.
     * Receives: Event $event
     */
    public const EVENT_CREATED = 'event.created';

    /**
     * Filter: Allow plugins to contribute additional system calendars.
     * Receives: SystemCalendar[] $calendars
     * Returns: SystemCalendar[] Modified array of system calendars
     */
    public const SYSTEM_CALENDARS_REGISTER = 'systemcalendars.register';

    /**
     * Action: Called after a person checks in to an event.
     * Receives: EventAttend $attendance, Event $event, Person $person
     */
    public const EVENT_CHECKIN = 'event.checkin';

    /**
     * Action: Called after a person checks out of an event.
     * Receives: EventAttend $attendance, Event $event, Person $person
     */
    public const EVENT_CHECKOUT = 'event.checkout';

    // =========================================================================
    // Group Hooks
    // =========================================================================

    /**
     * Action: Called when a person joins a group.
     * Receives: Person2group2roleP2g2r $membership, Group $group, Person $person
     */
    public const GROUP_MEMBER_ADDED = 'group.member.added';

    /**
     * Action: Called when a person leaves a group.
     * Receives: int $personId, Group $group
     */
    public const GROUP_MEMBER_REMOVED = 'group.member.removed';

    // =========================================================================
    // UI/Menu Hooks
    // =========================================================================

    /**
     * Filter: Modify main menu items.
     * Receives: array $menuItems
     * Returns: array Modified menu items
     */
    public const MENU_BUILDING = 'menu.building';


    /**
     * Action: Called during scheduled tasks (cron).
     * Receives: (none)
     */
    public const CRON_RUN = 'cron.run';

    // =========================================================================
}
