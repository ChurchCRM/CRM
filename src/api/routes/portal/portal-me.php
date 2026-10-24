<?php

use ChurchCRM\dto\Photo;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\Auth\PortalSessionMiddleware;
use ChurchCRM\Portal\MemberLanguage;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\RequirePortalCapability;
use ChurchCRM\Portal\PortalApiMiddleware;
use ChurchCRM\Portal\PortalExtension;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Portal\PortalSelfServiceException;
use ChurchCRM\Slim\Middleware\Api\FamilyEmailDeprecationMiddleware;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\HttpCache\Cache;
use Slim\Routing\RouteCollectorProxy;

/*
 * `/api/portal/*` — the Member Portal's own API (design §5.2, issue #9865).
 *
 * Four properties hold for every route in this file, and they are the reason
 * the portal does not reuse `/api/person` and `/api/family` for writes:
 *
 *  - **The actor is the session.** `PortalApiMiddleware` puts the portal
 *    session's person on the request. No route takes a
 *    `personId` or a `familyId`, so there is no id to tamper with: pointing
 *    one of these endpoints at somebody else is not a permission failure, it
 *    is unexpressible (design P11).
 *  - **Session only.** `PortalSessionMiddleware` refuses an API key outright.
 *  - **CSRF on every write**, because these are browser requests carrying a
 *    cookie; the portal's forms embed the token and the bundles send it back
 *    in `X-CSRF-Token`.
 *  - **A field allow-list.** `InputSanitizationMiddleware` cleans the fields
 *    the portal knows about, and `PortalSelfService` reads only those. Any
 *    other key in the body is ignored rather than rejected, so an old client
 *    sending an extra field still works and a new column cannot be written by
 *    guessing its name.
 */

/** The fields `POST /api/portal/me` accepts, and how each is sanitized. */
$portalPersonFields = [
    'title' => 'text',
    'firstName' => 'text',
    'middleName' => 'text',
    'lastName' => 'text',
    'suffix' => 'text',
    'email' => 'text',
    'workEmail' => 'text',
    'homePhone' => 'text',
    'cellPhone' => 'text',
    'workPhone' => 'text',
    // An ISO date from <input type="date">; PortalSelfService rejects anything
    // that is not a real calendar day. The sanitizer has no 'date' type yet, so
    // 'text' does the tag-stripping and the service does the parsing.
    'birthday' => 'text',
    // A locale from src/locale/locales.json, or empty for the church's language.
    'language' => 'text',
];

/** The fields `POST /api/portal/family` accepts. */
$portalFamilyFields = [
    'address1' => 'text',
    'address2' => 'text',
    'city' => 'text',
    'state' => 'text',
    'zip' => 'text',
    'country' => 'text',
    'homePhone' => 'text',
    'email' => 'text',
    'weddingDate' => 'text',
];

/** The fields `POST /api/portal/family/members` accepts. */
$portalNewMemberFields = [
    'firstName' => 'text',
    'lastName' => 'text',
    'role' => 'text',
    'birthday' => 'text',
];

/** The fields `POST /api/portal/family/confirm` accepts. */
$portalConfirmFields = [
    'result' => 'text',
    'comment' => 'text',
];

/**
 * The acting person, put on the request by PortalApiMiddleware.
 */
$portalActor = static function (Request $request): Person {
    /** @var Person $person */
    $person = $request->getAttribute(PortalSelfService::ACTOR_ATTRIBUTE);

    return $person;
};

/**
 * Turn a self-service refusal into the JSON shape the portal bundles read:
 * `{success: false, message, failures: []}` with the status the service chose
 * (400 for "fix what you typed", 403 for "not your place to change this").
 */
$portalError = static function (Response $response, PortalSelfServiceException $e): Response {
    return SlimUtils::renderErrorJSON(
        $response,
        $e->getMessage(),
        ['failures' => $e->getFailures()],
        $e->getStatus()
    );
};

/**
 * Serve a person's uploaded photo exactly as `/api/person/{id}/photo` does —
 * the same bytes, the same content type from the same `Photo` object — or the
 * same 404 when nobody has uploaded one, which is what tells the templates to
 * render initials instead of a broken image.
 *
 * A missing photo and a person the caller may not see are deliberately the
 * same answer: see `PortalSelfService::findFamilyMember()`.
 */
