<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventCountsQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// GET /event/view/{id} — read-only event detail page (no edit permission required)
$app->get('/view/{id}', function (Request $request, Response $response, array $args) {
    $eventId = (int) $args['id'];

    $event = EventQuery::create()->leftJoinWithEventType()->findOneById($eventId);
    if ($event === null) {
        LoggerUtils::getAppLogger()->warning('Event not found in view route: ' . $eventId);

        return $response->withHeader('Location', SystemURLs::getRootPath() . '/event/dashboard')->withStatus(302);
    }

    // Build attendance roster
    $attendees = [];
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
            'personId'     => $att->getPersonId(),
            'fullName'     => $person->getFullName(),
            'familyId'     => $person->getFamId(),
            'checkinDate'  => $att->getCheckinDate() ? date_format($att->getCheckinDate(), SystemConfig::getValue('sDateTimeFormat')) : null,
            'checkinBy'    => $checkinByName,
            'checkoutDate' => $att->getCheckoutDate() ? date_format($att->getCheckoutDate(), SystemConfig::getValue('sDateTimeFormat')) : null,
            'checkoutBy'   => $checkoutByName,
            'isCheckedOut' => $att->getCheckoutDate() !== null,
        ];
    }

    // Attendance counts
    $eventCounts = EventCountsQuery::create()
        ->filterByEvtcntEventid($eventId)
        ->orderByEvtcntCountid()
        ->find();
    $counts = [];
    foreach ($eventCounts as $count) {
        $counts[] = [
            'name'  => $count->getEvtcntCountname(),
            'count' => (int) $count->getEvtcntCountcount(),
        ];
    }

    // Linked groups (audience)
    $linkedGroups = [];
    foreach ($event->getGroups() as $group) {
        $linkedGroups[] = [
            'id'   => (int) $group->getId(),
            'name' => $group->getName(),
        ];
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'view.php', [
        'sRootPath'      => SystemURLs::getRootPath(),
        'sPageTitle'     => $event->getTitle() ?: gettext('Event'),
        'sPageSubtitle'  => $event->getEventType() ? $event->getEventType()->getName() : gettext('Event Details'),
        'aBreadcrumbs'   => PageHeader::breadcrumbs([
            [gettext('Events'), '/event/dashboard'],
            [$event->getTitle() ?: gettext('Event Details')],
        ]),
        'event'          => $event,
        'attendees'      => $attendees,
        'counts'         => $counts,
        'linkedGroups'   => $linkedGroups,
        'canEditEvents'  => AuthenticationManager::getCurrentUser()->isAddEvent(),
    ]);
});
