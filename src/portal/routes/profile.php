<?php

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalTwig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

// Profile — the member's own person record (design §5.2, issue #9865).
//
// Both pages read through PortalSelfService, which is also what
// `GET /api/portal/me` answers with, so the server-rendered page and the
// values the form re-renders after a save can never disagree. The acting
// person comes from the request attribute PortalAccessMiddleware set from the
// session; no route here takes a person id.

/**
 * The acting member's person record, or a 404 for an account with none.
 */
$portalActor = static function (Request $request): Person {
    $person = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);
    if (!$person instanceof Person) {
        throw new HttpNotFoundException(
            $request,
            gettext('Your account is not linked to a person record. Please contact the church office.')
        );
    }

    return $person;
};

// GET /portal/profile — read-only view of the member's own details.
$group->get('/profile', function (Request $request, Response $response) use ($portalActor): Response {
    return PortalTwig::render(
        $response,
        'profile/index.html.twig',
        [
            'pageTitle' => gettext('Profile'),
            'profile' => PortalSelfService::getProfile($portalActor($request)),
        ],
        PortalNav::PROFILE
    );
});

// GET /portal/profile/edit — the form that POSTs to /api/portal/me.
$group->get('/profile/edit', function (Request $request, Response $response) use ($portalActor): Response {
    return PortalTwig::render(
        $response,
        'profile/edit.html.twig',
        [
            'pageTitle' => gettext('Update your details'),
            'profile' => PortalSelfService::getProfile($portalActor($request)),
        ],
        PortalNav::PROFILE
    );
});
