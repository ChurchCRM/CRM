<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\VolunteerV1Service;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Volunteer\VolunteerException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Served at /admin/api/volunteer-conversion/* — the legacy Volunteer Opportunities becoming
// Ministries (#10606). AdminRoleAuthMiddleware already gates the admin app.
$app->group('/api/volunteer-conversion', function (RouteCollectorProxy $group): void {
    $group->get('/preview', 'previewVolunteerConversionAPI');
    $group->post('', 'convertVolunteerOpportunitiesAPI')->add(new CSRFMiddleware());
    $group->post('/notice/dismiss', 'dismissVolunteerV1NoticeAPI')->add(new CSRFMiddleware());
});

/**
 * The choice as preview() and convert() read it, from a query string or a JSON body. The
 * service validates each value; a query string sends the opportunity ids comma-separated.
 *
 * @return array<string, mixed>
 */
function volunteerConversionChoice(array $input): array
{
    $own = $input['ownMinistry'] ?? [];
    if (is_string($own)) {
        $own = $own === '' ? [] : explode(',', $own);
    }

    return array_filter([
        'ministryId' => $input['ministryId'] ?? null,
        'ministryName' => $input['ministryName'] ?? null,
        'ownMinistry' => $own,
    ], static fn ($value): bool => $value !== null);
}

/**
 * GET /admin/api/volunteer-conversion/preview?ministryId=|ministryName=&ownMinistry=1,2
 * — what converting would do, with the plan's `revision`. Nothing is written.
 *
 *   400 — the choice names a ministry that does not exist, a taken name, or malformed ids
 */
function previewVolunteerConversionAPI(Request $request, Response $response): Response
{
    try {
        $preview = (new VolunteerV1Service())->preview(volunteerConversionChoice($request->getQueryParams()));
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, $preview);
}

/**
 * POST /admin/api/volunteer-conversion {"ministryId": 3} | {"ministryName": "…"}, "ownMinistry": [ids],
 * "revision": "…" — run the previewed plan with that revision, all or nothing.
 *
 *   400 — the choice is malformed, the revision is missing, or a V2 rule refuses a name
 *   409 — the plan changed since that preview; nothing was written
 *   200 — converted; the plan that ran comes back with the opportunity → position map
 */
function convertVolunteerOpportunitiesAPI(Request $request, Response $response): Response
{
    $input = $request->getParsedBody();
    if (!is_array($input)) {
        return SlimUtils::renderErrorJSON($response, gettext('Send the conversion choice as a JSON object.'), [], 400, null, $request);
    }
    $revision = $input['revision'] ?? null;
    if (!is_string($revision) || $revision === '') {
        return SlimUtils::renderErrorJSON($response, gettext('Preview the conversion first, and send the revision it returned.'), [], 400, null, $request);
    }

    try {
        $result = (new VolunteerV1Service())->convert(volunteerConversionChoice($input), $revision, AuthenticationManager::getCurrentUser());
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    } catch (VolunteerException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], $e->getStatusCode(), null, $request);
    }

    return SlimUtils::renderJSON($response, ['success' => true] + $result);
}

/**
 * POST /admin/api/volunteer-conversion/notice/dismiss — hide the Admin Dashboard notice
 * until the next upgrade.
 */
function dismissVolunteerV1NoticeAPI(Request $request, Response $response): Response
{
    VolunteerV1Service::dismissDashboardNotice();

    return SlimUtils::renderJSON($response, ['success' => true]);
}
