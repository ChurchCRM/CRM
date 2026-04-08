<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventCountsQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
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
    if (!empty($params['WhichType']) && $params['WhichType'] !== 'All') {
        $eType = (int) $params['WhichType'];
    }

    $EventYear = !empty($params['WhichYear'])
        ? (int) $params['WhichYear']
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

    $totalEventTypes = EventTypeQuery::create()
        ->useEventTypeQuery()
            ->filterByStart(['min' => $yearMin, 'max' => $yearMax])
        ->endUse()
        ->distinct()
        ->count();

    // Event types that have events
    $eventTypesWithEvents = EventTypeQuery::create()
        ->useEventTypeQuery()
        ->endUse()
        ->distinct()
        ->orderById()
        ->find();

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
    $allMonths = [12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1];
    $monthlyData = [];

    foreach ($allMonths as $mVal) {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $mVal, $EventYear);
        $monthMin = sprintf('%04d-%02d-01 00:00:00', $EventYear, $mVal);
        $monthMax = sprintf('%04d-%02d-%02d 23:59:59', $EventYear, $mVal, $daysInMonth);

        $eventQuery = EventQuery::create()
            ->joinWithEventType()
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

        // Monthly averages (Propel ORM)
        $averages = [];
        if ($eType !== 'All' && !empty($events[0]['counts'])) {
            $avgCounts = EventCountsQuery::create()
                ->useEventQuery()
                    ->filterByType((int) $eType)
                    ->filterByStart(['min' => $monthMin, 'max' => $monthMax])
                ->endUse()
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
            ['label' => gettext('Manage Event Types'), 'url' => '/EventNames.php', 'icon' => 'fa-tags', 'adminOnly' => false],
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

// POST /event/dashboard — handle delete/activate actions
$app->post('/dashboard', function (Request $request, Response $response) {
    $body = $request->getParsedBody();
    $canEditEvents = AuthenticationManager::getCurrentUser()->isAddEvent();

    if (!empty($body['Action']) && !empty($body['EID']) && $canEditEvents) {
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
    if (!empty($body['WhichType'])) {
        $query['WhichType'] = $body['WhichType'];
    }
    if (!empty($body['WhichYear'])) {
        $query['WhichYear'] = $body['WhichYear'];
    }
    $target = SystemURLs::getRootPath() . '/event/dashboard';
    if (!empty($query)) {
        $target .= '?' . http_build_query($query);
    }

    return $response->withHeader('Location', $target)->withStatus(302);
});
