<?php

namespace ChurchCRM\Volunteer\Middleware;

/**
 * The gate for the `/ministries` admin pages: the dashboard, a ministry and an occurrence.
 *
 * Mirrors the sidebar's Ministries heading (`Menu::buildMenuItems()`), which shows only
 * for `isVolunteerCoordinatorEnabled()`. The API keeps the parent gate, whose team-leader
 * clause lets a team leader run their team from the Member Portal; on these pages that
 * clause let a staff login that leads a team, but has no Manage My Ministries access,
 * open the dashboard by typing its URL (design D12, 2026-09-18). Their alert emails link
 * to the portal's copies of these pages instead.
 */
class VolunteerAdminAreaRoleAuthMiddleware extends VolunteerCoordinatorRoleAuthMiddleware
{
    protected function hasRole(): bool
    {
        return $this->user->isVolunteerCoordinatorEnabled();
    }
}
