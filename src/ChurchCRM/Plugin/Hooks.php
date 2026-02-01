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
     * Filter: Called before a person is created.
     * Receives: array $personData
     * Returns: array Modified person data
     */
    public const PERSON_PRE_CREATE = 'person.pre_create';

    /**
     * Action: Called after a person is created.
     * Receives: Person $person
     */
    public const PERSON_CREATED = 'person.created';

    /**
     * Filter: Called before a person is updated.
     * Receives: Person $person, array $changes
     * Returns: array Modified changes
     */
    public const PERSON_PRE_UPDATE = 'person.pre_update';

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

    /**
     * Filter: Modify tabs on person view page.
     * Receives: array $tabs, Person $person
     * Returns: array Modified tabs
     */
    public const PERSON_VIEW_TABS = 'person.view.tabs';

    // =========================================================================
    // Family Hooks
    // =========================================================================

    /**
     * Filter: Called before a family is created.
     * Receives: array $familyData
     * Returns: array Modified family data
     */
    public const FAMILY_PRE_CREATE = 'family.pre_create';

    /**
     * Action: Called after a family is created.
     * Receives: Family $family
     */
    public const FAMILY_CREATED = 'family.created';

    /**
     * Filter: Called before a family is updated.
     * Receives: Family $family, array $changes
     * Returns: array Modified changes
     */
    public const FAMILY_PRE_UPDATE = 'family.pre_update';

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

    /**
     * Filter: Modify tabs on family view page.
     * Receives: array $tabs, Family $family
     * Returns: array Modified tabs
     */
    public const FAMILY_VIEW_TABS = 'family.view.tabs';

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
    // Email Hooks
    // =========================================================================

    /**
     * Filter: Called before an email is sent.
     * Receives: array $emailData (to, subject, body, etc.)
     * Returns: array Modified email data
     */
    public const EMAIL_PRE_SEND = 'email.pre_send';

    /**
     * Action: Called after an email is sent.
     * Receives: array $emailData, bool $success
     */
    public const EMAIL_SENT = 'email.sent';

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
     * Filter: Modify dashboard widgets.
     * Receives: array $widgets
     * Returns: array Modified widgets
     */
    public const DASHBOARD_WIDGETS = 'dashboard.widgets';

    /**
     * Filter: Modify settings panels.
     * Receives: array $panels
     * Returns: array Modified panels
     */
    public const SETTINGS_PANELS = 'settings.panels';

    /**
     * Action: Called when admin pages are being rendered.
     * Receives: string $pageId
     */
    public const ADMIN_PAGE = 'admin.page';

    // =========================================================================
    // Report Hooks
    // =========================================================================

    /**
     * Filter: Called before a report is generated.
     * Receives: array $reportData, string $reportType
     * Returns: array Modified report data
     */
    public const REPORT_PRE_GENERATE = 'report.pre_generate';

    /**
     * Filter: Add custom report types.
     * Receives: array $reportTypes
     * Returns: array Modified report types
     */
    public const REPORT_TYPES = 'report.types';

    // =========================================================================
    // System Hooks
    // =========================================================================

    /**
     * Action: Called during system initialization.
     * Receives: (none)
     */
    public const SYSTEM_INIT = 'system.init';

    /**
     * Action: Called after system upgrade.
     * Receives: string $fromVersion, string $toVersion
     */
    public const SYSTEM_UPGRADED = 'system.upgraded';

    /**
     * Action: Called during scheduled tasks (cron).
     * Receives: (none)
     */
    public const CRON_RUN = 'cron.run';

    /**
     * Filter: Modify API response before sending.
     * Receives: array $response, string $endpoint
     * Returns: array Modified response
     */
    public const API_RESPONSE = 'api.response';
}