$portalPhoto = static function (Response $response, int $personId): Response {
    $photo = new Photo('Person', $personId);

    if (!$photo->hasUploadedPhoto()) {
        return SlimUtils::renderErrorJSON($response, gettext('No photo has been uploaded for this person.'), [], 404);
    }

    return SlimUtils::renderPhoto($response, $photo);
};

$app->group('/portal', function (RouteCollectorProxy $group) use (
    $portalPersonFields,
    $portalFamilyFields,
    $portalNewMemberFields,
    $portalConfirmFields,
    $portalActor,
    $portalError,
    $portalPhoto
): void {
    /**
     * @OA\Get(
     *     path="/portal/me",
     *     operationId="getPortalMe",
     *     summary="The signed-in member's own profile",
     *     description="Returns the acting member's person record as the Member Portal renders it. The member is taken from the session; there is no person id to pass. Session only — API keys are refused.",
     *     tags={"Member Portal"},
     *     @OA\Response(response=200, description="The member's profile",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="profile", type="object",
     *                 @OA\Property(property="id", type="integer", example=100),
     *                 @OA\Property(property="firstName", type="string", example="Lena"),
     *                 @OA\Property(property="lastName", type="string", example="Black"),
     *                 @OA\Property(property="email", type="string", example="lena@example.com"),
     *                 @OA\Property(property="pendingEmail", type="string", description="The new address the member asked for, while its confirmation link is open; empty otherwise"),
     *                 @OA\Property(property="emailLocked", type="boolean", description="True when only an administrator may change this email: the person has a staff account or given portal permissions"),
     *                 @OA\Property(property="cellPhone", type="string", example="(206) 555-0100"),
     *                 @OA\Property(property="birthday", type="string", example="1971-12-24", description="ISO date, empty when unknown"),
     *                 @OA\Property(property="photoUrl", type="string"),
     *                 @OA\Property(property="canEditBirthday", type="boolean", description="Mirrors bPortalAllowBirthdayEdit"),
     *                 @OA\Property(property="language", type="string", example="fr_FR", description="The language the member chose for the portal and its emails; empty for the church's language")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No signed-in session"),
     *     @OA\Response(response=403, description="API-key caller, or an account with no person record")
     * )
     */
    $group->get('/me', function (Request $request, Response $response) use ($portalActor): Response {
        return SlimUtils::renderJSON($response, [
            'profile' => PortalSelfService::getProfile($portalActor($request)),
        ]);
    });

    /**
     * @OA\Post(
     *     path="/portal/me",
     *     operationId="updatePortalMe",
     *     summary="Update the signed-in member's own profile",
     *     description="Applies the allow-listed fields present in the body to the member's own person record and writes a timeline note naming what changed. Anything else in the body is ignored. Birthday is accepted only when bPortalAllowBirthdayEdit is on. A new email is not saved: a confirmation link goes to the new address, a notice goes to the old one, and the change happens when the link is used (member sign-in design §9). Language is ignored in a staff session, which keeps the staff account's own setting.",
     *     tags={"Member Portal"},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="firstName", type="string"),
     *             @OA\Property(property="middleName", type="string"),
     *             @OA\Property(property="lastName", type="string"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="suffix", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="workEmail", type="string"),
     *             @OA\Property(property="homePhone", type="string"),
     *             @OA\Property(property="cellPhone", type="string"),
     *             @OA\Property(property="workPhone", type="string"),
     *             @OA\Property(property="birthday", type="string", example="1971-12-24"),
     *             @OA\Property(property="language", type="string", example="fr_FR", description="A locale from src/locale/locales.json, or empty for the church's language")
     *         )
     *     ),
     *     @OA\Response(response=200, description="The saved profile",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="profile", type="object"),
     *             @OA\Property(property="updated", type="array", @OA\Items(type="string"), description="The fields that actually changed"),
     *             @OA\Property(property="pendingEmail", type="string", description="The new email a confirmation link was sent to; empty when the email did not change")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error, or an email that is empty or not valid"),
     *     @OA\Response(response=403, description="API-key caller, an invalid CSRF token, or an email only an administrator may change"),
     *     @OA\Response(response=429, description="Too many email change requests in an hour"),
     *     @OA\Response(response=503, description="The confirmation email could not be sent")
     * )
     */
    $group->post('/me', function (Request $request, Response $response) use ($portalActor, $portalError): Response {
        $actor = $portalActor($request);
        try {
            $result = PortalSelfService::updateProfile($actor, (array) $request->getParsedBody());
        } catch (PortalSelfServiceException $e) {
            return $portalError($response, $e);
        }
        if (in_array('language', $result['updated'], true)) {
            // The page reloads in the new language, so it says so in that language.
            PortalExtension::addFlash('success', MemberLanguage::inLanguageOf((int) $actor->getId(), static fn (): string => gettext('Your details have been saved.')));
        }

        return SlimUtils::renderJSON($response, array_merge(['success' => true], $result));
    })->add(new InputSanitizationMiddleware($portalPersonFields))
        ->add(RequirePortalCapability::any(CorePortalCapabilities::PROFILE_EDIT));

    /**
     * @OA\Post(
     *     path="/portal/me/photo",
     *     operationId="updatePortalMyPhoto",
     *     summary="Replace the signed-in member's own photo",
     *     description="The /api/person/{id}/photo upload route is behind the EditRecords role, which a self-service login never has, so the portal has its own endpoint for the member's own photo. It calls the same model method and writes the same 'photo' timeline note.",
     *     tags={"Member Portal"},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"imgBase64"},
     *             @OA\Property(property="imgBase64", type="string", description="Base64-encoded image with its data URI prefix")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Photo stored",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="profile", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Missing or unusable image data"),
     *     @OA\Response(response=413, description="Image larger than the server allows")
     * )
     */
    $group->post('/me/photo', function (Request $request, Response $response) use ($portalActor, $portalError): Response {
        $actor = $portalActor($request);
        $body = (array) $request->getParsedBody();

        if (!isset($body['imgBase64'])) {
            if (SlimUtils::isBodyDiscardedForSize($request)) {
                return SlimUtils::renderErrorJSON(
                    $response,
                    gettext('That photo is too large. Please choose a smaller one.'),
                    [],
                    413
                );
            }

            return SlimUtils::renderErrorJSON($response, gettext('No photo was sent.'), [], 400);
        }

        try {
            PortalSelfService::updatePhoto($actor, (string) $body['imgBase64']);
        } catch (PortalSelfServiceException $e) {
            return $portalError($response, $e);
        } catch (\ChurchCRM\Exceptions\PhotoSizeException $e) {
            return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 413, $e, $request);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('That photo could not be saved.'), [], 400, $e, $request);
        }

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'profile' => PortalSelfService::getProfile($actor),
        ]);
    })->add(RequirePortalCapability::any(CorePortalCapabilities::PROFILE_EDIT));

    /**
     * @OA\Get(
     *     path="/portal/me/photo",
     *     operationId="getPortalMyPhoto",
     *     summary="The signed-in member's own photo (binary image)",
     *     description="Returns the member's uploaded photo bytes. The portal serves its own copy because AuthMiddleware confines a self-service session to /portal and /api/portal, so /api/person/{id}/photo — which the profile used to point at — answers those sessions with 403 and every avatar renders broken. The member is taken from the session; there is no person id to pass. Cached privately for two hours, the same window the person endpoint uses; the URLs the portal hands out carry a ?v=<mtime> cache-buster so a fresh upload is visible at once.",
     *     tags={"Member Portal"},
     *     @OA\Response(response=200, description="The photo",
     *         @OA\MediaType(mediaType="image/*", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(response=401, description="No signed-in session"),
     *     @OA\Response(response=403, description="API-key caller, or an account with no person record"),
     *     @OA\Response(response=404, description="This member has not uploaded a photo")
     * )
     */
    $group->get('/me/photo', function (Request $request, Response $response) use ($portalActor, $portalPhoto): Response {
        return $portalPhoto($response, (int) $portalActor($request)->getId());
    })->add(new Cache('private', Photo::CACHE_DURATION_SECONDS));

    /**
     * @OA\Get(
     *     path="/portal/family",
     *     operationId="getPortalFamily",
     *     summary="The signed-in member's own family",
     *     description="Returns the acting member's family, its members, and what this member may do with it. Other members' email and cellPhone are empty unless the member holds portal.family.contacts.view (an adult of the family). The family is the member's own; there is no family id to pass.",
     *     tags={"Member Portal"},
     *     @OA\Response(response=200, description="The member's family",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="family", type="object", nullable=true,
     *                 @OA\Property(property="email", type="string", readOnly=true, deprecated=true, description="Deprecated in 7.8.3: the head of household's email, else the spouse's, empty unless the member may see it. Removed with the family email column.")
     *             ),
     *             @OA\Property(property="members", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="canEdit", type="boolean", description="portal.family.edit: an adult of the family"),
     *             @OA\Property(property="canConfirm", type="boolean", description="portal.family.confirm: an adult of the family"),
     *             @OA\Property(property="canSeeContacts", type="boolean", description="portal.family.contacts.view: an adult of the family")
     *         )
     *     ),
     *     @OA\Response(response=403, description="API-key caller")
     * )
     */
    $group->get('/family', function (Request $request, Response $response) use ($portalActor): Response {
        return SlimUtils::renderJSON($response, PortalSelfService::getFamilyView($portalActor($request)));
    })->add(new FamilyEmailDeprecationMiddleware('private/get-portal-family'))
        ->add(RequirePortalCapability::onOwnFamily(CorePortalCapabilities::FAMILY_VIEW));

    /**
     * @OA\Post(
     *     path="/portal/family",
     *     operationId="updatePortalFamily",
     *     summary="Update the signed-in member's own family",
     *     description="Applies the allow-listed fields present in the body to the member's own family and writes a timeline note naming what changed. Only an adult of the family (head or spouse, per sDirRoleHead / sDirRoleSpouse) may call it; every other member gets 403.",
     *     tags={"Member Portal"},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="address1", type="string"),
     *             @OA\Property(property="address2", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="state", type="string"),
     *             @OA\Property(property="zip", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="homePhone", type="string"),
     *             @OA\Property(property="email", type="string", deprecated=true, description="Deprecated in 7.8.3 and ignored: families have no email. A member changes their own email on their profile."),
     *             @OA\Property(property="weddingDate", type="string", example="2004-06-12")
     *         )
     *     ),
     *     @OA\Response(response=200, description="The saved family",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="family", type="object",
     *                 @OA\Property(property="email", type="string", readOnly=true, deprecated=true, description="Deprecated in 7.8.3: the head of household's email, else the spouse's, empty unless the member may see it. Removed with the family email column.")
     *             ),
     *             @OA\Property(property="updated", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=403, description="Not an adult of this family, an API-key caller, or an invalid CSRF token"),
     *     @OA\Response(response=404, description="The member has no family record")
     * )
     */
    $group->post('/family', function (Request $request, Response $response) use ($portalActor, $portalError): Response {
        try {
            $result = PortalSelfService::updateFamily($portalActor($request), (array) $request->getParsedBody());
        } catch (PortalSelfServiceException $e) {
            return $portalError($response, $e);
        }

        return SlimUtils::renderJSON($response, array_merge(['success' => true], $result));
    })->add(new FamilyEmailDeprecationMiddleware('private/update-portal-family', 'email'))
        ->add(new InputSanitizationMiddleware($portalFamilyFields))
        ->add(RequirePortalCapability::onOwnFamily(
            CorePortalCapabilities::FAMILY_EDIT,
            gettext('Only an adult of your family can change these details. Please contact the church office.')
        ));

    /**
     * @OA\Post(
     *     path="/portal/family/confirm",
     *     operationId="confirmPortalFamily",
     *     summary="Confirm the member's family details are current",
     *     description="Writes exactly the note the emailed verify link writes — type 'verify', entered by SELF_VERIFY, the text 'No Changes' or the member's comment — so a portal confirmation reaches the People → Verify dashboard the same way a token-link one does. Needs portal.family.confirm: an adult of the family.",
     *     tags={"Member Portal"},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"result"},
     *             @OA\Property(property="result", type="string", enum={"no-change","change-needed"}),
     *             @OA\Property(property="comment", type="string", description="Required when result is change-needed")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Confirmation recorded",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))
     *     ),
     *     @OA\Response(response=400, description="No choice made, or a change requested with no comment"),
     *     @OA\Response(response=403, description="Not an adult of this family"),
     *     @OA\Response(response=404, description="The member has no family record")
     * )
     */
    $group->post('/family/confirm', function (Request $request, Response $response) use ($portalActor, $portalError): Response {
        $body = (array) $request->getParsedBody();
        try {
            PortalSelfService::confirmFamily(
                $portalActor($request),
                (string) ($body['result'] ?? ''),
                (string) ($body['comment'] ?? '')
            );
        } catch (PortalSelfServiceException $e) {
            return $portalError($response, $e);
        }

        return SlimUtils::renderSuccessJSON($response);
    })->add(new InputSanitizationMiddleware($portalConfirmFields))
        ->add(RequirePortalCapability::onOwnFamily(
            CorePortalCapabilities::FAMILY_CONFIRM,
            gettext('Only an adult of your family can confirm these details. Please contact the church office.')
        ));

    /**
     * @OA\Get(
     *     path="/portal/family/members/{personId}/photo",
     *     operationId="getPortalFamilyMemberPhoto",
     *     summary="The photo of somebody in the signed-in member's own family (binary image)",
     *     description="The read side of the portal's own photo serving, for the faces on the My Family page. The id is checked against the members of the acting member's family — the same enumeration GET /portal/family renders — and anything else is 404, never 403: a 403 would confirm that the id names a real person, which a member outside that family must not learn (design P12). This is the only portal route that takes a person id, and it never names the actor.",
     *     tags={"Member Portal"},
     *     @OA\Parameter(name="personId", in="path", required=true, description="A person in the acting member's own family", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="The photo",
     *         @OA\MediaType(mediaType="image/*", @OA\Schema(type="string", format="binary"))
     *     ),
     *     @OA\Response(response=401, description="No signed-in session"),
     *     @OA\Response(response=403, description="API-key caller, or an account with no person record"),
     *     @OA\Response(response=404, description="Not a member of this family, or that member has no photo")
     * )
     */
    $group->get('/family/members/{personId:[0-9]+}/photo', function (Request $request, Response $response, array $args) use ($portalActor, $portalPhoto): Response {
        $member = PortalSelfService::findFamilyMember($portalActor($request), (int) $args['personId']);
        if ($member === null) {
            return SlimUtils::renderErrorJSON($response, gettext('No photo has been uploaded for this person.'), [], 404);
        }

        // Not cached: on a shared browser the next member signed in could be served it
        // without the family check running again.
        return $portalPhoto($response, (int) $member->getId())->withHeader('Cache-Control', 'private, no-store');
    })->add(RequirePortalCapability::onOwnFamily(CorePortalCapabilities::FAMILY_VIEW));

    /**
     * @OA\Post(
     *     path="/portal/family/members",
     *     operationId="addPortalFamilyMember",
     *     summary="Propose a new member of the signed-in member's family",
     *     description="Creates a person in the member's family marked SELF_REGISTER, exactly as the public registration form does, so the entry waits on People → Self Registrations for staff to review. It is never a live member. Only an adult of the family may call it.",
     *     tags={"Member Portal"},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"firstName","lastName"},
     *             @OA\Property(property="firstName", type="string"),
     *             @OA\Property(property="lastName", type="string"),
     *             @OA\Property(property="role", type="string", description="A family-role option id; falls back to the configured child role"),
     *             @OA\Property(property="birthday", type="string", example="2015-03-08")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Pending entry created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="personId", type="integer", example=1234)
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=403, description="Not an adult of this family"),
     *     @OA\Response(response=404, description="The member has no family record")
     * )
     */
    $group->post('/family/members', function (Request $request, Response $response) use ($portalActor, $portalError): Response {
        try {
            $person = PortalSelfService::addFamilyMember($portalActor($request), (array) $request->getParsedBody());
        } catch (PortalSelfServiceException $e) {
            return $portalError($response, $e);
        }

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'personId' => (int) $person->getId(),
        ]);
    })->add(new InputSanitizationMiddleware($portalNewMemberFields))
        ->add(RequirePortalCapability::onOwnFamily(
            CorePortalCapabilities::FAMILY_EDIT,
            gettext('Only an adult of your family can change these details. Please contact the church office.')
        ));
})->add(new CSRFMiddleware())->add(new PortalApiMiddleware())->add(new PortalSessionMiddleware());
