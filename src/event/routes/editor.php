<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventAudience;
use ChurchCRM\model\ChurchCRM\EventAudienceQuery;
use ChurchCRM\model\ChurchCRM\EventCountNameQuery;
use ChurchCRM\model\ChurchCRM\EventCounts;
use ChurchCRM\model\ChurchCRM\EventCountsQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AddEventsRoleAuthMiddleware;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Compute smart-prefill defaults for a new event based on EventType.
 */
function computeEventDefaults(int $typeId): array
{
    $defaults = [
        'iTypeID'         => 0,
        'sTypeName'       => '',
        'sEventStartDate' => DateTimeUtils::getTodayDate(),
        'sEventEndDate'   => DateTimeUtils::getTodayDate(),
        'iEventStartHour' => '09',
        'iEventStartMins' => '00',
        'iEventEndHour'   => '10',
        'iEventEndMins'   => '00',
        'sTypeDefRecurDOW' => '',
    ];

    $eventType = EventTypeQuery::create()->findOneById($typeId);
    if ($eventType === null) {
        return $defaults;
    }

    $defaults['iTypeID'] = (int) $eventType->getId();
    $defaults['sTypeName'] = $eventType->getName();

    $defStart = $eventType->getDefStartTime();
    $sDefStartTime = ($defStart instanceof \DateTime) ? $defStart->format('H:i:s') : '09:00:00';
    [$startH, $startM] = explode(':', $sDefStartTime);

    $defaults['iEventStartHour'] = $startH;
    $defaults['iEventStartMins'] = $startM;
    $defaults['iEventEndHour'] = str_pad((string) ((int) $startH + 1), 2, '0', STR_PAD_LEFT);
    $defaults['iEventEndMins'] = $startM;

    $iDefRecurDOW = $eventType->getDefRecurDow();
    $iDefRecurDOM = $eventType->getDefRecurDom();
    $sDefRecurDOY = $eventType->getDefRecurDoy();
    $sDefRecurType = $eventType->getDefRecurType();

    $defaults['sTypeDefRecurDOW'] = $iDefRecurDOW;

    // For recurring types, find the most recent event of this type via Propel
    if (in_array($sDefRecurType, ['weekly', 'monthly', 'yearly'], true)) {
        $lastEvent = EventQuery::create()
            ->filterByType($typeId)
            ->orderByStart(Criteria::DESC)
            ->limit(1)
            ->findOne();

        if ($lastEvent !== null) {
            $eventStart = $lastEvent->getStart();
            $startStr = ($eventStart instanceof \DateTime) ? $eventStart->format('Y-m-d') : (string) $eventStart;

            switch ($sDefRecurType) {
                case 'weekly':
                    $defaults['sEventStartDate'] = DateTimeUtils::getDateRelativeTo(explode(' ', $startStr)[0], '+1 week');
                    break;
                case 'monthly':
                    $defaults['sEventStartDate'] = DateTimeUtils::getDateRelativeTo(explode(' ', $startStr)[0], '+1 month');
                    break;
                case 'yearly':
                    $defaults['sEventStartDate'] = DateTimeUtils::getDateRelativeTo(explode(' ', $startStr)[0], '+1 year');
                    break;
            }
            $defaults['sEventEndDate'] = $defaults['sEventStartDate'];

            return $defaults;
        }
    }

    // Fall back to type definition
    switch ($sDefRecurType) {
        case 'weekly':
            $defaults['sEventStartDate'] = DateTimeUtils::getRelativeDate("last $iDefRecurDOW");
            break;
        case 'monthly':
            $currentDOM = DateTimeUtils::getCurrentDay();
            $currentMonth = DateTimeUtils::getCurrentMonth();
            $currentYear = DateTimeUtils::getCurrentYear();
            if ($currentDOM < $iDefRecurDOM) {
                $defaults['sEventStartDate'] = DateTimeUtils::formatDateFromComponents($currentYear, $currentMonth - 1, $iDefRecurDOM);
            } else {
                $defaults['sEventStartDate'] = DateTimeUtils::formatDateFromComponents($currentYear, $currentMonth, $iDefRecurDOM);
            }
            break;
        case 'yearly':
            if (!empty($sDefRecurDOY)) {
                $defaults['sEventStartDate'] = $sDefRecurDOY;
            }
            break;
    }
    $defaults['sEventEndDate'] = $defaults['sEventStartDate'];

    return $defaults;
}

