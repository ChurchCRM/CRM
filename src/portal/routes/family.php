<?php

use ChurchCRM\data\Countries;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalTwig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;

// My Family — the member's own family record (design §5.2, issue #9865).
//
// The family is always the acting member's family: it is read from the person
// the session resolves to, never from the URL. Editing is offered only to the
// family's adults — the audience the verify flow addresses — and everybody
// else sees the same page read-only.

/**
 * The family view-model for the acting member, or a 404 when the account has
 * no person record or the person has no family.
 *
 * @return array<string, mixed>
 */
$portalFamilyView = static function (Request $request): array {
    $person = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);
    if (!$person instanceof Person) {
        throw new HttpNotFoundException(
            $request,
            gettext('Your account is not linked to a person record. Please contact the church office.')
        );
    }

    $view = PortalSelfService::getFamilyView($person);
    if ($view['family'] === null) {
        throw new HttpNotFoundException($request, gettext('You are not part of a family record yet.'));
    }

    return $view;
};

// GET /portal/family — the address card, the members list and the two actions.
$group->get('/family', function (Request $request, Response $response) use ($portalFamilyView): Response {
    return PortalTwig::render(
        $response,
        'family/index.html.twig',
        array_merge(
            [
                'pageTitle' => gettext('My Family'),
                // Only the adults see the "add a family member" dialog, but the
                // role list is cheap and a theme may put it elsewhere.
                'familyRoles' => PortalSelfService::getFamilyRoles(),
                'defaultNewMemberRoleId' => PortalSelfService::getDefaultNewMemberRoleId(),
            ],
            $portalFamilyView($request)
        ),
        PortalNav::FAMILY
    );
});

// GET /portal/family/edit — the form that POSTs to /api/portal/family.
$group->get('/family/edit', function (Request $request, Response $response) use ($portalFamilyView): Response {
    $view = $portalFamilyView($request);
    if (!$view['canEdit']) {
        throw new HttpForbiddenException(
            $request,
            gettext('Only an adult of your family can change these details. Please contact the church office.')
        );
    }

    return PortalTwig::render(
        $response,
        'family/edit.html.twig',
        array_merge(
            [
                'pageTitle' => gettext('Update your family details'),
                // A plain <select> rather than a searchable one: the portal is
                // mobile-first and the native picker is the better control on a
                // phone — and it is not subject to the 50-option cap that bites
                // the admin side's dropdowns.
                'countries' => Countries::getNames(),
            ],
            $view
        ),
        PortalNav::FAMILY
    );
});

// GET /portal/family/confirm — "are these details still right?", the portal's
// door into the same flow the emailed verify link opens.
$group->get('/family/confirm', function (Request $request, Response $response) use ($portalFamilyView): Response {
    return PortalTwig::render(
        $response,
        'family/confirm.html.twig',
        array_merge(['pageTitle' => gettext('Confirm your family details')], $portalFamilyView($request)),
        PortalNav::FAMILY
    );
});
