<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\User;

/**
 * Module report links for the Reports menu. forUser() returns only the
 * reports the current user is allowed to open.
 */
class ReportCatalog
{
    /**
     * @return array<int, array{module: string, title: string, description: string, url: string, icon: string}>
     */
    public static function forUser(User $user): array
    {
        $entries = [];
        foreach (self::all($user) as [$allowed, $entry]) {
            if ($allowed) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array{0: bool, 1: array{module: string, title: string, description: string, url: string, icon: string}}>
     */
    private static function all(User $user): array
    {
        return [
            [$user->isAdmin(), [
                'module'      => gettext('People'),
                'title'       => gettext('People Reports'),
                'description' => gettext('Birthdays, anniversaries, volunteers and other people lists with a classification filter'),
                'url'         => 'people/reports',
                'icon'        => 'fa-table-list',
            ]],
            [$user->isManageGroupsEnabled(), [
                'module'      => gettext('Groups'),
                'title'       => gettext('Group Reports'),
                'description' => gettext('Group membership lists and Sunday School class reports'),
                'url'         => 'groups/reports',
                'icon'        => 'fa-users',
            ]],
            [$user->isFinanceEnabled(), [
                'module'      => gettext('Finance'),
                'title'       => gettext('Financial Reports'),
                'description' => gettext('Generate reports for tax statements, pledge tracking, and financial analysis.'),
                'url'         => 'finance/reports',
                'icon'        => 'fa-file-invoice',
            ]],
        ];
    }
}
