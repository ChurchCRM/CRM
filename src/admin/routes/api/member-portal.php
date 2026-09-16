<?php

use ChurchCRM\Portal\PortalStatsService;
use ChurchCRM\Portal\ThemeException;
use ChurchCRM\Portal\ThemeManager;
use ChurchCRM\Portal\ThemeValidator;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Served at /admin/api/member-portal/* — '/admin' is the app base path, and
// AdminRoleAuthMiddleware already gates every route in this app.
//
// Booleans and text on the Admin → Member Portal page save through the
// existing POST /admin/api/system/config/{name}. Only theme activation needs a
// route of its own, because the validator has to be able to refuse (design §4).
$app->group('/api/member-portal', function (RouteCollectorProxy $group): void {
    $group->get('/stats', 'getMemberPortalStatsAPI');
    $group->get('/themes', 'listMemberPortalThemesAPI');
    $group->get('/theme/{name}/validation', 'validateMemberPortalThemeAPI');
    $group->post('/theme', 'activateMemberPortalThemeAPI')
        // `name` is a folder name; sanitizeText strips markup, and the handler
        // then checks it against the folders actually on disk.
        ->add(new InputSanitizationMiddleware(['name' => 'text']));
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

