<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\UserService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/settings', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $financeOn = SystemConfig::getBooleanValue('bEnabledFinance');
    $eventsOn = SystemConfig::getBooleanValue('bEnabledEvents');

    $linkGroups = [
        [gettext('Church & System'), [
            [gettext('Church Information'), '/admin/system/church-info', 'fa-church'],
            [gettext('Localization & Formats'), '/admin/system/localization', 'fa-globe'],
            [gettext('Feature Toggles'), '/admin/system/feature-toggles', 'fa-toggle-on'],
            [gettext('System Users'), '/admin/system/users', 'fa-user-gear'],
            [gettext('Email Settings'), '/admin/email', 'fa-envelope'],
            [gettext('Plugins'), '/plugins/management', 'fa-plug'],
            [gettext('System Logs'), '/admin/system/logs', 'fa-file-lines'],
            [gettext('System Upgrade'), '/admin/system/upgrade', 'fa-arrow-up'],
        ]],
        [gettext('Areas'), array_values(array_filter([
            [gettext('People Settings'), '/admin/people', 'fa-users'],
            [gettext('Member Portal'), '/admin/member-portal', 'fa-house-user'],
            [gettext('Ministry Settings'), '/admin/ministry-settings', 'fa-hands-helping'],
            $financeOn ? [gettext('Finance Settings'), '/admin/finance', 'fa-sack-dollar'] : null,
            $financeOn ? [gettext('Statement Settings'), '/finance/statements/settings', 'fa-file-invoice'] : null,
            [gettext('Report Settings'), '/admin/system/report-settings', 'fa-file-lines'],
        ]))],
    ];

    $searchLabels = [
        'bSearchIncludePersons' => gettext('Search People'),
        'bSearchIncludeFamilies' => gettext('Search Families'),
        'bSearchIncludeGroups' => gettext('Search Groups'),
        'bSearchIncludeDeposits' => gettext('Search Deposits'),
        'bSearchIncludePayments' => gettext('Search Payments'),
        'bSearchIncludeAddresses' => gettext('Search Addresses'),
        'bSearchIncludeCalendarEvents' => gettext('Search Calendar Events'),
        'bSearchIncludeFamilyCustomProperties' => gettext('Search Family Custom Properties'),
    ];
    $searchKeys = ['bSearchIncludePersons', 'bSearchIncludeFamilies', 'bSearchIncludeFamilyHOH', 'bSearchIncludeFamilyCustomProperties', 'bSearchIncludeAddresses', 'bSearchIncludeGroups'];
    if ($financeOn) {
        array_push($searchKeys, 'bSearchIncludeDeposits', 'bSearchIncludePayments');
    }
    if ($eventsOn) {
        $searchKeys[] = 'bSearchIncludeCalendarEvents';
    }

    $sections = [
        ['id' => 'quickSearchSettings', 'title' => gettext('Quick Search'), 'icon' => 'fa-solid fa-magnifying-glass', 'settings' => array_map(static function (array $setting) use ($searchLabels): array {
            if (isset($searchLabels[$setting['name']])) {
                $setting['label'] = $searchLabels[$setting['name']];
            }
            if (in_array($setting['name'], ['bSearchIncludeFamilyHOH', 'bSearchIncludeFamilyCustomProperties'], true)) {
                $setting['dependsOn'] = 'bSearchIncludeFamilies';
            }

            return $setting;
        }, SystemConfig::getSettingsConfig($searchKeys))],
        ['id' => 'scheduledTasksSettings', 'title' => gettext('Scheduled Tasks'), 'icon' => 'fa-solid fa-clock', 'settings' => array_map(static function (array $setting): array {
            $setting['label'] = gettext('Warning After (Hours)');

            return $setting;
        }, SystemConfig::getSettingsConfig(['iTimerJobsStaleHours']))],
        ['id' => 'securitySettings', 'title' => gettext('Sign-in & Security'), 'icon' => 'fa-solid fa-shield-halved', 'settings' => (new UserService())->getUserSettingsConfig()],
        ['id' => 'textMessageSettings', 'title' => gettext('Text Messages'), 'icon' => 'fa-solid fa-comment-sms', 'settings' => array_map(static function (array $setting): array {
            $setting['label'] = gettext('Do Not SMS Property');
            $setting['type'] = 'ajax';
            $setting['ajaxUrl'] = '/admin/api/system/properties/person';

            return $setting;
        }, SystemConfig::getSettingsConfig(['iDoNotSmsPropertyId']))],
        ...($eventsOn ? [['id' => 'publicCalendarSettings', 'title' => gettext('Public Calendar'), 'icon' => 'fa-solid fa-calendar', 'settings' => array_map(static function (array $setting): array {
            $setting['label'] = gettext('Calendar Embed Origins');

            return $setting;
        }, SystemConfig::getSettingsConfig(['sCalendarEmbedOrigins']))]] : []),
        ['id' => 'reportingSettings', 'title' => gettext('Reporting'), 'icon' => 'fa-solid fa-file-pdf', 'settings' => array_map(static function (array $setting): array {
            $setting['label'] = gettext('PDF Temporary Directory');

            return $setting;
        }, SystemConfig::getSettingsConfig(['sPDFTempDir']))],
    ];

    return $renderer->render($response, 'settings.php', [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Settings'),
        'sPageSubtitle' => gettext('Every settings page in one place, and the settings that apply to the whole system'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Settings')],
        ]),
        'linkGroups'   => $linkGroups,
        'sections'     => $sections,
    ]);
});
