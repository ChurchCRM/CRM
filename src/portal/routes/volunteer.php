<?php

use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\RequirePortalCapability;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalTwig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * The volunteer's own two pages, inside the portal (MP6, #9867; Member Portal
 * design §5.4 / P15, Volunteer v2 design §5.6).
 *
 * The templates reproduce the container ids that
 * `webpack/portal/volunteer-{schedule,opportunities}.ts` look for.
 *
 * Both need `portal.volunteer.use`, which every signed-in person holds while
 * Volunteer v2 and the portal's volunteering switch are on (member sign-in
 * design §8.4); with either off the pages do not exist and answer 404. The nav
 * entry asks the same capability. Everything on the pages comes from
 * `/api/ministries/me/*`, where the acting person is the session and no route
 * accepts a `personId`.
 */

// GET /portal/volunteer/schedule — "My schedule" (was /volunteer/my-schedule).
$group->get('/volunteer/schedule', function (Request $request, Response $response): Response {
    return PortalTwig::render(
        $response,
        'volunteer/schedule.html.twig',
        [
            'pageTitle' => gettext('My schedule'),
            'activeTab' => 'schedule',
        ],
        PortalNav::VOLUNTEER
    );
})->add(RequirePortalCapability::any(CorePortalCapabilities::VOLUNTEER_USE));

// GET /portal/volunteer/opportunities — "Find something to do".
$group->get('/volunteer/opportunities', function (Request $request, Response $response): Response {
    return PortalTwig::render(
        $response,
        'volunteer/opportunities.html.twig',
        [
            'pageTitle' => gettext('Find something to do'),
            'activeTab' => 'opportunities',
        ],
        PortalNav::VOLUNTEER
    );
})->add(RequirePortalCapability::any(CorePortalCapabilities::VOLUNTEER_USE));
