<?php

use ChurchCRM\Portal\PortalCalendarService;
use ChurchCRM\Portal\PortalDirectoryService;
use ChurchCRM\Service\MemberSignInListService;
use ChurchCRM\Service\MemberSignInSettingsService;
use ChurchCRM\Portal\PortalStatsService;
use ChurchCRM\Portal\ThemeException;
use ChurchCRM\Portal\ThemeManager;
use ChurchCRM\Portal\ThemeValidator;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Served at /admin/api/member-portal/* — '/admin' is the app base path, and
// AdminRoleAuthMiddleware already gates every route in this app.
//
// The Settings tab's booleans save through the existing
// POST /admin/api/system/config/{name}. Theme activation and the Sign-in tab
// have routes of their own, because their checks have to be able to refuse.
$app->group('/api/member-portal', function (RouteCollectorProxy $group): void {
    $group->get('/stats', 'getMemberPortalStatsAPI');
    $group->get('/themes', 'listMemberPortalThemesAPI');
    $group->get('/theme/{name}/validation', 'validateMemberPortalThemeAPI');
    $group->post('/theme', 'activateMemberPortalThemeAPI')
        // `name` is a folder name; sanitizeText strips markup, and the handler
        // then checks it against the folders actually on disk.
        ->add(new InputSanitizationMiddleware(['name' => 'text']));
    $group->get('/calendars', 'listMemberPortalCalendarsAPI');
    // The body is a list of {type, id} objects, not scalar fields, so
    // InputSanitizationMiddleware has nothing to clean here; validation is
    // PortalCalendarService::setVisible(), which accepts only entries naming a
    // calendar this installation actually has.
    $group->post('/calendars', 'saveMemberPortalCalendarsAPI');
    // PortalDirectoryService::save() validates every part of the body.
    $group->get('/directory', 'getMemberPortalDirectoryAPI');
    $group->post('/directory', 'saveMemberPortalDirectoryAPI')->add(new CSRFMiddleware());
    // The Sign-in tab (member sign-in design §11.1). MemberSignInSettingsService
    // validates every field; the generic config API routes these keys through it too.
    $group->get('/sign-in', 'getMemberSignInSettingsAPI');
    $group->post('/sign-in', 'saveMemberSignInSettingsAPI')->add(new CSRFMiddleware());
    $group->get('/sign-in/office-candidates/{query}', 'searchMemberSignInOfficeCandidatesAPI');
    $group->post('/former-accounts-notice/dismiss', 'dismissMemberPortalFormerAccountsNoticeAPI')->add(new CSRFMiddleware());
});

/**
 * GET /admin/api/member-portal/stats — the statistics tab's numbers.
 */
function getMemberPortalStatsAPI(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, PortalStatsService::getStatistics());
}

/**
 * GET /admin/api/member-portal/themes — every discovered theme with its
 * current validation result. Used by the page's "refresh" paths; the first
 * render is server-side.
 */
function listMemberPortalThemesAPI(Request $request, Response $response): Response
{
    $themes = [];
    foreach (ThemeManager::listThemes() as $theme) {
        $findings = ThemeManager::validate($theme['id']);
        $themes[] = $theme + [
            'status' => ThemeValidator::summarize($findings),
            'findings' => $findings,
        ];
    }

    return SlimUtils::renderJSON($response, [
        'activeTheme' => ThemeManager::getActiveThemeName(),
        'themes' => $themes,
    ]);
}

/**
 * GET /admin/api/member-portal/theme/{name}/validation — the "Check" button.
 * Runs the validator against one theme and returns its findings without
 * changing anything.
 */
function validateMemberPortalThemeAPI(Request $request, Response $response, array $args): Response
{
    $name = (string) ($args['name'] ?? '');
    if (!isKnownMemberPortalTheme($name)) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('That Member Portal theme folder does not exist.'),
            [],
            404,
            null,
            $request
        );
    }

    $findings = ThemeManager::validate($name);

    return SlimUtils::renderJSON($response, [
        'name' => $name,
        'status' => ThemeValidator::summarize($findings),
        'findings' => $findings,
    ]);
}

/**
 * POST /admin/api/member-portal/theme {"name": "..."} — make a theme active.
 *
 *   400 — the name is missing, malformed, or names no folder on disk
 *   409 — the theme has error-level findings; nothing is written
 *   200 — activated; any warning-level findings come back with it
 */
