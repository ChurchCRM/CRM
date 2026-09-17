<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\PortalAccountPages;
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

// The account pages: password and two-factor, owned by the portal so that every
// role — member, staff, administrator, and a masquerading administrator — stays
// inside the portal. The admin console is reached from the staff bar's "Admin
// Console" control and nowhere else. See PortalAccountPages for why the older
// /v2/user/current/* URLs still exist alongside these.
//
// These two act on the signed-in *account*, not on a person record, so unlike
// the pages above they do not resolve $portalActor: somebody whose account has
// no person record linked must still be able to change their own password.

// GET /portal/profile/password — the form.
$group->get('/profile/password', function (Request $request, Response $response): Response {
    return PortalAccountPages::renderPasswordForm($response, PortalAccountPages::getPasswordUrl());
});

// POST /portal/profile/password — the change itself. CSRF is already enforced
// for every portal route by the group middleware in src/portal/index.php.
//
// Both outcomes stay in the portal and on this URL: a success renders the
// confirmation page, and a rejected password re-renders this form carrying the
// error, exactly as the /v2 page does for a self-service session.
$group->post('/profile/password', function (Request $request, Response $response): Response {
    $user = AuthenticationManager::getCurrentUser();

    try {
        PortalAccountPages::applyPasswordChange($user, $request->getParsedBody());
    } catch (PasswordChangeException $passwordChangeException) {
        $isOldPassword = $passwordChangeException->AffectedPassword === 'Old';

        return PortalAccountPages::renderPasswordForm(
            $response,
            PortalAccountPages::getPasswordUrl(),
            $isOldPassword ? $passwordChangeException->getMessage() : '',
            $isOldPassword ? '' : $passwordChangeException->getMessage()
        );
    }

    return PortalAccountPages::renderPasswordChanged($response);
});

// GET /portal/profile/two-factor — enrollment and recovery codes, drawn by the
// shared two-factor-enrollment bundle against /api/user/current/*.
$group->get('/profile/two-factor', function (Request $request, Response $response): Response {
    return PortalAccountPages::renderTwoFactor($response);
});
