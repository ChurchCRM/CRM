<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerNotification;
use ChurchCRM\model\ChurchCRM\VolunteerNotificationQuery;
use ChurchCRM\Service\SystemService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Served at /admin/ministry-settings — the '/admin' prefix comes from
// MvcAppFactory::create(), and AdminRoleAuthMiddleware gates the whole app, so
// this page is administrators-only without any check of its own.
//
// The one home of the Volunteer Management settings (product-owner decision,
// 2026-09-18, following the Member Portal precedent): the rollout state and the
// reminder lead time used to sit in an admin-only strip on the Ministry
// Dashboard, which only exists once V2 is on — so the switch that turns V2 on
// could not be reached from the page that held it. They are deliberately in no
// System Settings category any more, so this page is where they live.
$ministrySettingsHandler = function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $version = User::getVolunteerVersion();
    $v2Enabled = User::isVolunteerV2Enabled();

    // Delivery health, installation-wide (this is an administrator's page, so
    // no coordinator scoping): terminal failures are the number to act on,
    // queued rows say whether the drain is keeping up, and the last timer-job
    // run says whether it is running at all.
    $failedCount = VolunteerNotificationQuery::create()
        ->filterByStatus(VolunteerNotification::STATUS_FAILED)
        ->count();
    $pendingCount = VolunteerNotificationQuery::create()
        ->filterByStatus(VolunteerNotification::STATUS_PENDING)
        ->count();
    $recentFailures = [];
    foreach (
        VolunteerNotificationQuery::create()
            ->filterByStatus(VolunteerNotification::STATUS_FAILED)
            ->orderByLastAttemptDate('DESC')
            ->limit(10)
            ->find() as $row
    ) {
        $person = $row->getPerson();
        $recentFailures[] = [
            'type' => (string) $row->getType(),
            'person' => $person === null ? '' : $person->getFullName(),
            'lastAttempt' => $row->getLastAttemptDate('Y-m-d H:i') ?? '',
            'error' => (string) ($row->getLastError() ?? ''),
        ];
    }

    $headerButtons = $v2Enabled
        ? PageHeader::buttons([
            ['label' => gettext('Open the Ministry Dashboard'), 'url' => '/ministries/dashboard', 'icon' => 'fa-arrow-up-right-from-square'],
        ])
        : '';

    return $renderer->render($response, 'ministry-settings.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Ministry Settings'),
        'sPageSubtitle' => gettext('Choose which volunteer experience this church uses, set the reminder lead time, and check that notifications are going out.'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Ministry Settings')],
        ]),
        'sPageHeaderButtons' => $headerButtons,
        'sVersion' => $version,
        'bV2Enabled' => $v2Enabled,
        'iLeadHours' => (int) SystemConfig::getValue('iVolunteerReminderLeadHours'),
        'iFailedCount' => $failedCount,
        'iPendingCount' => $pendingCount,
        'aRecentFailures' => $recentFailures,
        'sLastTimerJobsRun' => (string) SystemConfig::getValue(SystemService::TIMER_JOBS_LAST_RUN_CONFIG),
        // The supported entry point for the outbox drain is the CLI runner —
        // the same command src/cli/timerjobs.php documents.
        'sTimerJobsHint' => '0,15,30,45 * * * * /usr/bin/php /path/to/churchcrm/cli/timerjobs.php',
    ]);
};

$app->get('/ministry-settings', $ministrySettingsHandler);
$app->get('/ministry-settings/', $ministrySettingsHandler);
