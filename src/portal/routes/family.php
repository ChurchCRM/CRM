<?php

use ChurchCRM\data\Countries;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\PortalNav;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalTwig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;

// My Family — the member's own family record (design §5.2, issue #9865).
//
// The family is always the acting member's family: it is read from the person
// the session resolves to, never from the URL. Editing is offered only to the
// family's adults — the audience the verify flow addresses — and everybody
// else sees the same page read-only.

/**
 * The family view-model for the acting member, or null when the account has no
 * person record or the person has no family.
 *
 * Both of those are facts about the member's record, not broken links, so they
 * are answered with `family/none.html.twig` rather than the portal's 404: a
 * member who reads "This page was not found" has no way to tell that the church
 * simply has not put them in a household yet.
 *
 * @return array<string, mixed>|null
 */
$portalFamilyView = static function (Request $request): ?array {
    $person = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);
    if (!$person instanceof Person) {
        return null;
    }

    $view = PortalSelfService::getFamilyView($person);

    return $view['family'] === null ? null : $view;
};

/**
 * "You are not currently associated with a family", with the church office's
 * own contact details so the member can do something about it.
 */
$portalFamilyNone = static function (Response $response): Response {
    return PortalTwig::render(
        $response,
        'family/none.html.twig',
        array_merge(
            ['pageTitle' => gettext('You are not currently associated with a family')],
            portalFamilyOfficeContact()
        ),
        PortalNav::FAMILY
    );
};

/**
 * The church office's contact details, in the two forms the page needs: the
 * number as the church wrote it, and the number a `tel:` link can dial.
 *
 * Either may be empty — a brand-new install has neither — and the template
 * leaves that line out rather than offering a link to nowhere.
 *
 * @return array<string, string>
 */
function portalFamilyOfficeContact(): array
{
    $phone = trim(ChurchMetaData::getChurchPhone());
    // `tel:` takes digits and, for an international number, a leading plus.
    // Everything the church typed for legibility — spaces, dashes, brackets,
    // dots — is punctuation, not part of the number.
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    return [
        'officeEmail' => trim(ChurchMetaData::getChurchEmail()),
        'officePhone' => $phone,
        'officePhoneHref' => $digits === '' ? '' : (str_starts_with($phone, '+') ? '+' : '') . $digits,
    ];
}

// GET /portal/family — the address card, the members list and the two actions.
$group->get('/family', function (Request $request, Response $response) use ($portalFamilyView, $portalFamilyNone): Response {
    $view = $portalFamilyView($request);
    if ($view === null) {
        return $portalFamilyNone($response);
    }

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
            $view
        ),
        PortalNav::FAMILY
    );
});

// GET /portal/family/edit — the form that POSTs to /api/portal/family.
$group->get('/family/edit', function (Request $request, Response $response) use ($portalFamilyView, $portalFamilyNone): Response {
    $view = $portalFamilyView($request);
    if ($view === null) {
        return $portalFamilyNone($response);
    }

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
$group->get('/family/confirm', function (Request $request, Response $response) use ($portalFamilyView, $portalFamilyNone): Response {
    $view = $portalFamilyView($request);
    if ($view === null) {
        return $portalFamilyNone($response);
    }

    return PortalTwig::render(
        $response,
        'family/confirm.html.twig',
        array_merge(['pageTitle' => gettext('Confirm your family details')], $view),
        PortalNav::FAMILY
    );
});