/**
 * Build attendance count rows for a given EventType (for new events).
 */
function buildCountsForType(int $typeId): array
{
    $countNames = EventCountNameQuery::create()
        ->filterByTypeId($typeId)
        ->orderById()
        ->find();

    $counts = [];
    foreach ($countNames as $cn) {
        $counts[] = [
            'id'    => (int) $cn->getId(),
            'name'  => $cn->getName(),
            'count' => 0,
        ];
    }

    return $counts;
}

/**
 * Build attendance count rows for an existing event.
 *
 * If the event already has EventCounts rows, return them.
 * Otherwise (event predates its type's categories, or counts were never
 * filled in), fall back to the type's defined count categories with zero
 * values so the user can fill them in directly from the editor.
 */
function buildCountsForEvent(int $eventId, int $typeId = 0): array
{
    $eventCounts = EventCountsQuery::create()
        ->filterByEvtcntEventid($eventId)
        ->orderByEvtcntCountid()
        ->find();

    $counts = [];
    foreach ($eventCounts as $ec) {
        $counts[] = [
            'id'    => (int) $ec->getEvtcntCountid(),
            'name'  => $ec->getEvtcntCountname(),
            'count' => (int) $ec->getEvtcntCountcount(),
            'notes' => $ec->getEvtcntNotes(),
        ];
    }

    // No counts saved yet for this event — show the type's defined categories
    // so the user can enter values directly.
    if (empty($counts) && $typeId > 0) {
        return buildCountsForType($typeId);
    }

    return $counts;
}

