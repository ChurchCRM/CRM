<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\User;

/**
 * A user's effective permissions, row by row, read from the same predicates the
 * route gates call, so each answer is what the user can actually do.
 */
class UserPermissionsService
{
    /**
     * @return list<array{group: string, rows: list<array{label: string, granted: bool, reason: ?string}>}>
     */
    public function getEffectivePermissions(User $user): array
    {
        $eventsOff = !User::isEventsEnabled();
        $notGranted = gettext('Not granted to this account');

        $row = function (string $label, bool $granted, ?string $moduleOff = null, ?string $denied = null) use ($notGranted): array {
            return [
                'label' => $label,
                'granted' => $granted,
                'reason' => $granted ? null : $moduleOff ?? $denied ?? $notGranted,
            ];
        };

        return [
            [
                'group' => gettext('People & Families'),
                'rows' => [
                    $row(gettext('Add people and families'), $user->isAddRecordsEnabled()),
                    $row(gettext('Edit people and families'), $user->isEditRecordsEnabled()),
                    $row(gettext('Delete people and families'), $user->isDeleteRecordsEnabled()),
                    $row(gettext('Notes'), $user->isNotesEnabled()),
                ],
            ],
            [
                'group' => gettext('Groups'),
                'rows' => [
                    $row(gettext('Manage Groups and Roles'), $user->isManageGroupsEnabled()),
                ],
            ],
            [
                'group' => gettext('Finance'),
                'rows' => [
                    $row(
                        gettext('Manage Donations and Finance'),
                        $user->isFinanceEnabled(),
                        $this->isOff('bEnabledFinance') ? gettext('Finance is turned off for this church') : null
                    ),
                    $row(
                        gettext('Manage Fundraisers'),
                        $user->isManageFundraisersEnabled(),
                        $this->isOff('bEnabledFundraiser') ? gettext('Fundraisers are turned off for this church') : null
                    ),
                ],
            ],
            [
                'group' => gettext('Events'),
                'rows' => [
                    $row(
                        gettext('View events'),
                        $user->canViewEvents(),
                        $eventsOff ? gettext('Events are turned off for this church') : null
                    ),
                    $row(
                        gettext('Add and edit events'),
                        $user->canManageEvents(),
                        $eventsOff ? gettext('Events are turned off for this church') : null
                    ),
                ],
            ],
            [
                'group' => gettext('Email'),
                'rows' => [
                    $row(gettext('Email people and groups'), $user->isEmailEnabled()),
                ],
            ],
            [
                'group' => gettext('Volunteers'),
                'rows' => [
                    $row(gettext('Manage all ministries'), $user->isManageMinistriesEnabled()),
                    $row(gettext('Manage My Ministries'), $user->isManageMyMinistriesEnabled()),
                    $row(
                        gettext('Ministry coordinator'),
                        $user->isVolunteerCoordinatorEnabled(),
                        null,
                        $user->isManageMyMinistriesEnabled() ? gettext('No ministry assigned') : null
                    ),
                    $row(
                        gettext('Team leader'),
                        $user->isVolunteerTeamLeaderEnabled(),
                        null,
                        gettext('Not leading a volunteer team')
                    ),
                ],
            ],
        ];
    }

    private function isOff(string $flag): bool
    {
        return !SystemConfig::getBooleanValue($flag);
    }
}
