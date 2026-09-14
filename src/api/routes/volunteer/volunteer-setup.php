<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\Exceptions\VolunteerSetupException;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPosition;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerQualification;
use ChurchCRM\model\ChurchCRM\VolunteerScope;
use ChurchCRM\model\ChurchCRM\VolunteerScopeQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\VolunteerAssignmentService;
use ChurchCRM\Service\VolunteerSetupService;
use ChurchCRM\Slim\Middleware\Api\VolunteerMinistryMiddleware;
use ChurchCRM\Slim\Middleware\Api\VolunteerPositionMiddleware;
use ChurchCRM\Slim\Middleware\Api\VolunteerQualificationMiddleware;
use ChurchCRM\Slim\Middleware\Api\VolunteerTeamMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\VolunteerManagerRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Volunteer Management V2 API — the setup surface: ministries, teams and
 * positions (#9715, epic #9701, design §3.3.1).
 *
 * Gating, from the outside in (Slim `->add()` is LIFO — the last one chained
 * runs first):
 *
 *   VolunteerV2EnabledMiddleware        on the /volunteer group. Chained HERE and
 *                                       not inherited: Slim scopes ->add() to the
 *                                       single RouteCollectorProxy it is chained
 *                                       on, so nothing propagates from the group
 *                                       in volunteer-status.php, and an ungated
 *                                       group would answer in every rollout state.
 *   VolunteerCoordinatorRoleAuthMiddleware  on the inner group — "do you coordinate
 *                                       anything at all".
 *   VolunteerManagerRoleAuthMiddleware  on the two manager-only routes, POST and
 *                                       DELETE of a ministry (§4.6).
 *   VolunteerMinistry/Team/PositionMiddleware  per record: 404 when it is missing,
 *                                       403 when it is outside the caller's scope.
 *   InputSanitizationMiddleware         last, once the caller is known to be
 *                                       allowed in at all.
 *
 * Two places deliberately do NOT chain the ministry entity middleware: the
 * position list and the position create under `/ministries/{ministryId}`. §4.6
 * lets a **team leader** create and edit positions in their own team, and
 * `VolunteerMinistryMiddleware` would deny them before the payload — which names
 * the team — has even been read. Those two handlers resolve the ministry
 * themselves and hand the whole decision to `VolunteerSetupService`, which
 * applies the same rule `VolunteerPositionMiddleware` applies to an existing
 * row: a position belongs to its team, and so to that team's leader and the
 * ministry coordinator above them.
 *
 * Read scoping is done in the QUERY, never after hydration (§4.4). The list
 * handlers call the service, which asks `isGlobalManager()` first (an
 * administrator holds no explicit grants) and short-circuits an empty allow-list
 * rather than handing `[]` to a filter.
 *
 * #9707 added the pool and qualification routes to this file, in their own
 * blocks below the position block; #9708 opens its own group in
 * volunteer-schedule.php.
 */
$app->group('/volunteer', function (RouteCollectorProxy $group): void {
    $group->group('', function (RouteCollectorProxy $setup): void {
        // ── Ministries ────────────────────────────────────────────────────
        $setup->get('/ministries', 'listVolunteerMinistries');

        $setup->post('/ministries', 'createVolunteerMinistry')
            ->add(new InputSanitizationMiddleware([
                'name' => 'text',
                'description' => 'text',
                // D19's advert. `helpWantedText` is 'text', not 'html': it is rendered
                // escaped with its line breaks preserved, never as markup.
                'helpWantedText' => 'text',
            ]))
            ->add(VolunteerManagerRoleAuthMiddleware::class);

        $setup->get('/ministries/{ministryId:[0-9]+}', 'getVolunteerMinistry')
            ->add(new VolunteerMinistryMiddleware());

        $setup->post('/ministries/{ministryId:[0-9]+}', 'updateVolunteerMinistry')
            ->add(new InputSanitizationMiddleware([
                'name' => 'text',
                'description' => 'text',
                'helpWantedText' => 'text',
            ]))
            ->add(new VolunteerMinistryMiddleware());

        $setup->delete('/ministries/{ministryId:[0-9]+}', 'deleteVolunteerMinistry')
            ->add(new VolunteerMinistryMiddleware())
            ->add(VolunteerManagerRoleAuthMiddleware::class);

        // ── Teams ─────────────────────────────────────────────────────────
        $setup->get('/ministries/{ministryId:[0-9]+}/teams', 'listVolunteerTeams')
            ->add(new VolunteerMinistryMiddleware());

        $setup->post('/ministries/{ministryId:[0-9]+}/teams', 'createVolunteerTeam')
            ->add(new InputSanitizationMiddleware([
                'name' => 'text',
                'description' => 'text',
            ]))
            ->add(new VolunteerMinistryMiddleware());

        $setup->get('/teams/{teamId:[0-9]+}', 'getVolunteerTeam')
            ->add(new VolunteerTeamMiddleware());

        $setup->post('/teams/{teamId:[0-9]+}', 'updateVolunteerTeam')
            ->add(new InputSanitizationMiddleware([
                'name' => 'text',
                'description' => 'text',
            ]))
            ->add(new VolunteerTeamMiddleware());

        $setup->delete('/teams/{teamId:[0-9]+}', 'deleteVolunteerTeam')
            ->add(new VolunteerTeamMiddleware());

        // ── Positions ─────────────────────────────────────────────────────
        // No ministry middleware here: see the file docblock — a team leader is
        // allowed at these two routes for their own team (§4.6).
        $setup->get('/ministries/{ministryId:[0-9]+}/positions', 'listVolunteerPositions');

        $setup->post('/ministries/{ministryId:[0-9]+}/positions', 'createVolunteerPosition')
            ->add(new InputSanitizationMiddleware([
                'name' => 'text',
                'description' => 'text',
            ]));

        $setup->get('/positions/{positionId:[0-9]+}', 'getVolunteerPosition')
            ->add(new VolunteerPositionMiddleware());

        $setup->post('/positions/{positionId:[0-9]+}', 'updateVolunteerPosition')
            ->add(new InputSanitizationMiddleware([
                'name' => 'text',
                'description' => 'text',
            ]))
            ->add(new VolunteerPositionMiddleware());

        $setup->delete('/positions/{positionId:[0-9]+}', 'deleteVolunteerPosition')
            ->add(new VolunteerPositionMiddleware());

        // ── The pool Group (D19) ──────────────────────────────────────────
        // There is nothing to link any more: a ministry owns exactly one Group and
        // it was created with the ministry. What is left is its membership, and
        // these three routes are the coordinator's way in — gated exactly like
        // every other ministry route, and writing through the Propel membership
        // model so the plugin hooks fire (G3).
        //
        // No InputSanitizationMiddleware: both writes take their person id from
        // the PATH, where the route pattern has already restricted it to digits,
        // and neither reads a body at all.
        $setup->get('/ministries/{ministryId:[0-9]+}/pool', 'listVolunteerPoolMembers')
            ->add(new VolunteerMinistryMiddleware());

        $setup->post('/ministries/{ministryId:[0-9]+}/pool/{personId:[0-9]+}', 'addVolunteerPoolMember')
            ->add(new VolunteerMinistryMiddleware());

        $setup->delete('/ministries/{ministryId:[0-9]+}/pool/{personId:[0-9]+}', 'removeVolunteerPoolMember')
            ->add(new VolunteerMinistryMiddleware());

        // "Remove Volunteer" on the ministry page's Volunteers tab: qualifications,
        // upcoming assignments and pool membership in one call. Ministry-level, so
        // `VolunteerMinistryMiddleware` answers 403 for a team leader — the page hides
        // the menu item from them, but hiding is not security (D5) and this is the
        // decision. No sanitizer: both ids come from the PATH, already digits-only.
        $setup->delete('/ministries/{ministryId:[0-9]+}/volunteers/{personId:[0-9]+}', 'removeVolunteerFromMinistry')
            ->add(new VolunteerMinistryMiddleware());

        // ── Pool people and the qualification matrix (#9707) ───────────────
        $setup->get('/ministries/{ministryId:[0-9]+}/members', 'listVolunteerMatrixMembers')
            ->add(new VolunteerMinistryMiddleware());

        $setup->get('/teams/{teamId:[0-9]+}/members', 'listVolunteerTeamMembers')
            ->add(new VolunteerTeamMiddleware());

        $setup->get('/ministries/{ministryId:[0-9]+}/qualification-matrix', 'getVolunteerQualificationMatrix')
            ->add(new VolunteerMinistryMiddleware());

        // ── Qualifications (#9707) ────────────────────────────────────────
        $setup->get('/positions/{positionId:[0-9]+}/qualifications', 'listVolunteerQualifications')
            ->add(new VolunteerPositionMiddleware());

        $setup->post('/positions/{positionId:[0-9]+}/qualifications', 'grantVolunteerQualification')
            ->add(new InputSanitizationMiddleware([
                'personId' => 'int',
                'notes' => 'text',
            ]))
            ->add(new VolunteerPositionMiddleware());

        // The Cart sink (P5/P6). No sanitizer: the payload is empty — the
        // people come from $_SESSION via Cart::getCartPeople(), never from the
        // request, so there is nothing to sanitize and nothing to spoof.
        $setup->post('/positions/{positionId:[0-9]+}/qualifications/from-cart', 'grantVolunteerQualificationsFromCart')
            ->add(new VolunteerPositionMiddleware());

        $setup->delete('/qualifications/{qualificationId:[0-9]+}', 'revokeVolunteerQualification')
            ->add(new VolunteerQualificationMiddleware());

        $setup->get('/people/{personId:[0-9]+}/qualifications', 'listVolunteerQualificationsForPerson');
    })->add(VolunteerCoordinatorRoleAuthMiddleware::class);
})->add(new VolunteerV2EnabledMiddleware());

// ─── Wire shapes ─────────────────────────────────────────────────────────────
//
// One array per entity, used by every handler below so a field can never be
// spelled two ways. Counts that need a second query are passed in rather than
// looked up here, which is what keeps the list handlers free of an N+1.

/**
 * @param array{teamCount?: int, positionCount?: int} $counts
 */
function volunteerMinistryToArray(VolunteerMinistry $ministry, array $counts = []): array
{
    return [
        'id' => (int) $ministry->getId(),
        'name' => $ministry->getName(),
        'description' => $ministry->getDescription(),
        'active' => (bool) $ministry->getActive(),
        'createdDate' => $ministry->getCreatedDate('Y-m-d H:i:s'),
        'createdByPersonId' => $ministry->getCreatedByPersonId() === null
            ? null
            : (int) $ministry->getCreatedByPersonId(),
        'teamCount' => $counts['teamCount'] ?? 0,
        'positionCount' => $counts['positionCount'] ?? 0,
        // D19's advert. Both are always on the wire, so a client can render the
        // "Help wanted" card without a second request and without guessing that an
        // absent key means off.
        'helpWanted' => (bool) $ministry->getHelpWanted(),
        'helpWantedText' => $ministry->getHelpWantedText(),
    ];
}

/**
 * @param array<int, array{scopeId: int, personId: int, personName: string}> $leaders
 *     the team-scope grants on this team, so the ministry page can render a "Team
 *     Leader" column without a `/scopes` call per row — and without needing the
 *     manager-only scope API at all, which a ministry coordinator does not have.
 *     Writing a grant is still `/api/volunteer/scopes`, still manager-only (§3.2).
 */
function volunteerTeamToArray(VolunteerTeam $team, int $positionCount = 0, array $leaders = []): array
{
    return [
        'id' => (int) $team->getId(),
        'ministryId' => (int) $team->getMinistryId(),
        'name' => $team->getName(),
        'description' => $team->getDescription(),
        'active' => (bool) $team->getActive(),
        'positionCount' => $positionCount,
        // Normally 0 or 1. The column carries no uniqueness constraint, so more than
        // one is representable and is reported rather than silently truncated.
        'leaders' => array_values($leaders),
    ];
}

/**
 * Team id → its team-leader grants, in ONE query over `volunteer_scope_vscp`.
 *
 * @param int[] $teamIds
 *
 * @return array<int, array<int, array{scopeId: int, personId: int, personName: string}>>
 */
function volunteerSetupTeamLeaders(array $teamIds): array
{
    if ($teamIds === []) {
        return [];
    }

    $scopes = VolunteerScopeQuery::create()
        ->filterByScopeType(VolunteerScope::TYPE_TEAM)
        ->filterByScopeId($teamIds, Criteria::IN)
        ->orderById()
        ->find();

    $rows = iterator_to_array($scopes, false);
    $names = volunteerSetupPersonNames(array_map(
        static fn (VolunteerScope $scope): int => (int) $scope->getPersonId(),
        $rows
    ));

    $byTeam = [];
    foreach ($rows as $scope) {
        $personId = (int) $scope->getPersonId();
        $byTeam[(int) $scope->getScopeId()][] = [
            'scopeId' => (int) $scope->getId(),
            'personId' => $personId,
            'personName' => $names[$personId] ?? '',
        ];
    }

    return $byTeam;
}

/**
 * @param array<int, string> $teamNames team id → name, so a position row renders
 *                                      its scope without a query per row
 */
function volunteerPositionToArray(VolunteerPosition $position, array $teamNames = []): array
{
    // D18: every position names a team, so `teamId` is never null on the wire and a
    // client never has to render a "no team" case.
    $teamId = (int) $position->getTeamId();

    return [
        'id' => (int) $position->getId(),
        'ministryId' => (int) $position->getMinistryId(),
        'teamId' => $teamId,
        'teamName' => $teamNames[$teamId] ?? null,
        'name' => $position->getName(),
        'description' => $position->getDescription(),
        'active' => (bool) $position->getActive(),
        'order' => (int) $position->getOrder(),
    ];
}

// ─── Shared handler helpers ──────────────────────────────────────────────────

/** The acting user, which is also the acting person (F4). */
function volunteerSetupActor(): User
{
    return AuthenticationManager::getCurrentUser();
}

/**
 * Turn a service-layer failure into the canonical error envelope, carrying the
 * status the service chose. Anything that is not a VolunteerSetupException is a
 * genuine fault and becomes a 500 with the exception attached for the log.
 */
function volunteerSetupError(Request $request, Response $response, \Throwable $e): Response
{
    if ($e instanceof VolunteerSetupException) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), $e->getExtra(), $e->getStatusCode(), null, $request);
    }

    return SlimUtils::renderErrorJSON($response, gettext('The volunteer setup change could not be saved'), [], 500, $e, $request);
}

