<?php

use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalTwig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

/**
 * The volunteer's own two pages, inside the portal (MP6, #9867; Member Portal
 * design §5.4 / P15, Volunteer v2 design §5.6).
 *
 * They used to be `/volunteer/my-schedule` and `/volunteer/opportunities`,
 * rendered by `src/volunteer/routes/member.php` into the ADMIN shell — which is
 * exactly the thing the portal exists to stop a member ever seeing. Only the
 * chrome moved: the templates reproduce the container ids the two existing
 * bundles look for, and `webpack/volunteer/{my-schedule,opportunities,member-ui}.ts`
 * and `/api/ministries/me/*` are untouched.
 *
 * **No role gate here either, and for the same reason as before** (volunteer
 * design §3.2): every authenticated person is potentially a volunteer, so a gate
 * would lock out precisely the people the pages are for. What does apply is the
 * rollout flag — `PortalNav::isVolunteeringVisible()`, which is also what decides
 * whether the nav offers the entry, so the menu and the route can never disagree.
 * Everything on the pages comes from `/api/ministries/me/*`, where the acting
 * person is the session and no route accepts a `personId`.
 */

/** Refuse both pages when V2 is off (or the portal's volunteering switch is). */
$requireVolunteering = function (Request $request): void {
    if (!PortalNav::isVolunteeringVisible()) {
        // A 404 rather than a 403: with the feature off the page does not exist,
        // and the portal's own 404 template renders it in the member's theme.
        throw new HttpNotFoundException($request);
    }
};

// GET /portal/volunteer/schedule — "My schedule" (was /volunteer/my-schedule).
$group->get('/volunteer/schedule', function (Request $request, Response $response) use ($requireVolunteering): Response {
    $requireVolunteering($request);

    return PortalTwig::render(
        $response,
        'volunteer/schedule.html.twig',
        [
            'pageTitle' => gettext('My schedule'),
            'activeTab' => 'schedule',
        ],
        PortalNav::VOLUNTEER
    );
});

// GET /portal/volunteer/opportunities — "Find something to do".
$group->get('/volunteer/opportunities', function (Request $request, Response $response) use ($requireVolunteering): Response {
    $requireVolunteering($request);

    return PortalTwig::render(
        $response,
        'volunteer/opportunities.html.twig',
        [
            'pageTitle' => gettext('Find something to do'),
            'activeTab' => 'opportunities',
        ],
        PortalNav::VOLUNTEER
    );
});
