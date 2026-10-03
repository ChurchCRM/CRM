<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\User;

/**
 * Every module-owned report in one list. The Reports menu and the Reports
 * index page are both built from forUser(), so a report appears once and only
 * to users allowed to open it.
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
            [$user->isFinanceEnabled(), [
                'module'      => gettext('Finance'),
                'title'       => gettext('Financial Reports'),
                'description' => gettext('Generate reports for tax statements, pledge tracking, and financial analysis.'),
                'url'         => 'finance/reports',
                'icon'        => 'fa-file-invoice',
            ]],
            [$user->isManageFundraisersEnabled(), [
                'module'      => gettext('Fundraiser'),
                'title'       => gettext('Fundraiser Reports'),
                'description' => gettext('Bid sheets, certificates and catalogs are run from each fundraiser.'),
                'url'         => 'fundraiser/',
                'icon'        => 'fa-money-bill-1',
            ]],
        ];
    }
}