// GET /event/editor — show the editor (new or edit). Requires AddEvent permission.
$app->get('/editor[/{id}]', function (Request $request, Response $response, array $args) {
    $params = $request->getQueryParams();
    $eventId = (int) ($args['id'] ?? 0);
    $typeId = (int) ($params['typeId'] ?? 0);

    $event = null;
    $eventExists = false;
    $iTypeID = 0;
    $sTypeName = '';
    $sEventTitle = '';
    $sEventDesc = '';
    $sEventText = '';
    $iEventStatus = 0;
    $iLinkedGroupId = 0;
    $sCountNotes = '';
    $counts = [];
    $defaults = [];

    if ($eventId > 0) {
        // Edit mode
        $event = EventQuery::create()->leftJoinWithEventType()->findOneById($eventId);
        if ($event === null) {
            LoggerUtils::getAppLogger()->warning('Event not found: ' . $eventId);

            return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/dashboard')->withStatus(302);
        }

        $eventExists = true;
        $iTypeID = (int) $event->getType();
        $sTypeName = $event->getEventType() ? $event->getEventType()->getName() : '';
        $sEventTitle = $event->getTitle();
        $sEventDesc = $event->getDesc();
        $sEventText = $event->getText();
        $iEventStatus = (int) $event->getInActive();

        $eventStart = $event->getStart();
        $eventEnd = $event->getEnd();
        $defaults['sEventStartDate'] = $eventStart instanceof \DateTime ? $eventStart->format('Y-m-d') : substr((string) $eventStart, 0, 10);
        $defaults['iEventStartHour'] = $eventStart instanceof \DateTime ? $eventStart->format('H') : '09';
        $defaults['iEventStartMins'] = $eventStart instanceof \DateTime ? $eventStart->format('i') : '00';
        $defaults['sEventEndDate'] = $eventEnd instanceof \DateTime ? $eventEnd->format('Y-m-d') : substr((string) $eventEnd, 0, 10);
        $defaults['iEventEndHour'] = $eventEnd instanceof \DateTime ? $eventEnd->format('H') : '10';
        $defaults['iEventEndMins'] = $eventEnd instanceof \DateTime ? $eventEnd->format('i') : '00';

        $linkedGroups = $event->getGroups();
        if ($linkedGroups->count() > 0) {
            $iLinkedGroupId = (int) $linkedGroups->getFirst()->getId();
        }

        $counts = buildCountsForEvent($eventId, $iTypeID);
        if (!empty($counts) && isset($counts[0]['notes'])) {
            $sCountNotes = $counts[0]['notes'];
        }
    } elseif ($typeId > 0) {
        // New event with smart-prefill from type
        $defaults = computeEventDefaults($typeId);
        $iTypeID = $defaults['iTypeID'];
        $sTypeName = $defaults['sTypeName'];
        $counts = buildCountsForType($typeId);
        $sEventTitle = $defaults['sEventStartDate'] . '-' . $sTypeName;
    } else {
        // Brand new event with no type yet — show type selector
        $defaults = [
            'sEventStartDate' => DateTimeUtils::getTodayDate(),
            'sEventEndDate'   => DateTimeUtils::getTodayDate(),
            'iEventStartHour' => '09',
            'iEventStartMins' => '00',
            'iEventEndHour'   => '10',
            'iEventEndMins'   => '00',
        ];
    }

    // Show the linked-group selector for any event type so users can link any
    // event to a group for Kiosk check-in, not only Sunday School types.
    $showLinkedGroup = $iTypeID > 0;

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'editor.php', [
        'sRootPath'       => SystemURLs::getRootPath(),
        'sPageTitle'      => gettext('Church Event Editor'),
        'sPageSubtitle'   => gettext('Create and manage church events and activities'),
        'aBreadcrumbs'    => PageHeader::breadcrumbs([
            [gettext('Events'), '/event/dashboard'],
            [$eventExists ? gettext('Edit Event') : gettext('Create Event')],
        ]),
        'eventId'         => $eventId,
        'eventExists'     => $eventExists,
        'iTypeID'         => $iTypeID,
        'sTypeName'       => $sTypeName,
        'sEventTitle'     => $sEventTitle,
        'sEventDesc'      => $sEventDesc,
        'sEventText'      => $sEventText,
        'iEventStatus'    => $iEventStatus,
        'iLinkedGroupId'  => $iLinkedGroupId,
        'showLinkedGroup' => $showLinkedGroup,
        'counts'          => $counts,
        'sCountNotes'     => $sCountNotes,
        'defaults'        => $defaults,
        'eventTypes'      => EventTypeQuery::create()->orderByName()->find(),
        'groups'          => GroupQuery::create()->orderByName()->find(),
    ]);
})->add(new AddEventsRoleAuthMiddleware());

