<?php

namespace ChurchCRM\Portal\Permissions;

use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\PortalCalendarService;
use ChurchCRM\Portal\Permissions\Rules\DirectoryViewerRule;
use ChurchCRM\Portal\Permissions\Rules\EveryoneRule;
use ChurchCRM\Portal\Permissions\Rules\FamilyAdultRule;
use ChurchCRM\Portal\Permissions\Rules\TeamLeaderRule;

/**
 * The first catalog (member sign-in design §8.4) and the core rules (§8.3).
 * `portal.devices.manage` is added by row 7 for the Sign-in and devices page.
 */
final class CorePortalCapabilities
{
    public const PROFILE_EDIT = 'portal.profile.edit';
    public const DEVICES_MANAGE = 'portal.devices.manage';
    public const FAMILY_VIEW = 'portal.family.view';
    public const FAMILY_CONTACTS_VIEW = 'portal.family.contacts.view';
    public const FAMILY_EDIT = 'portal.family.edit';
    public const FAMILY_CONFIRM = 'portal.family.confirm';
    public const CALENDAR_VIEW = 'portal.calendar.view';
    public const DIRECTORY_VIEW = 'portal.directory.view';
    public const VOLUNTEER_USE = 'portal.volunteer.use';
    public const TEAM_MANAGE = 'volunteer.team.manage';
    public const TEAM_SUBSTITUTES = 'volunteer.team.substitutes';

    public static function register(): void
    {
        $core = PortalCapability::OWNER_CORE;
        $volunteerV2 = static fn (): bool => User::isVolunteerV2Enabled();
        $calendarShared = static fn (): bool => PortalCalendarService::hasVisibleCalendars();

        foreach ([
            new PortalCapability(self::PROFILE_EDIT, gettext('Edit my details'), gettext('Change their own name, contact details and photo.'), $core),
            new PortalCapability(self::DEVICES_MANAGE, gettext('Manage my sign-in and devices'), gettext('See the devices and passkeys that sign them in, and sign out or remove any of them.'), $core),
            new PortalCapability(self::FAMILY_VIEW, gettext('See my family'), gettext('See the names and photos of the people in their family.'), $core, PortalScope::FAMILY),
            new PortalCapability(self::FAMILY_CONTACTS_VIEW, gettext('See family contact details'), gettext('See the email addresses and phone numbers of the people in their family.'), $core, PortalScope::FAMILY),
            new PortalCapability(self::FAMILY_EDIT, gettext('Change family details'), gettext('Change the family address and contact details, and add a family member for review.'), $core, PortalScope::FAMILY),
            new PortalCapability(self::FAMILY_CONFIRM, gettext('Confirm family details'), gettext('Tell the church office whether the family details are still right.'), $core, PortalScope::FAMILY),
            new PortalCapability(self::CALENDAR_VIEW, gettext('See the church calendar'), gettext('See the calendars the church shares with members, and subscribe to them.'), $core, PortalScope::NONE, null, $calendarShared),
            new PortalCapability(self::DIRECTORY_VIEW, gettext('See the member directory'), gettext('See the people the church lists in its directory, with the details the church chooses to share.'), $core, PortalScope::NONE, 'bPortalShowDirectory'),
            new PortalCapability(self::VOLUNTEER_USE, gettext('Volunteer'), gettext('See their volunteer schedule, sign up for open places and ask for a substitute.'), $core, PortalScope::NONE, 'bPortalShowVolunteer', $volunteerV2),
            new PortalCapability(self::TEAM_MANAGE, gettext('Manage a team'), gettext('Run a volunteer team: positions, volunteers, schedules, staffing and headcounts.'), $core, PortalScope::TEAM, 'bPortalShowVolunteer', $volunteerV2),
            new PortalCapability(self::TEAM_SUBSTITUTES, gettext('Decide substitute requests'), gettext('Approve or decline substitute requests for a volunteer team.'), $core, PortalScope::TEAM, 'bPortalShowVolunteer', $volunteerV2),
        ] as $capability) {
            PortalCapabilityRegistry::register($capability);
        }

        PortalCapabilityRegistry::registerRule(new EveryoneRule());
        PortalCapabilityRegistry::registerRule(new FamilyAdultRule());
        PortalCapabilityRegistry::registerRule(new TeamLeaderRule());
        PortalCapabilityRegistry::registerRule(new DirectoryViewerRule());
    }
}