function activateMemberPortalThemeAPI(Request $request, Response $response): Response
{
    $input = $request->getParsedBody();
    $name = trim((string) (is_array($input) ? ($input['name'] ?? '') : ''));

    if (!isKnownMemberPortalTheme($name)) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('That Member Portal theme folder does not exist.'),
            [],
            400,
            null,
            $request
        );
    }

    try {
        $findings = ThemeManager::activate($name);
    } catch (ThemeException $e) {
        // Activation refused. ThemeManager writes nothing on this path, so the
        // portal keeps rendering with the theme it already had.
        return SlimUtils::renderJSON($response, [
            'name' => $name,
            'activated' => false,
            'status' => ThemeValidator::LEVEL_ERROR,
            'message' => $e->getMessage(),
            'findings' => $e->getFindings(),
        ], 409);
    }

    return SlimUtils::renderJSON($response, [
        'name' => $name,
        'activated' => true,
        'status' => ThemeValidator::summarize($findings),
        'findings' => $findings,
    ]);
}

/**
 * A theme name is acceptable only when it names a folder the scan found. That
 * is stricter than the character allow-list and is what keeps an arbitrary
 * string out of `sMemberPortalTheme`.
 */
function isKnownMemberPortalTheme(string $name): bool
{
    if (!ThemeManager::isValidThemeName($name)) {
        return false;
    }

    foreach (ThemeManager::listThemes() as $theme) {
        if ($theme['id'] === $name) {
            return true;
        }
    }

    return false;
}


/**
 * GET /admin/api/member-portal/calendars — every church, ministry and system
 * calendar with the "Show in Member Portal" switch's current position.
 */
function listMemberPortalCalendarsAPI(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, [
        'calendars' => PortalCalendarService::listChoices(),
    ]);
}

/**
 * POST /admin/api/member-portal/calendars {"visible": [{"type": "...", "id": 1}]}
 * — replace the set of calendars members see.
 *
 *   400 — `visible` is missing, is not a list, or an entry names no calendar
 *   200 — saved; the refreshed list comes back so the page can redraw
 */
function saveMemberPortalCalendarsAPI(Request $request, Response $response): Response
{
    $input = $request->getParsedBody();
    $visible = is_array($input) ? ($input['visible'] ?? null) : null;

    if (!is_array($visible)) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Send the calendars to show as a list.'),
            [],
            400,
            null,
            $request
        );
    }

    try {
        PortalCalendarService::setVisible(array_values($visible));
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, [
        'success' => true,
        'calendars' => PortalCalendarService::listChoices(),
    ]);
}

/**
 * @OA\Get(
 *     path="/admin/api/member-portal/sign-in",
 *     operationId="getMemberSignInSettings",
 *     summary="The Member Portal sign-in settings (Admin role required)",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Stored values, the choices, and why member sign-in or passkeys can't be used",
 *         @OA\JsonContent(
 *             @OA\Property(property="values", type="object", example={"bMemberSignIn": "0", "sMemberSignInFamilyRoles": "1,2", "iMemberSignInMinAge": "13"}),
 *             @OA\Property(property="emailProblem", type="string", nullable=true),
 *             @OA\Property(property="passkeyProblem", type="string", nullable=true),
 *             @OA\Property(property="familyRoles", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="classifications", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="officeRecipients", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="maySignInCount", type="integer", description="People the saved settings let in, before the email rule")
 *         )
 *     ),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function getMemberSignInSettingsAPI(Request $request, Response $response): Response
{
    $settings = new MemberSignInSettingsService();

    return SlimUtils::renderJSON($response, [
        'values' => $settings->values(),
        'emailProblem' => $settings->emailProblem(),
        'passkeyProblem' => $settings->passkeyProblem(),
        'familyRoles' => $settings->familyRoleChoices(),
        'classifications' => $settings->classificationChoices(),
        'officeRecipients' => $settings->officeRecipients(),
        'maySignInCount' => $settings->maySignInCount(),
    ]);
}

/**
 * @OA\Post(
 *     path="/admin/api/member-portal/sign-in",
 *     operationId="saveMemberSignInSettings",
 *     summary="Save Member Portal sign-in settings (Admin role required)",
 *     description="Send any of the sign-in settings. All of them are checked first; if any is wrong, none is saved. Member sign-in can only be turned on while ChurchCRM can send email. Family roles, classifications and the office list may be arrays of ids or comma-separated strings.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="bMemberSignIn", type="boolean"),
 *         @OA\Property(property="sMemberSignInFamilyRoles", type="array", @OA\Items(type="integer"), example={1, 2}),
 *         @OA\Property(property="sMemberSignInClassifications", type="array", @OA\Items(type="integer"), example={1, 2}),
 *         @OA\Property(property="iMemberSignInMinAge", type="integer", minimum=0, maximum=120, example=13),
 *         @OA\Property(property="iSignInLinkMinutes", type="integer", minimum=1, maximum=1440, example=120),
 *         @OA\Property(property="iMemberSignInRequestsPerIP", type="integer", minimum=0, maximum=10000, example=20),
 *         @OA\Property(property="iPortalIdleMinutes", type="integer", minimum=1, maximum=1440, example=30),
 *         @OA\Property(property="iRememberDeviceDays", type="integer", minimum=0, maximum=365, example=30),
 *         @OA\Property(property="bPortalPasskeys", type="boolean"),
 *         @OA\Property(property="sMemberSignInNotificationRecipientIDs", type="array", @OA\Items(type="integer")),
 *         @OA\Property(property="bAllowLoginAsPerson", type="boolean"),
 *         @OA\Property(property="bLoginAsPersonCanWrite", type="boolean")
 *     )),
 *     @OA\Response(response=200, description="Saved; the stored values come back",
 *         @OA\JsonContent(
 *             @OA\Property(property="values", type="object"),
 *             @OA\Property(property="maySignInCount", type="integer"),
 *             @OA\Property(property="sharedAddresses", type="integer", description="Shared personal emails that stop sign-in under the saved rule")
 *         )
 *     ),
 *     @OA\Response(response=400, description="A value is wrong; `errors` names each setting and its problem, and nothing was saved"),
 *     @OA\Response(response=403, description="Admin role required, or an invalid CSRF token")
 * )
 */