/**
 * Pull only the keys a caller actually sent out of the parsed body, so a partial
 * update never blanks a column the caller never mentioned. Booleans are coerced
 * here because JSON, form posts and the sanitizer all spell them differently.
 *
 * @param string[] $allowed
 * @param string[] $booleans
 */
function volunteerSetupFields(Request $request, array $allowed, array $booleans = []): array
{
    $body = (array) $request->getParsedBody();
    $fields = [];

    foreach ($allowed as $key) {
        if (!array_key_exists($key, $body)) {
            continue;
        }
        $fields[$key] = in_array($key, $booleans, true)
            ? filter_var($body[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $body[$key]
            : $body[$key];
    }

    return $fields;
}

/** `?active=1` / `?active=0`; absent means "both". */
function volunteerSetupActiveFilter(Request $request): ?bool
{
    $params = $request->getQueryParams();
    if (!isset($params['active']) || $params['active'] === '') {
        return null;
    }

    return filter_var($params['active'], FILTER_VALIDATE_BOOLEAN);
}

/**
 * Team id → name for a set of positions, in one query, so the wire shape can
 * carry a readable scope without the list handler doing a lookup per row.
 *
 * @param VolunteerPosition[] $positions
 *
 * @return array<int, string>
 */
function volunteerSetupTeamNames(array $positions): array
{
    $teamIds = [];
    foreach ($positions as $position) {
        $teamIds[(int) $position->getTeamId()] = true;
    }

    if ($teamIds === []) {
        return [];
    }

    $names = [];
    foreach (VolunteerTeamQuery::create()->findPks(array_keys($teamIds)) as $team) {
        $names[(int) $team->getId()] = $team->getName();
    }

    return $names;
}

// ─── Ministries ──────────────────────────────────────────────────────────────

/**
 * @OA\Get(
 *     path="/volunteer/ministries",
 *     operationId="listVolunteerMinistries",
 *     summary="List the volunteer ministries the caller may administer",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="active", in="query", required=false, @OA\Schema(type="boolean"),
 *         description="Only active (1) or only inactive (0) ministries; omit for both"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Volunteer coordinator access is required, or V2 is not enabled"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="ministries", type="array", @OA\Items(type="object")))
 *     )
 * )
 */
function listVolunteerMinistries(Request $request, Response $response): Response
{
    $service = new VolunteerSetupService();
    $ministries = $service->listMinistriesFor(volunteerSetupActor(), volunteerSetupActiveFilter($request));

    $ministryIds = array_map(static fn (VolunteerMinistry $m): int => (int) $m->getId(), $ministries);
    $teamCounts = $service->countTeamsByMinistry($ministryIds);
    $positionCounts = $service->countPositionsByMinistry($ministryIds);

    $rows = [];
    foreach ($ministries as $ministry) {
        $id = (int) $ministry->getId();
        $rows[] = volunteerMinistryToArray($ministry, [
            'teamCount' => $teamCounts[$id] ?? 0,
            'positionCount' => $positionCounts[$id] ?? 0,
        ]);
    }

    return SlimUtils::renderJSON($response, ['ministries' => $rows]);
}

/**
 * @OA\Post(
 *     path="/volunteer/ministries",
 *     operationId="createVolunteerMinistry",
 *     summary="Create a volunteer ministry",
 *     description="Global volunteer managers and administrators only (design §4.6).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"name"},
 *         @OA\Property(property="name", type="string", maxLength=100),
 *         @OA\Property(property="description", type="string", maxLength=255)
 *     )),
 *     @OA\Response(response=400, description="The name is missing or empty"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Volunteer manager access is required, or V2 is not enabled"),
 *     @OA\Response(response=409, description="A ministry with that name already exists"),
 *     @OA\Response(response=201, description="Created")
 * )
 */
function createVolunteerMinistry(Request $request, Response $response): Response
{
    $body = (array) $request->getParsedBody();

    try {
        $ministry = (new VolunteerSetupService())->createMinistry(
            (string) ($body['name'] ?? ''),
            isset($body['description']) ? (string) $body['description'] : null,
            volunteerSetupActor()
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    // The counts are not decorative here: a caller needs to see that the ministry
    // came with a team, because that team is what its first position or schedule
    // has to name. It is always exactly one and always zero positions, so this is
    // a statement of the invariant rather than a query. `poolGroupId` is the same
    // kind of statement for D19: the ministry came with its Group, and a caller
    // that wants to link to it should not have to ask a second time.
    $poolGroup = (new VolunteerSetupService())->getPoolGroup((int) $ministry->getId());

    return SlimUtils::renderJSON(
        $response,
        [
            'ministry' => volunteerMinistryToArray($ministry, ['teamCount' => 1, 'positionCount' => 0]),
            'poolGroupId' => $poolGroup === null ? null : (int) $poolGroup->getId(),
        ],
        201
    );
}

/**
 * @OA\Get(
 *     path="/volunteer/ministries/{ministryId}",
 *     operationId="getVolunteerMinistry",
 *     summary="One ministry with its teams and positions",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="ministry", type="object"),
 *             @OA\Property(property="summary", type="object",
 *                 description="The three numbers the overview strip shows, computed with the caller's scope",
 *                 @OA\Property(property="teamCount", type="integer"),
 *                 @OA\Property(property="volunteerCount", type="integer",
 *                     description="Members of the ministry's pool Group"),
 *                 @OA\Property(property="unfilledPositionCount", type="integer",
 *                     description="Open slots across every future scheduled occurrence the caller may see")
 *             ),
 *             @OA\Property(property="teams", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="positions", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
function getVolunteerMinistry(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');
    $ministryId = (int) $ministry->getId();

    $service = new VolunteerSetupService();
    $teams = $service->listTeams($ministryId);
    $positions = $service->listPositions($ministryId);
    $poolGroup = $service->getPoolGroup($ministryId);

    $teamIds = array_map(static fn (VolunteerTeam $t): int => (int) $t->getId(), $teams);
    $teamPositionCounts = $service->countPositionsByTeam($teamIds);
    $teamLeaders = volunteerSetupTeamLeaders($teamIds);
    $teamNames = volunteerSetupTeamNames($positions);

    return SlimUtils::renderJSON($response, [
        'ministry' => volunteerMinistryToArray($ministry, [
            'teamCount' => count($teams),
            'positionCount' => count($positions),
        ]),
        // The overview strip's three numbers. `unfilledPositionCount` is scoped to the
        // caller by the service — a team leader sees only the occurrences of the teams
        // they lead — and reuses the ONE gap implementation rather than re-deriving it.
        'summary' => [
            'teamCount' => count($teams),
            'volunteerCount' => count($service->getPoolPersonIds($ministryId)),
            'unfilledPositionCount' => (new VolunteerAssignmentService())->countUnfilledPositions(
                $ministryId,
                volunteerSetupActor()
            ),
        ],
        'teams' => array_map(
            static fn (VolunteerTeam $t): array => volunteerTeamToArray(
                $t,
                $teamPositionCounts[(int) $t->getId()] ?? 0,
                $teamLeaders[(int) $t->getId()] ?? []
            ),
            $teams
        ),
        'positions' => array_map(
            static fn (VolunteerPosition $p): array => volunteerPositionToArray($p, $teamNames),
            $positions
        ),
        // #9707 completes the §3.3.1 shape and D19 simplifies it: the detail
        // document carries the ministry's pool Group and its members, so the
        // ministry page renders its Teams tab and its pool panel from ONE cached
        // response rather than a second fetch per tab (§5.4).
        'poolGroupId' => $poolGroup === null ? null : (int) $poolGroup->getId(),
        'poolGroupName' => $poolGroup === null ? null : (string) $poolGroup->getName(),
        'pool' => volunteerSetupPoolRows($service, $ministryId),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/ministries/{ministryId}",
 *     operationId="updateVolunteerMinistry",
 *     summary="Update a ministry's name, description or active flag",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="name", type="string", maxLength=100),
 *         @OA\Property(property="description", type="string", maxLength=255),
 *         @OA\Property(property="active", type="boolean"),
 *         @OA\Property(property="helpWanted", type="boolean", description="Advertise this ministry on the Open Opportunities page (design D19)"),
 *         @OA\Property(property="helpWantedText", type="string", description="What the ministry wants to say there; rendered escaped with line breaks preserved")
 *     )),
 *     @OA\Response(response=400, description="The name was sent empty"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=409, description="A ministry with that name already exists"),
 *     @OA\Response(response=200, description="Updated")
 * )
 */
function updateVolunteerMinistry(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');

    try {
        $ministry = (new VolunteerSetupService())->updateMinistry(
            $ministry,
            volunteerSetupFields(
                $request,
                ['name', 'description', 'active', 'helpWanted', 'helpWantedText'],
                ['active', 'helpWanted']
            ),
            volunteerSetupActor()
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderJSON($response, ['ministry' => volunteerMinistryToArray($ministry)]);
}

/**
 * @OA\Delete(
 *     path="/volunteer/ministries/{ministryId}",
 *     operationId="deleteVolunteerMinistry",
 *     summary="Delete a ministry that has no service history",
 *     description="Global volunteer managers and administrators only. Returns 409 once any occurrence or assignment references the ministry - deactivate it instead (design §2.3).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Volunteer manager access is required, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=409, description="The ministry still has occurrences or assignments"),
 *     @OA\Response(response=200, description="Deleted")
 * )
 */
function deleteVolunteerMinistry(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');

    try {
        (new VolunteerSetupService())->deleteMinistry($ministry, volunteerSetupActor());
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderSuccessJSON($response);
}

// ─── Teams ───────────────────────────────────────────────────────────────────

/**
 * @OA\Get(
 *     path="/volunteer/ministries/{ministryId}/teams",
 *     operationId="listVolunteerTeams",
 *     summary="Teams inside a ministry",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="active", in="query", required=false, @OA\Schema(type="boolean")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="teams", type="array", @OA\Items(type="object")))
 *     )
 * )
 */
function listVolunteerTeams(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');

    $service = new VolunteerSetupService();
    $teams = $service->listTeams((int) $ministry->getId(), volunteerSetupActiveFilter($request));
    $counts = $service->countPositionsByTeam(
        array_map(static fn (VolunteerTeam $t): int => (int) $t->getId(), $teams)
    );

    return SlimUtils::renderJSON($response, [
        'teams' => array_map(
            static fn (VolunteerTeam $t): array => volunteerTeamToArray($t, $counts[(int) $t->getId()] ?? 0),
            $teams
        ),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/ministries/{ministryId}/teams",
 *     operationId="createVolunteerTeam",
 *     summary="Create a team inside a ministry",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"name"},
 *         @OA\Property(property="name", type="string", maxLength=100),
 *         @OA\Property(property="description", type="string", maxLength=255)
 *     )),
 *     @OA\Response(response=400, description="The name is missing or empty"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=409, description="A team with that name already exists in this ministry"),
 *     @OA\Response(response=201, description="Created")
 * )
 */
function createVolunteerTeam(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');
    $body = (array) $request->getParsedBody();

    try {
        $team = (new VolunteerSetupService())->createTeam(
            $ministry,
            (string) ($body['name'] ?? ''),
            isset($body['description']) ? (string) $body['description'] : null,
            volunteerSetupActor()
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderJSON($response, ['team' => volunteerTeamToArray($team)], 201);
}

/**
 * @OA\Get(
 *     path="/volunteer/teams/{teamId}",
 *     operationId="getVolunteerTeam",
 *     summary="One team with the positions scoped to it",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="teamId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this team, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such team"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="team", type="object"),
 *             @OA\Property(property="positions", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
function getVolunteerTeam(Request $request, Response $response): Response
{
    /** @var VolunteerTeam $team */
    $team = $request->getAttribute('volunteerTeam');

    $service = new VolunteerSetupService();
    $positions = $service->listPositions((int) $team->getMinistryId(), (int) $team->getId());
    $teamNames = [(int) $team->getId() => $team->getName()];

    return SlimUtils::renderJSON($response, [
        'team' => volunteerTeamToArray($team, count($positions)),
        'positions' => array_map(
            static fn (VolunteerPosition $p): array => volunteerPositionToArray($p, $teamNames),
            $positions
        ),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/teams/{teamId}",
 *     operationId="updateVolunteerTeam",
 *     summary="Update a team's name, description or active flag",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="teamId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="name", type="string", maxLength=100),
 *         @OA\Property(property="description", type="string", maxLength=255),
 *         @OA\Property(property="active", type="boolean")
 *     )),
 *     @OA\Response(response=400, description="The name was sent empty"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this team, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such team"),
 *     @OA\Response(response=409, description="A team with that name already exists in this ministry"),
 *     @OA\Response(response=200, description="Updated")
 * )
 */
function updateVolunteerTeam(Request $request, Response $response): Response
{
    /** @var VolunteerTeam $team */
    $team = $request->getAttribute('volunteerTeam');

    try {
        $team = (new VolunteerSetupService())->updateTeam(
            $team,
            volunteerSetupFields($request, ['name', 'description', 'active'], ['active']),
            volunteerSetupActor()
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderJSON($response, ['team' => volunteerTeamToArray($team)]);
}

/**
 * @OA\Delete(
 *     path="/volunteer/teams/{teamId}",
 *     operationId="deleteVolunteerTeam",
 *     summary="Delete a team that is neither the ministry's last nor still in use",
 *     description="Returns 409 when this is the ministry's ONLY team - a ministry always has at least one, so the answer is to rename it - and also while the team still owns positions or schedules, which vpos_vtem_ID / vsch_vtem_ID would cascade away with it.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="teamId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this team, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such team"),
 *     @OA\Response(response=409, description="This is the ministry's only team, or it still owns positions or schedules"),
 *     @OA\Response(response=200, description="Deleted")
 * )
 */
function deleteVolunteerTeam(Request $request, Response $response): Response
{
    /** @var VolunteerTeam $team */
    $team = $request->getAttribute('volunteerTeam');

    try {
        (new VolunteerSetupService())->deleteTeam($team, volunteerSetupActor());
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderSuccessJSON($response);
}

// ─── Positions ───────────────────────────────────────────────────────────────

/**
 * Load the ministry for the two position routes that cannot use
 * `VolunteerMinistryMiddleware` (see the file docblock). Returns null when the
 * route argument names no ministry; the caller renders the 404.
 */
function volunteerSetupFindMinistry(Request $request): ?VolunteerMinistry
{
    $ministryId = (int) SlimUtils::getRouteArgument($request, 'ministryId');

    return VolunteerMinistryQuery::create()->findPk($ministryId);
}

/**
 * Which of this ministry's teams may the caller act for?
 *
 * Returns null when the caller coordinates the whole ministry ("no narrowing"),
 * and otherwise the intersection of their team grants with this ministry's
 * teams — computed with ONE query, not one per grant.
 *
 * @return int[]|null
 */
function volunteerSetupVisibleTeamIds(VolunteerSetupService $service, User $actor, int $ministryId): ?array
{
    $authz = $service->getAuthorizationService();

    if ($authz->canManageMinistry($actor, $ministryId)) {
        return null;
    }

    $managedTeamIds = $authz->getManagedTeamIds($actor);
    if ($managedTeamIds === []) {
        return [];
    }

    $teamIds = [];
    $rows = VolunteerTeamQuery::create()
        ->filterByMinistryId($ministryId)
        ->filterById($managedTeamIds, Criteria::IN)
        ->select(['Id'])
        ->find();

    foreach ($rows as $teamId) {
        $teamIds[] = (int) $teamId;
    }

    return $teamIds;
}

/**
 * @OA\Get(
 *     path="/volunteer/ministries/{ministryId}/positions",
 *     operationId="listVolunteerPositions",
 *     summary="Positions of a ministry",
 *     description="A ministry coordinator sees every position; a team leader sees the positions of the teams they lead (design §4.4 - the narrowing happens in the query).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="teamId", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="active", in="query", required=false, @OA\Schema(type="boolean")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="positions", type="array", @OA\Items(type="object")))
 *     )
 * )
 */
function listVolunteerPositions(Request $request, Response $response): Response
{
    $ministry = volunteerSetupFindMinistry($request);
    if ($ministry === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Ministry not found'), [], 404, null, $request);
    }

    $ministryId = (int) $ministry->getId();
    $service = new VolunteerSetupService();

    // A coordinator of the ministry sees all of it. A team leader sees their own
    // teams' positions and nothing else — the ids are handed to the query, not
    // used to sieve a hydrated list (§4.4). Neither means no business here.
    $visibleTeamIds = volunteerSetupVisibleTeamIds($service, volunteerSetupActor(), $ministryId);
    if ($visibleTeamIds === []) {
        return SlimUtils::renderErrorJSON($response, gettext('Not authorized for this ministry'), [], 403, null, $request);
    }

    $params = $request->getQueryParams();
    $teamId = isset($params['teamId']) && $params['teamId'] !== '' ? (int) $params['teamId'] : null;

    $positions = $service->listPositions(
        $ministryId,
        $teamId,
        volunteerSetupActiveFilter($request),
        $visibleTeamIds
    );
    $teamNames = volunteerSetupTeamNames($positions);

    return SlimUtils::renderJSON($response, [
        'positions' => array_map(
            static fn (VolunteerPosition $p): array => volunteerPositionToArray($p, $teamNames),
            $positions
        ),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/ministries/{ministryId}/positions",
 *     operationId="createVolunteerPosition",
 *     summary="Create a position in one of the ministry's teams",
 *     description="Every position belongs to a team. A ministry coordinator may create one in any of their teams; a team leader in their own only (design §4.6).
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"name","teamId"},
 *         @OA\Property(property="name", type="string", maxLength=100),
 *         @OA\Property(property="description", type="string", maxLength=255),
 *         @OA\Property(property="teamId", type="integer", description="Required: a position always belongs to a team"),
 *         @OA\Property(property="order", type="integer", description="Display order within the ministry")
 *     )),
 *     @OA\Response(response=400, description="The name or the teamId is missing, or the team belongs to another ministry"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry or team, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry or team"),
 *     @OA\Response(response=409, description="A position with that name already exists in this scope"),
 *     @OA\Response(response=201, description="Created")
 * )
 */
function createVolunteerPosition(Request $request, Response $response): Response
{
    $ministry = volunteerSetupFindMinistry($request);
    if ($ministry === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Ministry not found'), [], 404, null, $request);
    }

    $body = (array) $request->getParsedBody();

    // D18: a position always belongs to a team, and a ministry always has one, so an
    // absent teamId is a malformed payload rather than a "ministry-wide" request.
    if (!isset($body['teamId']) || $body['teamId'] === '' || $body['teamId'] === null) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('A position belongs to a team; choose one'),
            [],
            400,
            null,
            $request
        );
    }

    $team = VolunteerTeamQuery::create()->findPk((int) $body['teamId']);
    if ($team === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Team not found'), [], 404, null, $request);
    }

    try {
        $position = (new VolunteerSetupService())->createPosition(
            $ministry,
            $team,
            (string) ($body['name'] ?? ''),
            isset($body['description']) ? (string) $body['description'] : null,
            isset($body['order']) ? (int) $body['order'] : 0,
            volunteerSetupActor()
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    $teamNames = [(int) $team->getId() => $team->getName()];

    return SlimUtils::renderJSON($response, ['position' => volunteerPositionToArray($position, $teamNames)], 201);
}

/**
 * @OA\Get(
 *     path="/volunteer/positions/{positionId}",
 *     operationId="getVolunteerPosition",
 *     summary="One position",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="positionId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this position, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such position"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="position", type="object"))
 *     )
 * )
 */
function getVolunteerPosition(Request $request, Response $response): Response
{
    /** @var VolunteerPosition $position */
    $position = $request->getAttribute('volunteerPosition');

    return SlimUtils::renderJSON($response, [
        'position' => volunteerPositionToArray($position, volunteerSetupTeamNames([$position])),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/positions/{positionId}",
 *     operationId="updateVolunteerPosition",
 *     summary="Update a position, including activating or deactivating it",
 *     description="Deactivation is the documented alternative to deletion once a position has history (design §2.6); it never touches existing assignments.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="positionId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="name", type="string", maxLength=100),
 *         @OA\Property(property="description", type="string", maxLength=255),
 *         @OA\Property(property="teamId", type="integer", description="Moves the position to another of this ministry's teams; null is a 400"),
 *         @OA\Property(property="order", type="integer"),
 *         @OA\Property(property="active", type="boolean")
 *     )),
 *     @OA\Response(response=400, description="The name was sent empty, or the team belongs to another ministry"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this position, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such position"),
 *     @OA\Response(response=409, description="A position with that name already exists in this scope"),
 *     @OA\Response(response=200, description="Updated")
 * )
 */
function updateVolunteerPosition(Request $request, Response $response): Response
{
    /** @var VolunteerPosition $position */
    $position = $request->getAttribute('volunteerPosition');
    $service = new VolunteerSetupService();
    $actor = volunteerSetupActor();

    $fields = volunteerSetupFields($request, ['name', 'description', 'teamId', 'order', 'active'], ['active']);

    try {
        // The activation-only payload goes through the dedicated method so the
        // UI toggle and the API field can never drift apart (§2.6).
        if (array_key_exists('active', $fields) && count($fields) === 1) {
            $position = $service->setPositionActive($position, (bool) $fields['active'], $actor);
        } else {
            $position = $service->updatePosition($position, $fields, $actor);
        }
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderJSON($response, [
        'position' => volunteerPositionToArray($position, volunteerSetupTeamNames([$position])),
    ]);
}

/**
 * @OA\Delete(
 *     path="/volunteer/positions/{positionId}",
 *     operationId="deleteVolunteerPosition",
 *     summary="Delete a position that nothing references",
 *     description="Returns 409, naming the counts, once qualifications, staffing requirements or assignments reference the position - deactivate it instead (design §2.6).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="positionId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this position, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such position"),
 *     @OA\Response(response=409, description="The position is still referenced"),
 *     @OA\Response(response=200, description="Deleted")
 * )
 */
function deleteVolunteerPosition(Request $request, Response $response): Response
{
    /** @var VolunteerPosition $position */
    $position = $request->getAttribute('volunteerPosition');

    try {
        (new VolunteerSetupService())->deletePosition($position, volunteerSetupActor());
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderSuccessJSON($response);
}

// ─── The pool Group and qualifications (#9707, rewritten by D19) ─────────────
//
// Wire shapes and helpers first, then the handlers, matching the layout above.
//
// D1 still holds: a Group **is** the roster, and nothing here copies a
// membership row — every count and every name is read live off
// `person2group2role_p2g2r`, so somebody added in the Groups module is in the
// pool on the very next request, with no sync step.
//
// What D19 changed is that there is no longer anything to LINK. A ministry owns
// exactly one Group, created with it, and the endpoints below are about that
// group's membership rather than about the link that used to point at it.

/**
 * One person in a ministry's volunteer pool, or on a row of the qualification
 * matrix.
 *
 * `inPool` is what makes the matrix's union readable (D19): its rows are
 * everyone qualified for any of the positions in view ∪ everyone in the pool, so
 * a row needs to be able to say which of the two it is there for. A person in
 * the pool with no ticks yet is the "not qualified yet" hint on the screen; a
 * qualified person with `inPool: false` was taken out of the group after being
 * qualified, and is still perfectly assignable.
 *
 * @param array{displayName?: ?string, groupIds?: int[], qualifications?: int[], qualificationIds?: array<int, int>, inPool?: bool} $context
 */
function volunteerPoolPersonToArray(int $personId, array $context = []): array
{
    $held = $context['qualificationIds'] ?? [];

    return [
        'personId' => $personId,
        'displayName' => $context['displayName'] ?? '',
        'groupIds' => $context['groupIds'] ?? [],
        'inPool' => (bool) ($context['inPool'] ?? false),
        // §3.3.1's shape: the position ids this person may serve in.
        'qualifications' => array_keys($held),
        // Plus the row id behind each tick, so unticking a matrix box can revoke
        // that exact qualification without a lookup request. Cast to object so an
        // empty map serialises as {} rather than [].
        'qualificationIds' => (object) $held,
    ];
}

/**
 * One qualification row. `active: false` is a REVOKED qualification that is
 * still readable — that is the whole point of §2.7's "revocation is
 * deactivation", so the flag is part of the wire shape rather than a filter
 * applied before serialising.
 *
 * @param array{displayName?: ?string, positionName?: ?string, ministryId?: ?int, teamId?: ?int} $context
 */
function volunteerQualificationToArray(VolunteerQualification $qualification, array $context = []): array
{
    return [
        'id' => (int) $qualification->getId(),
        'personId' => (int) $qualification->getPersonId(),
        'displayName' => $context['displayName'] ?? null,
        'positionId' => (int) $qualification->getPositionId(),
        'positionName' => $context['positionName'] ?? null,
        'ministryId' => $context['ministryId'] ?? null,
        'teamId' => $context['teamId'] ?? null,
        'active' => (bool) $qualification->getActive(),
        'grantedDate' => $qualification->getGrantedDate('Y-m-d H:i:s'),
        'grantedByPersonId' => $qualification->getGrantedByPersonId() === null
            ? null
            : (int) $qualification->getGrantedByPersonId(),
        'notes' => $qualification->getNotes(),
    ];
}

/**
 * Person id → display name for a set of ids, in ONE query.
 *
 * V2 never stores a person's name: the roster rows it renders are projections
 * over `person_per`, resolved at read time (P8, §1.1 G1).
 *
 * @param int[] $personIds
 *
 * @return array<int, string>
 */
function volunteerSetupPersonNames(array $personIds): array
{
    if ($personIds === []) {
        return [];
    }

    $names = [];
    foreach (PersonQuery::create()->findPks($personIds) as $person) {
        $names[(int) $person->getId()] = $person->getFullName();
    }

    return $names;
}

/**
 * The pool panel's rows: everybody in the ministry's pool Group, alphabetically.
 *
 * Deliberately NOT the matrix's union — this list answers "who is in the pool",
 * which is the question the panel's Remove button acts on, so somebody who is
 * qualified but not a member must not appear in it with a Remove that would 404.
 *
 * @return array<int, array<string, mixed>>
 */
function volunteerSetupPoolRows(VolunteerSetupService $service, int $ministryId): array
{
    $membership = $service->getPoolMembership($ministryId);
    if ($membership === []) {
        return [];
    }

    $names = volunteerSetupPersonNames(array_keys($membership));

    $rows = [];
    foreach ($membership as $personId => $groupIds) {
        $rows[] = volunteerPoolPersonToArray((int) $personId, [
            'displayName' => $names[(int) $personId] ?? '',
            'groupIds' => $groupIds,
            'inPool' => true,
        ]);
    }

    usort($rows, static fn (array $a, array $b): int => strcasecmp($a['displayName'], $b['displayName']));

    return $rows;
}

/**
 * The qualification matrix's rows: everyone in the pool **union** everyone
 * qualified for any of the positions in view (D19).
 *
 * The union is the decision, not an implementation detail. Before D19 the matrix
 * was the pool and nothing else, so a coordinator who qualified somebody through
 * the person picker could not then see or untick them — the row they had just
 * created was invisible. And going the other way, a pool member with no ticks yet
 * is exactly who the coordinator opened this screen to find, so dropping them
 * because they hold no qualification would empty the screen of its whole purpose.
 * `inPool` tells the two apart on the row.
 *
 * `$positionIds` is what the qualification half is computed over, so a caller
 * that has already narrowed the positions (a team view) gets a matching
 * `qualifications` array rather than one that mentions columns it is not
 * showing — and the union follows it, so a team view does not drag in people
 * qualified only for another team's positions.
 *
 * @param int[] $positionIds
 *
 * @return array<int, array<string, mixed>>
 */
function volunteerSetupMemberRows(
    VolunteerSetupService $service,
    int $ministryId,
    ?int $teamId,
    array $positionIds
): array {
    $membership = $service->getPoolMembership($ministryId, $teamId);
    $qualifications = $service->getQualificationsByPerson($positionIds);

    $personIds = array_values(array_unique(array_merge(
        array_map('intval', array_keys($membership)),
        array_map('intval', array_keys($qualifications))
    )));

    if ($personIds === []) {
        return [];
    }

    $names = volunteerSetupPersonNames($personIds);

    $rows = [];
    foreach ($personIds as $personId) {
        $rows[] = volunteerPoolPersonToArray($personId, [
            'displayName' => $names[$personId] ?? '',
            'groupIds' => $membership[$personId] ?? [],
            'inPool' => isset($membership[$personId]),
            'qualificationIds' => $qualifications[$personId] ?? [],
        ]);
    }

    // Alphabetical: a matrix is read by looking someone up, not by id order.
    usort($rows, static fn (array $a, array $b): int => strcasecmp($a['displayName'], $b['displayName']));

    return $rows;
}

/**
 * The position ids a member/matrix view is computed over, plus the position
 * rows themselves. Only active positions become matrix columns — a deactivated
 * position keeps its history but is not something new people get qualified for
 * (§2.6).
 *
 * @return array{positions: VolunteerPosition[], positionIds: int[]}
 */
function volunteerSetupMatrixPositions(VolunteerSetupService $service, int $ministryId, ?int $teamId): array
{
    $positions = $service->listPositions($ministryId, $teamId, true);

    return [
        'positions' => $positions,
        'positionIds' => array_map(static fn (VolunteerPosition $p): int => (int) $p->getId(), $positions),
    ];
}

/** `?teamId=`; absent or empty means "the whole ministry". */
function volunteerSetupTeamFilter(Request $request): ?int
{
    $params = $request->getQueryParams();

    return isset($params['teamId']) && $params['teamId'] !== '' ? (int) $params['teamId'] : null;
}

/**
 * @OA\Get(
 *     path="/volunteer/ministries/{ministryId}/pool",
 *     operationId="listVolunteerPoolMembers",
 *     summary="The people in this ministry's volunteer pool",
 *     description="The membership of the ministry's own Group (group_grp.grp_ministry_id), read live from person2group2role_p2g2r - V2 stores no people (design D1, D19). Alphabetical.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="groupId", type="integer", nullable=true),
 *             @OA\Property(property="groupName", type="string", nullable=true),
 *             @OA\Property(property="members", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
function listVolunteerPoolMembers(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');

    $service = new VolunteerSetupService();
    $ministryId = (int) $ministry->getId();
    $group = $service->getPoolGroup($ministryId);

    return SlimUtils::renderJSON($response, [
        'groupId' => $group === null ? null : (int) $group->getId(),
        'groupName' => $group === null ? null : (string) $group->getName(),
        'members' => volunteerSetupPoolRows($service, $ministryId),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/ministries/{ministryId}/pool/{personId}",
 *     operationId="addVolunteerPoolMember",
 *     summary="Put a person in this ministry's volunteer pool",
 *     description="Writes a person2group2role_p2g2r row on the ministry's own Group with the group's default role, firing the same GROUP_MEMBER_ADDED plugin hook the Groups module fires. A ministry coordinator may do this WITHOUT the global Manage Groups permission (design D19) - the Propel hooks allow it because the group carries this ministry's id. Idempotent: somebody already in the pool is 200, not 409.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry, person, or pool group"),
 *     @OA\Response(response=200, description="They were already in the pool"),
 *     @OA\Response(response=201, description="Added")
 * )
 */
function addVolunteerPoolMember(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');
    $personId = (int) SlimUtils::getRouteArgument($request, 'personId');

    try {
        $added = (new VolunteerSetupService())->addPoolMember(
            (int) $ministry->getId(),
            $personId,
            volunteerSetupActor()
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderJSON($response, ['personId' => $personId, 'added' => $added], $added ? 201 : 200);
}

/**
 * @OA\Delete(
 *     path="/volunteer/ministries/{ministryId}/pool/{personId}",
 *     operationId="removeVolunteerPoolMember",
 *     summary="Take a person out of this ministry's volunteer pool",
 *     description="Deletes the membership row and fires GROUP_MEMBER_REMOVED. Their qualifications are NOT revoked (design D19): the two are independent, and a qualified non-member is still assignable.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry, or that person is not in the pool"),
 *     @OA\Response(response=200, description="Removed")
 * )
 */
function removeVolunteerPoolMember(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');

    try {
        (new VolunteerSetupService())->removePoolMember(
            (int) $ministry->getId(),
            (int) SlimUtils::getRouteArgument($request, 'personId'),
            volunteerSetupActor()
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Delete(
 *     path="/volunteer/ministries/{ministryId}/volunteers/{personId}",
 *     operationId="removeVolunteerFromMinistry",
 *     summary="Take one person out of a ministry entirely",
 *     description="In one transaction: revokes every active qualification they hold for a position of this ministry (revocation is deactivation, design section 2.7), cancels every live assignment of theirs on a still-to-come occurrence of the ministry through the ordinary cancel path so the outbox rows are cancelled and the response trail is appended, and removes them from the ministry's pool Group through the managed-write context. Past assignments are service history and are left alone. Ministry-level authority: a team leader gets 403.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry or person"),
 *     @OA\Response(response=200, description="Removed",
 *         @OA\JsonContent(
 *             @OA\Property(property="personId", type="integer"),
 *             @OA\Property(property="qualifications", type="integer", description="Qualifications revoked"),
 *             @OA\Property(property="assignments", type="integer", description="Upcoming assignments cancelled"),
 *             @OA\Property(property="removedFromPool", type="boolean")
 *         )
 *     )
 * )
 */
function removeVolunteerFromMinistry(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');
    $personId = (int) SlimUtils::getRouteArgument($request, 'personId');

    try {
        $result = (new VolunteerAssignmentService())->removeVolunteerFromMinistry(
            $ministry,
            $personId,
            volunteerSetupActor()
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderJSON($response, ['personId' => $personId] + $result);
}

/**
 * @OA\Get(
 *     path="/volunteer/ministries/{ministryId}/members",
 *     operationId="listVolunteerMatrixMembers",
 *     summary="The rows of this ministry's qualification matrix",
 *     description="Everyone in the ministry's pool Group UNION everyone actively qualified for one of the positions in view (design D19). Each row carries inPool and the position ids that person is qualified for, so the matrix renders from one response (section 5.4).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="teamId", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="members", type="array", @OA\Items(type="object")))
 *     )
 * )
 */
function listVolunteerMatrixMembers(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');

    $service = new VolunteerSetupService();
    $ministryId = (int) $ministry->getId();
    $teamId = volunteerSetupTeamFilter($request);
    $positions = volunteerSetupMatrixPositions($service, $ministryId, $teamId);

    return SlimUtils::renderJSON($response, [
        'members' => volunteerSetupMemberRows($service, $ministryId, $teamId, $positions['positionIds']),
    ]);
}

/**
 * @OA\Get(
 *     path="/volunteer/teams/{teamId}/members",
 *     operationId="listVolunteerTeamMembers",
 *     summary="The people in one team's volunteer pools",
 *     description="The team's own pools UNION the parent ministry's pools, because a ministry-wide pool feeds every team under it.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="teamId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this team, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such team"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="members", type="array", @OA\Items(type="object")))
 *     )
 * )
 */
function listVolunteerTeamMembers(Request $request, Response $response): Response
{
    /** @var VolunteerTeam $team */
    $team = $request->getAttribute('volunteerTeam');

    $service = new VolunteerSetupService();
    $ministryId = (int) $team->getMinistryId();
    $teamId = (int) $team->getId();
    $positions = volunteerSetupMatrixPositions($service, $ministryId, $teamId);

    return SlimUtils::renderJSON($response, [
        'members' => volunteerSetupMemberRows($service, $ministryId, $teamId, $positions['positionIds']),
    ]);
}

/**
 * @OA\Get(
 *     path="/volunteer/ministries/{ministryId}/qualification-matrix",
 *     operationId="getVolunteerQualificationMatrix",
 *     summary="Everything the qualification matrix needs, in one response",
 *     description="Pool members UNION everyone qualified for a position in view down the side (design D19), active positions across the top, and each person's qualified position ids - section 5.4 requires the matrix to handle 15-200 people without re-fetching per cell. A row's inPool flag says which half it is there for.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="teamId", in="query", required=false, @OA\Schema(type="integer"),
 *         description="Narrow both the columns and the pool people to one team"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="ministryId", type="integer"),
 *             @OA\Property(property="teamId", type="integer", nullable=true),
 *             @OA\Property(property="positions", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="people", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
function getVolunteerQualificationMatrix(Request $request, Response $response): Response
{
    /** @var VolunteerMinistry $ministry */
    $ministry = $request->getAttribute('volunteerMinistry');

    $service = new VolunteerSetupService();
    $ministryId = (int) $ministry->getId();
    $teamId = volunteerSetupTeamFilter($request);

    $positions = volunteerSetupMatrixPositions($service, $ministryId, $teamId);
    $teamNames = volunteerSetupTeamNames($positions['positions']);

    return SlimUtils::renderJSON($response, [
        'ministryId' => $ministryId,
        'teamId' => $teamId,
        'positions' => array_map(
            static fn (VolunteerPosition $p): array => volunteerPositionToArray($p, $teamNames),
            $positions['positions']
        ),
        'people' => volunteerSetupMemberRows($service, $ministryId, $teamId, $positions['positionIds']),
    ]);
}

/**
 * @OA\Get(
 *     path="/volunteer/positions/{positionId}/qualifications",
 *     operationId="listVolunteerQualifications",
 *     summary="Who is qualified for a position",
 *     description="Includes revoked rows by default: revocation is deactivation, so the grant history stays readable (design section 2.7). Pass active=1 for the eligibility list.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="positionId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="active", in="query", required=false, @OA\Schema(type="boolean")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this position, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such position"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="qualifications", type="array", @OA\Items(type="object")))
 *     )
 * )
 */
function listVolunteerQualifications(Request $request, Response $response): Response
{
    /** @var VolunteerPosition $position */
    $position = $request->getAttribute('volunteerPosition');

    $service = new VolunteerSetupService();
    $qualifications = $service->listQualifications(
        (int) $position->getId(),
        volunteerSetupActiveFilter($request)
    );

    $names = volunteerSetupPersonNames(array_map(
        static fn (VolunteerQualification $q): int => (int) $q->getPersonId(),
        $qualifications
    ));

    return SlimUtils::renderJSON($response, [
        'qualifications' => array_map(
            static fn (VolunteerQualification $q): array => volunteerQualificationToArray($q, [
                'displayName' => $names[(int) $q->getPersonId()] ?? null,
                'positionName' => $position->getName(),
                'ministryId' => (int) $position->getMinistryId(),
                'teamId' => (int) $position->getTeamId(),
            ]),
            $qualifications
        ),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/positions/{positionId}/qualifications",
 *     operationId="grantVolunteerQualification",
 *     summary="Qualify a person for a position",
 *     description="Idempotent by UNIQUE (vqal_per_ID, vqal_vpos_ID): 201 when the row is new, 200 when it already existed - a re-grant reactivates a revoked row rather than inserting a second. The person does NOT have to be in a linked pool: the pool is the candidate set, the qualification is the eligibility (design section 2.5).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="positionId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"personId"},
 *         @OA\Property(property="personId", type="integer"),
 *         @OA\Property(property="notes", type="string", maxLength=255)
 *     )),
 *     @OA\Response(response=400, description="personId is missing or not an integer"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this position, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such position or person"),
 *     @OA\Response(response=200, description="The qualification already existed and is active"),
 *     @OA\Response(response=201, description="Granted")
 * )
 */
function grantVolunteerQualification(Request $request, Response $response): Response
{
    /** @var VolunteerPosition $position */
    $position = $request->getAttribute('volunteerPosition');
    $body = (array) $request->getParsedBody();
    $personId = (int) ($body['personId'] ?? 0);

    $service = new VolunteerSetupService();
    // Asked BEFORE the write, which is the only moment the answer exists: the
    // grant itself is an upsert (volunteer-scopes.php takes the same shape).
    $existing = $service->findQualification($personId, (int) $position->getId());

    try {
        $qualification = $service->grantQualification(
            $personId,
            $position,
            volunteerSetupActor(),
            isset($body['notes']) ? (string) $body['notes'] : null
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    $names = volunteerSetupPersonNames([(int) $qualification->getPersonId()]);

    return SlimUtils::renderJSON(
        $response,
        [
            'qualification' => volunteerQualificationToArray($qualification, [
                'displayName' => $names[(int) $qualification->getPersonId()] ?? null,
                'positionName' => $position->getName(),
                'ministryId' => (int) $position->getMinistryId(),
                'teamId' => (int) $position->getTeamId(),
            ]),
        ],
        $existing === null ? 201 : 200
    );
}

/**
 * @OA\Post(
 *     path="/volunteer/positions/{positionId}/qualifications/from-cart",
 *     operationId="grantVolunteerQualificationsFromCart",
 *     summary="Qualify everyone in the session cart for one position",
 *     description="The V2 Cart sink (design P5/P6): the route reads Cart::getCartPeople() and hands the ids to VolunteerSetupService, exactly as Cart::emptyToGroup()'s successors do. The cart is NOT emptied - the same selection is usually wanted for a second position. Takes no request body.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="positionId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=400, description="The cart is empty"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this position, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such position"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="granted", type="integer"),
 *             @OA\Property(property="reactivated", type="integer"),
 *             @OA\Property(property="existing", type="integer"),
 *             @OA\Property(property="skipped", type="integer")
 *         )
 *     )
 * )
 */
function grantVolunteerQualificationsFromCart(Request $request, Response $response): Response
{
    /** @var VolunteerPosition $position */
    $position = $request->getAttribute('volunteerPosition');

    $personIds = [];
    foreach (Cart::getCartPeople() as $person) {
        /** @var Person $person */
        $personIds[] = (int) $person->getId();
    }

    if ($personIds === []) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Add people to the cart before qualifying them'),
            [],
            400,
            null,
            $request
        );
    }

    try {
        $result = (new VolunteerSetupService())->grantQualifications(
            $personIds,
            $position,
            volunteerSetupActor()
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    return SlimUtils::renderJSON($response, [
        'granted' => $result['granted'],
        'reactivated' => $result['reactivated'],
        'existing' => $result['existing'],
        'skipped' => $result['skipped'],
    ]);
}

/**
 * @OA\Delete(
 *     path="/volunteer/qualifications/{qualificationId}",
 *     operationId="revokeVolunteerQualification",
 *     summary="Revoke a qualification by deactivating it",
 *     description="The row is KEPT with active=false (design section 2.7). Historical assignments stay valid because volunteer_assignment_vasg has no foreign key to a qualification - it references the person and the position directly.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="qualificationId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this qualification, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such qualification"),
 *     @OA\Response(response=200, description="Revoked; the row is returned with active=false")
 * )
 */
function revokeVolunteerQualification(Request $request, Response $response): Response
{
    /** @var VolunteerQualification $qualification */
    $qualification = $request->getAttribute('volunteerQualification');

    try {
        $qualification = (new VolunteerSetupService())->revokeQualification(
            $qualification,
            volunteerSetupActor()
        );
    } catch (\Throwable $e) {
        return volunteerSetupError($request, $response, $e);
    }

    $position = VolunteerPositionQuery::create()->findPk((int) $qualification->getPositionId());
    $names = volunteerSetupPersonNames([(int) $qualification->getPersonId()]);

    return SlimUtils::renderJSON($response, [
        'qualification' => volunteerQualificationToArray($qualification, [
            'displayName' => $names[(int) $qualification->getPersonId()] ?? null,
            'positionName' => $position?->getName(),
            'ministryId' => $position === null ? null : (int) $position->getMinistryId(),
            'teamId' => $position?->getTeamId() === null ? null : (int) $position->getTeamId(),
        ]),
    ]);
}

/**
 * @OA\Get(
 *     path="/volunteer/people/{personId}/qualifications",
 *     operationId="listVolunteerQualificationsForPerson",
 *     summary="Everything one person is qualified for, scoped to the caller",
 *     description="A global manager sees every ministry; a coordinator sees only their own, and the narrowing happens in the query (design section 4.4).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="personId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Volunteer coordinator access is required, or V2 is not enabled"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="qualifications", type="array", @OA\Items(type="object")))
 *     )
 * )
 */
function listVolunteerQualificationsForPerson(Request $request, Response $response): Response
{
    $personId = (int) SlimUtils::getRouteArgument($request, 'personId');

    $service = new VolunteerSetupService();
    $authz = $service->getAuthorizationService();
    $actor = volunteerSetupActor();

    // isGlobalManager() first: an administrator holds no explicit grants, so
    // getManagedMinistryIds() would hand back [] and hide everything (§4.4).
    $ministryIds = $authz->isGlobalManager($actor) ? null : $authz->getManagedMinistryIds($actor);
    $qualifications = $service->listQualificationsForPerson($personId, $ministryIds);

    $positionIds = array_values(array_unique(array_map(
        static fn (VolunteerQualification $q): int => (int) $q->getPositionId(),
        $qualifications
    )));

    $positions = [];
    if ($positionIds !== []) {
        foreach (VolunteerPositionQuery::create()->findPks($positionIds) as $position) {
            $positions[(int) $position->getId()] = $position;
        }
    }

    $names = volunteerSetupPersonNames([$personId]);

    return SlimUtils::renderJSON($response, [
        'qualifications' => array_map(
            static function (VolunteerQualification $q) use ($positions, $names, $personId): array {
                $position = $positions[(int) $q->getPositionId()] ?? null;

                return volunteerQualificationToArray($q, [
                    'displayName' => $names[$personId] ?? null,
                    'positionName' => $position?->getName(),
                    'ministryId' => $position === null ? null : (int) $position->getMinistryId(),
                    'teamId' => $position?->getTeamId() === null ? null : (int) $position->getTeamId(),
                ]);
            },
            $qualifications
        ),
    ]);
}
