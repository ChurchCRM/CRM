<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerNotification;
use ChurchCRM\model\ChurchCRM\VolunteerNotificationQuery;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Volunteer\Service\VolunteerEventService;
use ChurchCRM\Volunteer\Service\VolunteerScheduleService;
use ChurchCRM\Volunteer\Service\VolunteerScheduleTopUp;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Served at /admin/ministry-settings — the '/admin' prefix comes from
// MvcAppFactory::create(), and AdminRoleAuthMiddleware gates the whole app, so
// this page is administrators-only without any check of its own.
//
// The one home of the Volunteer Management settings, following the Member Portal
// precedent. They are deliberately in no System Settings category. The page only
// exists while V2 is on; the V1 / V2 / Both switch is on Feature Toggles.
$ministrySettingsHandler = function (Request $request, Response $response): Response {
    // The rollout state (V1 / V2 / Both) is switched on the Feature Toggles page; these
    // settings only mean something once V2 is on.
    if (!User::isVolunteerV2Enabled()) {
        return $response->withHeader('Location', SystemURLs::getRootPath() . '/admin/system/feature-toggles')->withStatus(302);
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

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

    // D31: the choices for the ministry events' default type. Empty means "the type named
    // Other", which the label says only when there is one.
    $otherTypeId = null;
    $eventTypeChoices = [];
    foreach (EventTypeQuery::create()->filterByActive(1)->orderByName()->find() as $type) {
        $eventTypeChoices[] = ['value' => (string) $type->getId(), 'label' => (string) $type->getName()];
        if (strcasecmp((string) $type->getName(), VolunteerEventService::OTHER_EVENT_TYPE_NAME) === 0) {
            $otherTypeId ??= (int) $type->getId();
        }
    }
    array_unshift($eventTypeChoices, [
        'value' => '',
        'label' => $otherTypeId === null
            ? gettext('None: the event type is chosen for each event')
            : sprintf(gettext('Not set: use "%s"'), VolunteerEventService::OTHER_EVENT_TYPE_NAME),
    ]);

    $headerButtons = PageHeader::buttons([
        ['label' => gettext('Open the Ministry Dashboard'), 'url' => '/ministries/dashboard', 'icon' => 'fa-arrow-up-right-from-square'],
    ]);

    return $renderer->render($response, 'ministry-settings.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Ministry Settings'),
        'sPageSubtitle' => gettext('Choose how far ahead schedules are filled and when reminders go out, and check that the background jobs are running.'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            [gettext('Ministry Settings')],
        ]),
        'sPageHeaderButtons' => $headerButtons,
        'iLeadHours' => (int) SystemConfig::getValue('iVolunteerReminderLeadHours'),
        'iHorizonWeeks' => VolunteerScheduleService::horizonWeeks(),
        'iMinHorizonWeeks' => VolunteerScheduleService::MIN_HORIZON_WEEKS,
        'iMaxHorizonWeeks' => VolunteerScheduleService::MAX_HORIZON_WEEKS,
        'aEventTypeChoices' => $eventTypeChoices,
        'aLastTopUp' => VolunteerScheduleTopUp::lastResult(),
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