function saveMemberSignInSettingsAPI(Request $request, Response $response): Response
{
    $input = $request->getParsedBody();
    if (!is_array($input)) {
        return SlimUtils::renderErrorJSON($response, gettext('Send the settings as a JSON object.'), [], 400, null, $request);
    }

    $settings = new MemberSignInSettingsService();
    $errors = $settings->save($input);
    if ($errors !== []) {
        return SlimUtils::renderErrorJSON($response, (string) reset($errors), ['errors' => $errors], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, [
        'success' => true,
        'values' => $settings->values(),
        'maySignInCount' => $settings->maySignInCount(),
        'sharedAddresses' => count((new MemberSignInListService())->sharedEmails()),
    ]);
}

/**
 * @OA\Get(
 *     path="/admin/api/member-portal/sign-in/office-candidates/{query}",
 *     operationId="searchMemberSignInOfficeCandidates",
 *     summary="Find people who can be on the sign-in office list (Admin role required)",
 *     description="Living people with their own email whose name or email contains the query. Same shape as GET /api/persons/search/{query}.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="query", in="path", required=true, @OA\Schema(type="string", example="smith")),
 *     @OA\Response(response=200, description="Up to 15 people",
 *         @OA\JsonContent(type="array", @OA\Items(type="object",
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="objid", type="integer"),
 *             @OA\Property(property="text", type="string", example="Jane Smith (jane@example.com)")
 *         ))
 *     ),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function searchMemberSignInOfficeCandidatesAPI(Request $request, Response $response, array $args): Response
{
    return SlimUtils::renderJSON($response, (new MemberSignInSettingsService())->searchOfficeCandidates((string) ($args['query'] ?? '')));
}

/**
 * @OA\Post(
 *     path="/admin/api/member-portal/former-accounts-notice/dismiss",
 *     operationId="dismissMemberPortalFormerAccountsNotice",
 *     summary="Hide the Admin Dashboard notice about the removed self-service accounts (Admin role required)",
 *     description="The 7.9.0 upgrade removes self-service accounts and records how many. The Admin Dashboard asks to turn member sign-in on until it is on or this is called.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="Dismissed", @OA\JsonContent(@OA\Property(property="success", type="boolean", example=true))),
 *     @OA\Response(response=403, description="Admin role required, or the CSRF token was missing")
 * )
 */
function dismissMemberPortalFormerAccountsNoticeAPI(Request $request, Response $response): Response
{
    (new MemberSignInSettingsService())->dismissFormerAccountsNotice();

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * GET /admin/api/member-portal/directory — who the directory lists, who can see it and what it shows.
 */
function getMemberPortalDirectoryAPI(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, ['settings' => PortalDirectoryService::settings()]);
}

/**
 * POST /admin/api/member-portal/directory {"listed": [1], "viewers": [1], "fields": {...},
 * "birthday": "withoutYear", "anniversary": "withoutYear"} — any subset of the parts.
 *
 *   400 — a part is malformed or names a classification or field that does not exist
 *   200 — saved; the whole stored choice comes back
 */
function saveMemberPortalDirectoryAPI(Request $request, Response $response): Response
{
    $input = $request->getParsedBody();
    if (!is_array($input)) {
        return SlimUtils::renderErrorJSON($response, gettext('Send the directory settings as a JSON object.'), [], 400, null, $request);
    }

    try {
        $settings = PortalDirectoryService::save($input);
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, ['success' => true, 'settings' => $settings]);
}
