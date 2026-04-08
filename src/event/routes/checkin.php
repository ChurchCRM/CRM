<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /event/checkin — display the check-in page
$app->get('/checkin[/{eventId}]', function (Request $request, Response $response, array $args) {
    $params = $request->getQueryParams();

    $eventId = (int) ($args['eventId'] ?? $params['EventID'] ?? 0);
    $eventTypeId = (int) ($params['EventTypeID'] ?? 0);
    $directEventAccess = $eventId > 0;
    $addedCount = (int) ($params['AddedCount'] ?? 0);

    // Get all active event types for the filter
    $eventTypes = EventTypeQuery::create()
        ->filterByActive(true)
        ->orderByName()
        ->find();

    // Build active events query with optional type filter
    $activeEventsQuery = EventQuery::create()
        ->filterByInActive(1, Criteria::NOT_EQUAL)
        ->orderByStart(Criteria::DESC);

    if ($eventTypeId > 0 && !$directEventAccess) {
        $eventType = EventTypeQuery::create()->findOneById($eventTypeId);
        if ($eventType) {
            $activeEventsQuery->filterByEventType($eventType);
        }
    }

    $activeEvents = $activeEventsQuery->find();

    $event = null;
    $attendees = [];
    if ($eventId > 0) {
        $event = EventQuery::create()->findOneById($eventId);
        if ($event !== null) {
            $eventAttendees = EventAttendQuery::create()
                ->filterByEventId($eventId)
                ->find();

            foreach ($eventAttendees as $att) {
                $person = PersonQuery::create()->findOneById($att->getPersonId());
                if ($person === null) {
                    continue;
                }

                $checkinByName = '';
                if ($att->getCheckinId()) {
                    $checkinBy = PersonQuery::create()->findOneById($att->getCheckinId());
                    $checkinByName = $checkinBy ? $checkinBy->getFullName() : '';
                }

                $checkoutByName = '';
                if ($att->getCheckoutId()) {
                    $checkoutBy = PersonQuery::create()->findOneById($att->getCheckoutId());
                    $checkoutByName = $checkoutBy ? $checkoutBy->getFullName() : '';
                }

                $attendees[] = [
                    'personId'       => $att->getPersonId(),
                    'fullName'       => $person->getFullName(),
                    'familyId'       => $person->getFamId(),
                    'checkinDate'    => $att->getCheckinDate() ? date_format($att->getCheckinDate(), SystemConfig::getValue('sDateTimeFormat')) : null,
                    'checkinBy'      => $checkinByName,
                    'checkoutDate'   => $att->getCheckoutDate() ? date_format($att->getCheckoutDate(), SystemConfig::getValue('sDateTimeFormat')) : null,
                    'checkoutBy'     => $checkoutByName,
                    'isCheckedOut'   => $att->getCheckoutDate() !== null,
                    'inCart'         => isset($_SESSION['aPeopleCart']) && in_array($att->getPersonId(), $_SESSION['aPeopleCart'], false),
                ];
            }
        }
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'checkin.php', [
        'sRootPath'         => SystemURLs::getRootPath(),
        'sPageTitle'        => gettext('Event Check-in'),
        'sPageSubtitle'     => gettext('Check in attendees for church events and activities'),
        'aBreadcrumbs'      => PageHeader::breadcrumbs([
            [gettext('Events'), '/ListEvents.php'],
            [gettext('Check-in')],
        ]),
        'eventId'           => $eventId,
        'event'             => $event,
        'eventTypeId'       => $eventTypeId,
        'eventTypes'        => $eventTypes,
        'activeEvents'      => $activeEvents,
        'attendees'         => $attendees,
        'directEventAccess' => $directEventAccess,
        'addedCount'        => $addedCount,
    ]);
});
