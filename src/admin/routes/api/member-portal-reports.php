<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Service\MemberSignInListService;
use ChurchCRM\Service\PortalGrantService;
use ChurchCRM\Service\PortalPermissionsService;
use ChurchCRM\Service\SignInAuditService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Served at /admin/api/member-portal/* (member sign-in design §11.2, §8.6).
// AdminRoleAuthMiddleware gates every route in this app. Every route here only
// reads.
$app->group('/api/member-portal', function (RouteCollectorProxy $group): void {
    $group->get('/lists', 'getMemberPortalListSummaryAPI');
    $group->get('/lists/{list}', 'getMemberPortalListAPI');
    $group->get('/audit', 'getMemberSignInAuditAPI');
    $group->get('/permissions', 'getMemberPortalPermissionsAPI');
    $group->get('/permissions/{capability}/holders', 'getMemberPortalPermissionHoldersAPI');
    $group->get('/grant-scopes/{type}', 'searchPortalGrantScopesAPI');
});

/**
 * @return array{0: int, 1: int, 2: string}
 */
function memberPortalPageParams(Request $request, int $defaultLength): array
{
    $params = $request->getQueryParams();

    return [
        max(0, (int) ($params['start'] ?? 0)),
        (int) ($params['length'] ?? $defaultLength),
        is_string($params['search'] ?? null) ? $params['search'] : '',
    ];
}

/**
 * @OA\Get(
 *     path="/admin/api/member-portal/lists",
 *     operationId="getMemberPortalListSummary",
 *     summary="How many people each Member Portal list holds (Admin role required)",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="A count per list, the email problems, and the family email move's outcomes",
 *         @OA\JsonContent(
 *             @OA\Property(property="may-sign-in", type="integer"),
 *             @OA\Property(property="signed-in-recently", type="integer"),
 *             @OA\Property(property="never-signed-in", type="integer"),
 *             @OA\Property(property="email-attention", type="integer"),
 *             @OA\Property(property="emailProblems", type="object", example={"no_email": 3, "shared_email": 2, "email_unconfirmed": 1}),
 *             @OA\Property(property="families-confirmed", type="integer"),
 *             @OA\Property(property="family-emails-not-moved", type="integer"),
 *             @OA\Property(property="familyEmailMoves", type="object", example={"head": 120, "spouse": 4, "held": 30, "noted": 6, "pending": 0, "total": 160}),
 *             @OA\Property(property="recentDays", type="integer", example=30)
 *         )
 *     ),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function getMemberPortalListSummaryAPI(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, (new MemberSignInListService())->summary());
}

/**
 * @OA\Get(
 *     path="/admin/api/member-portal/lists/{list}",
 *     operationId="getMemberPortalList",
 *     summary="One page of a Member Portal list (Admin role required)",
 *     description="may-sign-in: the blocks, the member rule and the email rule all let them in. signed-in-recently: a portal sign-in in the last 30 days. never-signed-in: may sign in but never has. email-attention: the member rule lets them in but their email does not (no_email, shared_email or email_unconfirmed); an unconfirmed row says whether the caller may confirm it (canConfirmEmail, confirmEmailRefusal) through POST /api/person/{id}/sign-in/confirm-email. families-confirmed: a member confirmed the family's details in the last 30 days. family-emails-not-moved: the 7.9.0 upgrade could not give the family email to a person.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="list", in="path", required=true, @OA\Schema(type="string", enum={"may-sign-in", "signed-in-recently", "never-signed-in", "email-attention", "families-confirmed", "family-emails-not-moved"})),
 *     @OA\Parameter(name="start", in="query", @OA\Schema(type="integer", default=0)),
 *     @OA\Parameter(name="length", in="query", @OA\Schema(type="integer", default=25, maximum=100)),
 *     @OA\Parameter(name="search", in="query", description="Part of a name or email", @OA\Schema(type="string")),
 *     @OA\Parameter(name="problem", in="query", description="email-attention only", @OA\Schema(type="string", enum={"no_email", "shared_email", "email_unconfirmed"})),
 *     @OA\Response(response=200, description="The page",
 *         @OA\JsonContent(
 *             @OA\Property(property="total", type="integer", description="Rows in the list"),
 *             @OA\Property(property="filtered", type="integer", description="Rows matching the search"),
 *             @OA\Property(property="rows", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(response=400, description="An unknown list or email problem"),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function getMemberPortalListAPI(Request $request, Response $response, array $args): Response
{
    [$start, $length, $search] = memberPortalPageParams($request, MemberSignInListService::DEFAULT_PAGE_SIZE);
    $problem = $request->getQueryParams()['problem'] ?? '';

    try {
        $lists = new MemberSignInListService(null, AuthenticationManager::getCurrentUser()->isAdmin());
        $page = $lists->page((string) $args['list'], $start, $length, $search, is_string($problem) ? $problem : '');
    } catch (InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, $page);
}

/**
 * @OA\Get(
 *     path="/admin/api/member-portal/audit",
 *     operationId="getMemberSignInAudit",
 *     summary="The member sign-in audit, newest first (Admin role required)",
 *     description="Each row: time, person, event, method, reason, the administrator for staff actions, and the address that was typed or used. The audit never holds a code, link, token, password or passkey secret.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="from", in="query", description="First day, YYYY-MM-DD", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", description="Last day, YYYY-MM-DD, inclusive", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="event", in="query", @OA\Schema(type="string", example="refused")),
 *     @OA\Parameter(name="start", in="query", @OA\Schema(type="integer", default=0)),
 *     @OA\Parameter(name="length", in="query", @OA\Schema(type="integer", default=25, maximum=100)),
 *     @OA\Response(response=200, description="The page",
 *         @OA\JsonContent(
 *             @OA\Property(property="total", type="integer"),
 *             @OA\Property(property="filtered", type="integer"),
 *             @OA\Property(property="rows", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="events", type="array", @OA\Items(type="object"))
 *         )
 *     ),
 *     @OA\Response(response=400, description="A malformed date, a range that ends before it starts, or an unknown event"),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function getMemberSignInAuditAPI(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    [$start, $length] = memberPortalPageParams($request, SignInAuditService::DEFAULT_PAGE_SIZE);
    $text = static fn (string $name): ?string => is_string($params[$name] ?? null) ? $params[$name] : null;

    try {
        $page = (new SignInAuditService())->page(
            isset($params['personId']) ? (int) $params['personId'] : null,
            $text('from'),
            $text('to'),
            $text('event'),
            $start,
            $length,
        );
    } catch (InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, $page + ['events' => SignInAuditService::eventChoices()]);
}

/**
 * @OA\Get(
 *     path="/admin/api/member-portal/permissions",
 *     operationId="getMemberPortalPermissions",
 *     summary="Every portal capability with its sources and how many people hold it (Admin role required)",
 *     description="Holders are counted among the people who can open the portal: everyone the sign-in rules let in, and staff.",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=200, description="The catalog",
 *         @OA\JsonContent(@OA\Property(property="capabilities", type="array", @OA\Items(type="object",
 *             @OA\Property(property="key", type="string", example="volunteer.team.manage"),
 *             @OA\Property(property="label", type="string"),
 *             @OA\Property(property="description", type="string"),
 *             @OA\Property(property="owner", type="string", example="core"),
 *             @OA\Property(property="scopeType", type="string", example="team"),
 *             @OA\Property(property="status", type="string", example="on"),
 *             @OA\Property(property="sources", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="holderCount", type="integer"),
 *             @OA\Property(property="givenCount", type="integer")
 *         )))
 *     ),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function getMemberPortalPermissionsAPI(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, ['capabilities' => (new PortalPermissionsService())->capabilities()]);
}

/**
 * @OA\Get(
 *     path="/admin/api/member-portal/permissions/{capability}/holders",
 *     operationId="getMemberPortalPermissionHolders",
 *     summary="The people who hold one portal capability, and where each grant comes from (Admin role required)",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="capability", in="path", required=true, @OA\Schema(type="string", example="volunteer.team.manage")),
 *     @OA\Parameter(name="start", in="query", @OA\Schema(type="integer", default=0)),
 *     @OA\Parameter(name="length", in="query", @OA\Schema(type="integer", default=25, maximum=100)),
 *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="The page", @OA\JsonContent(
 *         @OA\Property(property="capability", type="object"),
 *         @OA\Property(property="total", type="integer"),
 *         @OA\Property(property="filtered", type="integer"),
 *         @OA\Property(property="rows", type="array", @OA\Items(type="object"))
 *     )),
 *     @OA\Response(response=403, description="Admin role required"),
 *     @OA\Response(response=404, description="No such capability")
 * )
 */
