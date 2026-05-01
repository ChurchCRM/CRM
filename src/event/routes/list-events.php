<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventCountsQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /event/dashboard — events dashboard page
$app->get('/dashboard', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $canEditEvents = AuthenticationManager::getCurrentUser()->isAddEvent();

    $eType = 'All';
    if (!empty($params['type']) && $params['type'] !== 'All') {
        $eType = (int) $params['type'];
    }

    $EventYear = !empty($params['year'])
        ? (int) $params['year']
        : (int) DateTimeUtils::getCurrentYear();

    // --- Dashboard Stats (Propel ORM) ---
    $yearMin = $EventYear . '-01-01 00:00:00';
    $yearMax = $EventYear . '-12-31 23:59:59';

    $totalEventsThisYear = EventQuery::create()
        ->filterByStart(['min' => $yearMin, 'max' => $yearMax])
        ->count();

    $totalCheckInsThisYear = EventAttendQuery::create()
        ->useEventQuery()
            ->filterByStart(['min' => $yearMin, 'max' => $yearMax])
        ->endUse()
        ->filterByCheckinDate(null, Criteria::ISNOTNULL)
        ->count();

    $activeEventsThisYear = EventQuery::create()
        ->filterByStart(['min' => $yearMin, 'max' => $yearMax])
        ->filterByInActive(0)
        ->count();

    // Total number of event types defined in the system (not filtered by year).
    $totalEventTypes = EventTypeQuery::create()->count();

    // Event types that have at least one event. Two-step query — get distinct
    // type IDs from EventQuery, then fetch the EventType rows. (The previous
    // EventTypeQuery::create()->useEventTypeQuery() chain was nonsense — the
    // self-relation method doesn't exist on EventTypeQuery.)
    $typeIdsWithEvents = EventQuery::create()
        ->select('Type')
        ->distinct()
        ->find()
        ->getData();
    $eventTypesWithEvents = !empty($typeIdsWithEvents)
        ? EventTypeQuery::create()
            ->filterById($typeIdsWithEvents, Criteria::IN)
            ->orderById()
            ->find()
        : EventTypeQuery::create()->limit(0)->find();

    // Available years
    $yearQuery = EventQuery::create()
        ->addAsColumn('EventYear', 'YEAR(event_start)')
        ->select(['EventYear']);
    if ($eType !== 'All') {
        $yearQuery->filterByType((int) $eType);
    }
    $availableYears = $yearQuery
        ->groupBy('EventYear')
        ->orderBy('EventYear', Criteria::DESC)
        ->find()
        ->toArray();

    // --- Build monthly event data (all Propel ORM — replaces 6 RunQuery calls) ---
    $allMonths = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
    $monthlyData = [];

    foreach ($allMonths as $mVal) {
        $daysInMonth = DateTimeUtils::getDaysInMonth($mVal, $EventYear);
        $monthMin = sprintf('%04d-%02d-01 00:00:00', $EventYear, $mVal);
        $monthMax = sprintf('%04d-%02d-%02d 23:59:59', $EventYear, $mVal, $daysInMonth);

        $eventQuery = EventQuery::create()
            ->leftJoinWithEventType()
            ->filterByStart(['min' => $monthMin, 'max' => $monthMax])
            ->orderByStart();

        if ($eType !== 'All') {
            $eventQuery->filterByType((int) $eType);
        }

        $monthEvents = $eventQuery->find();

        if ($monthEvents->count() === 0) {
            continue;
        }

        $events = [];
        foreach ($monthEvents as $evt) {
            $eventId = (int) $evt->getId();

            // Attendance counts (Propel ORM)
            $attendeeCount = EventAttendQuery::create()
                ->filterByEventId($eventId)
                ->count();

            $checkedInCount = EventAttendQuery::create()
                ->filterByEventId($eventId)
                ->filterByCheckoutDate(null, Criteria::ISNULL)
                ->filterByCheckinDate(null, Criteria::ISNOTNULL)
                ->count();

            $checkedOutCount = EventAttendQuery::create()
                ->filterByEventId($eventId)
                ->filterByCheckoutDate(null, Criteria::ISNOTNULL)
                ->count();

            // Event counts (Propel ORM)
            $eventCounts = EventCountsQuery::create()
                ->filterByEvtcntEventid($eventId)
                ->orderByEvtcntCountid()
                ->find();

            $countsArray = [];
            foreach ($eventCounts as $count) {
                $countsArray[] = [
                    'name' => $count->getEvtcntCountname(),
                    'count' => (int) $count->getEvtcntCountcount(),
                ];
            }

            $events[] = [
                'id'                => $eventId,
                'type_name'         => $evt->getEventType() ? $evt->getEventType()->getName() : '',
                'title'             => $evt->getTitle(),
                'desc'              => $evt->getDesc(),
                'text'              => $evt->getText(),
                'start'             => $evt->getStart(),
                'end'               => $evt->getEnd(),
                'inactive'          => (int) $evt->getInActive(),
                'attendee_count'    => $attendeeCount,
                'checked_in_count'  => $checkedInCount,
                'checked_out_count' => $checkedOutCount,
                'counts'            => $countsArray,
            ];
        }

        // Monthly averages — eventcounts_evtcnt has no FK relation to events_event
        // in the schema, so we filter by the event IDs we already loaded above.
        $averages = [];
        if ($eType !== 'All' && !empty($events[0]['counts'])) {
            $eventIds = array_column($events, 'id');

            $avgCounts = EventCountsQuery::create()
                ->filterByEvtcntEventid($eventIds, Criteria::IN)
                ->addAsColumn('avg_count', 'AVG(evtcnt_countcount)')
                ->select(['EvtcntCountname', 'avg_count'])
                ->groupByEvtcntCountid()
                ->orderByEvtcntCountid()
                ->find();

            foreach ($avgCounts as $avg) {
                $averages[] = [
                    'name'      => $avg['EvtcntCountname'],
                    'avg_count' => (float) $avg['avg_count'],
                ];
            }
        }

        $monthlyData[] = [
            'month'     => $mVal,
            'monthName' => date('F', mktime(0, 0, 0, $mVal, 1, $EventYear)),
            'count'     => $monthEvents->count(),
            'events'    => $events,
            'averages'  => $averages,
        ];
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'list-events.php', [
        'sRootPath'              => SystemURLs::getRootPath(),
        'sPageTitle'             => gettext('Events Dashboard'),
        'sPageSubtitle'          => gettext('Overview of church events, attendance, and activity'),
        'aBreadcrumbs'           => PageHeader::breadcrumbs([[gettext('Events')]]),
        'sPageHeaderButtons'     => $canEditEvents ? PageHeader::buttons([
            ['label' => gettext('Manage Event Types'), 'url' => '/event/types', 'icon' => 'fa-tags', 'adminOnly' => false],
            ['label' => gettext('Audit Events'), 'url' => '/event/audit', 'icon' => 'fa-triangle-exclamation', 'adminOnly' => false],
        ]) : '',
        'canEditEvents'          => $canEditEvents,
        'eType'                  => $eType,
        'EventYear'              => $EventYear,
        'totalEventsThisYear'    => $totalEventsThisYear,
        'totalCheckInsThisYear'  => $totalCheckInsThisYear,
        'activeEventsThisYear'   => $activeEventsThisYear,
        'totalEventTypes'        => $totalEventTypes,
        'eventTypesWithEvents'   => $eventTypesWithEvents,
        'availableYears'         => $availableYears,
        'monthlyData'            => $monthlyData,
    ]);
});

// POST /event/dashboard — handle delete/activate actions. Requires AddEvent permission.
$app->post('/dashboard', function (Request $request, Response $response) {
    $body = $request->getParsedBody();

    if (!empty($body['Action']) && !empty($body['EID'])) {
        $eID = (int) $body['EID'];
        $action = $body['Action'];

        if ($action === 'Delete' && $eID > 0) {
            $event = EventQuery::create()->findOneById($eID);
            if ($event !== null) {
                $event->delete();
            }
        } elseif ($action === 'Activate' && $eID > 0) {
            $event = EventQuery::create()->findOneById($eID);
            if ($event !== null) {
                $event->setInActive(0);
                $event->save();
            }
        }
    }

    $query = [];
    if (!empty($body['type'])) {
        $query['type'] = $body['type'];
    }
    if (!empty($body['year'])) {
        $query['year'] = $body['year'];
    }
    $target = SystemURLs::getRootPath() . '/event/dashboard';
    if (!empty($query)) {
        $target .= '?' . http_build_query($query);
    }

    return $response->withHeader('Location', $target)->withStatus(302);
})->add(new AddEventsRoleAuthMiddleware());
