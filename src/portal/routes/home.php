<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\PortalCalendarService;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Utils\DateTimeUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// GET /portal — the member's landing page (design §5.1).
//
// MP2 shipped the welcome card and clearly-marked placeholders for the sections
// later issues fill in; MP5 (#9866) turned the calendar placeholder into the
// real thing — the next three events from the calendars the church shares — and
// MP4 (#9865) did the same for My Family and Profile. Volunteering (MP6) is the
// one placeholder left. Themes typically override this page first.
$homeHandler = function (Request $request, Response $response): Response {
    $actor = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);
    $family = $actor instanceof Person ? $actor->getFamily() : null;

    return PortalTwig::render(
        $response,
        'home.html.twig',
        [
            'pageTitle' => gettext('Home'),
            // The Profile card shows the member's own contact details rather
            // than a sentence describing them, so it reads the same view-model
            // the Profile page renders — never a second copy of the logic.
            'profile' => $actor instanceof Person ? PortalSelfService::getProfile($actor) : null,
            'familySummary' => $family === null ? null : [
                'name' => (string) $family->getName(),
                'memberCount' => count($family->getPeople()),
            ],
            'upcomingEvents' => portalHomeUpcomingEvents(),
            'hasVisibleCalendars' => SystemConfig::getBooleanValue('bPortalShowCalendar')
                && PortalCalendarService::hasVisibleCalendars(),
        ],
        PortalNav::HOME
    );
};

/**
 * The next three events from the calendars the Member Portal shares, formatted
 * for the home page card. Dates are formatted here rather than in the template
 * so a church theme gets a string it can print, not a date it has to reason
 * about (design §3.6).
 *
 * @return array<int, array{title: string, when: string, calendarName: string}>
 */
function portalHomeUpcomingEvents(): array
{
    if (!SystemConfig::getBooleanValue('bPortalShowCalendar')) {
        return [];
    }

    $events = [];
    foreach (PortalCalendarService::upcoming(3) as $event) {
        $events[] = [
            'title' => (string) $event['title'],
            'when' => DateTimeUtils::formatDate((string) $event['start'], !$event['allDay']),
            'calendarName' => (string) ($event['extendedProps']['calendarName'] ?? ''),
        ];
    }

    return $events;
}

// Both spellings answer, so a link to `/portal` works as well as `/portal/`.
$group->get('', $homeHandler);
$group->get('/', $homeHandler);