function getMemberPortalPermissionHoldersAPI(Request $request, Response $response, array $args): Response
{
    [$start, $length, $search] = memberPortalPageParams($request, PortalPermissionsService::DEFAULT_PAGE_SIZE);

    try {
        $page = (new PortalPermissionsService())->holdersPage((string) $args['capability'], $start, $length, $search);
    } catch (InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 404, null, $request);
    }

    return SlimUtils::renderJSON($response, $page);
}

/**
 * @OA\Get(
 *     path="/admin/api/member-portal/grant-scopes/{type}",
 *     operationId="searchPortalGrantScopes",
 *     summary="Families, teams, ministries or groups a portal permission can be given on (Admin role required)",
 *     tags={"Admin"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="type", in="path", required=true, @OA\Schema(type="string", enum={"family", "team", "ministry", "group"})),
 *     @OA\Parameter(name="q", in="query", description="Part of the name", @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Up to 25 choices", @OA\JsonContent(type="array", @OA\Items(type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="text", type="string")
 *     ))),
 *     @OA\Response(response=400, description="A scope type with no picker"),
 *     @OA\Response(response=403, description="Admin role required")
 * )
 */
function searchPortalGrantScopesAPI(Request $request, Response $response, array $args): Response
{
    $query = $request->getQueryParams()['q'] ?? '';

    try {
        $choices = (new PortalGrantService())->scopeChoices((string) $args['type'], is_string($query) ? $query : '');
    } catch (InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, $choices);
}
