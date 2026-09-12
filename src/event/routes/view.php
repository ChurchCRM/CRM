<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventCountsQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\Service\VolunteerAssignmentService;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
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
    $groups = $event->getGroups();
    $linkedGroups = [];
    foreach ($groups as $group) {
        $linkedGroups[] = [
            'id'   => (int) $group->getId(),
            'name' => $group->getName(),
        ];
    }

    // Compute non-attendees (group members who did not check in), shown only after event ends
    $eventEnd = $event->getEnd();
    $eventEnded = $eventEnd !== null && $eventEnd < new DateTime();
    $nonAttendees = [];
    if ($eventEnded && $groups->count() > 0) {
        $members = PersonQuery::create()
            ->joinWithPerson2group2roleP2g2r()
            ->usePerson2group2roleP2g2rQuery()
                ->filterByGroup($groups)
            ->endUse()
            ->leftJoinEventAttend()
            ->addJoinCondition('EventAttend', 'event_attend.event_id = ?', $event->getId())
            ->where('event_attend.event_id IS NULL')
            ->groupBy('Person.Id')
            ->find();

        foreach ($members as $person) {
            $nonAttendees[] = [
                    'personId'  => $person->getId(),
                    'fullName'  => $person->getFullName(),
                    'email'     => $person->getEmail(),
                    'cellPhone' => $person->getCellPhone(),
                    'homePhone' => $person->getHomePhone(),
                ];
        }
        usort($nonAttendees, fn ($a, $b) => strcmp($a['fullName'], $b['fullName']));
    }

    // Volunteer v2 staffing for this event (#9713, design §3.5).
    //
    // Gated twice: on the rollout flag, and on scope — `getEventStaffingSummary()` returns
    // nothing at all unless the caller may manage at least one occurrence linked to this
    // event, so a volunteer, a plain member or an events administrator with no ministry
    // scope simply never sees the card. Read-only; the edit affordance is the link to
    // /volunteer/occurrences/{id}.
    $volunteerOccurrences = [];
    if (User::isVolunteerV2Enabled()) {
        $assignments = new VolunteerAssignmentService();
        $summary = $assignments->getEventStaffingSummary([$eventId], AuthenticationManager::getCurrentUser());
        $occurrenceIds = $summary[$eventId]['occurrenceIds'] ?? [];

        if ($occurrenceIds !== []) {
            // One getGaps() call for every occurrence at once — §2.11.3's single gap
            // implementation, never re-derived here.
            $gaps = $assignments->getGaps($occurrenceIds);

            $occurrences = VolunteerOccurrenceQuery::create()
                ->filterById($occurrenceIds, Criteria::IN)
                ->orderByOccurrenceDate()
                ->find();

            // A handful of rows at most — an event carries one occurrence per ministry
            // scheduling it (UC3), not one per volunteer.
            foreach ($occurrences as $occurrence) {
                $schedule = VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());
                $ministry = $schedule === null
                    ? null
                    : VolunteerMinistryQuery::create()->findPk((int) $schedule->getMinistryId());
                $summaryRow = $gaps[(int) $occurrence->getId()] ?? [
                    'gapCount' => 0,
                    'liveCount' => 0,
                    'requiredCount' => 0,
                ];

                $volunteerOccurrences[] = [
                    'occurrenceId'  => (int) $occurrence->getId(),
                    'ministryName'  => $ministry === null ? gettext('Volunteer') : $ministry->getName(),
                    'scheduleName'  => $schedule === null ? '' : $schedule->getName(),
                    'liveCount'     => (int) $summaryRow['liveCount'],
                    'requiredCount' => (int) $summaryRow['requiredCount'],
                    'gapCount'      => (int) $summaryRow['gapCount'],
                ];
            }
        }
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
        'eventEnded'     => $eventEnded,
        'nonAttendees'   => $nonAttendees,
        'emailEnabled'   => SystemConfig::isEmailEnabled(),
        'volunteerOccurrences' => $volunteerOccurrences,
    ]);
});