// POST /event/editor — save event (new or update). Requires AddEvent permission.
$app->post('/editor', function (Request $request, Response $response) {
    $body = $request->getParsedBody();

    $iEventID = (int) ($body['eventId'] ?? 0);
    $iTypeID = (int) ($body['typeId'] ?? 0);
    $eventExists = (int) ($body['EventExists'] ?? 0);

    if ($iTypeID <= 0) {
        // Type not selected — bounce back to selector
        return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/editor')->withStatus(302);
    }

    $sEventTitle = InputUtils::legacyFilterInput($body['EventTitle'] ?? '');
    $sEventDesc = InputUtils::sanitizeHTML($body['EventDescInput'] ?? '');
    $sEventText = InputUtils::sanitizeHTML($body['EventTextInput'] ?? '');
    $iEventStatus = (int) ($body['EventStatus'] ?? 0);
    $iLinkedGroupId = (int) ($body['LinkedGroupId'] ?? 0);
    $sCountNotes = InputUtils::legacyFilterInput($body['EventCountNotes'] ?? '');

    // Parse date range "YYYY-MM-DD h:mm A - YYYY-MM-DD h:mm A"
    $sEventRange = $body['EventDateRange'] ?? '';
    $rangeParts = explode(' - ', $sEventRange);
    if (count($rangeParts) !== 2) {
        return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/editor')->withStatus(302);
    }
    $startDt = \DateTime::createFromFormat('Y-m-d H:i a', $rangeParts[0]);
    $endDt = \DateTime::createFromFormat('Y-m-d H:i a', $rangeParts[1]);
    if (!$startDt || !$endDt) {
        $_SESSION['sGlobalMessage'] = gettext('Invalid date format.');
        $_SESSION['sGlobalMessageClass'] = 'danger';
        $back = SystemURLs::getRootPath() . '/event/editor';
        if ($iEventID > 0) {
            $back .= '/' . $iEventID;
        }

        return $response->withHeader('Location', $back)->withStatus(302);
    }

    // Server-side date order validation (#6629)
    if ($endDt < $startDt) {
        $_SESSION['sGlobalMessage'] = gettext('Event end date/time must be on or after the start date/time.');
        $_SESSION['sGlobalMessageClass'] = 'danger';
        $back = SystemURLs::getRootPath() . '/event/editor';
        if ($iEventID > 0) {
            $back .= '/' . $iEventID;
        }

        return $response->withHeader('Location', $back)->withStatus(302);
    }

    $sEventStart = $startDt->format('Y-m-d H:i');
    $sEventEnd = $endDt->format('Y-m-d H:i');

    // Save event (create or update)
    if ($eventExists === 0) {
        $event = new Event();
    } else {
        $event = EventQuery::create()->findOneById($iEventID);
        if ($event === null) {
            return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/dashboard')->withStatus(302);
        }
    }

    $event
        ->setType($iTypeID)
        ->setTitle($sEventTitle)
        ->setDesc($sEventDesc)
        ->setText($sEventText)
        ->setStart($sEventStart)
        ->setEnd($sEventEnd)
        ->setInActive($iEventStatus);
    $event->save();
    $event->reload();

    $iEventID = (int) $event->getId();

    // Manage linked group via EventAudience (Propel ORM)
    EventAudienceQuery::create()->filterByEventId($iEventID)->delete();
    if ($iLinkedGroupId > 0) {
        $eventAudience = new EventAudience();
        $eventAudience->setEventId($iEventID);
        $eventAudience->setGroupId($iLinkedGroupId);
        $eventAudience->save();
    }

    // Save attendance counts via Propel (replaces ON DUPLICATE KEY UPDATE)
    $aEventCount = $body['EventCount'] ?? [];
    $aEventCountID = $body['EventCountID'] ?? [];
    $aEventCountName = $body['EventCountName'] ?? [];
    $numCounts = (int) ($body['NumAttendCounts'] ?? 0);

    for ($c = 0; $c < $numCounts; $c++) {
        $countId = (int) ($aEventCountID[$c] ?? 0);
        $countName = $aEventCountName[$c] ?? '';
        $countValue = (int) ($aEventCount[$c] ?? 0);

        // Composite primary key (eventId, countId) — findPk + create-if-missing
        $eventCount = EventCountsQuery::create()->findPk([$iEventID, $countId]);
        if ($eventCount === null) {
            $eventCount = new EventCounts();
            $eventCount->setEvtcntEventid($iEventID);
            $eventCount->setEvtcntCountid($countId);
        }
        $eventCount->setEvtcntCountname($countName);
        $eventCount->setEvtcntCountcount($countValue);
        $eventCount->setEvtcntNotes($sCountNotes);
        $eventCount->save();
    }

    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/event/dashboard')
        ->withStatus(302);
})->add(new AddEventsRoleAuthMiddleware());
