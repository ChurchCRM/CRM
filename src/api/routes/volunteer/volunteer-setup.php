<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Exceptions\VolunteerSetupException;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPosition;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\VolunteerSetupService;
use ChurchCRM\Slim\Middleware\Api\VolunteerMinistryMiddleware;
use ChurchCRM\Slim\Middleware\Api\VolunteerPositionMiddleware;
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
 * row: a team-scoped position belongs to its team, a ministry-wide one to the
 * ministry.
 *
 * Read scoping is done in the QUERY, never after hydration (§4.4). The list
 * handlers call the service, which asks `isGlobalManager()` first (an
 * administrator holds no explicit grants) and short-circuits an empty allow-list
 * rather than handing `[]` to a filter.
 *
 * #9707 adds the pool and qualification routes to this file; #9708 opens its own
 * group in volunteer-schedule.php.
 */
$app->group('/volunteer', function (RouteCollectorProxy $group): void {
    $group->group('', function (RouteCollectorProxy $setup): void {
        // ── Ministries ────────────────────────────────────────────────────
        $setup->get('/ministries', 'listVolunteerMinistries');

        $setup->post('/ministries', 'createVolunteerMinistry')
            ->add(new InputSanitizationMiddleware([
                'name' => 'text',
                'description' => 'text',
            ]))
            ->add(VolunteerManagerRoleAuthMiddleware::class);

        $setup->get('/ministries/{ministryId:[0-9]+}', 'getVolunteerMinistry')
            ->add(new VolunteerMinistryMiddleware());

        $setup->post('/ministries/{ministryId:[0-9]+}', 'updateVolunteerMinistry')
            ->add(new InputSanitizationMiddleware([
                'name' => 'text',
                'description' => 'text',
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
    ];
}

function volunteerTeamToArray(VolunteerTeam $team, int $positionCount = 0): array
{
    return [
        'id' => (int) $team->getId(),
        'ministryId' => (int) $team->getMinistryId(),
        'name' => $team->getName(),
        'description' => $team->getDescription(),
        'active' => (bool) $team->getActive(),
        'positionCount' => $positionCount,
    ];
}

/**
 * @param array<int, string> $teamNames team id → name, so a position row renders
 *                                      its scope without a query per row
 */
function volunteerPositionToArray(VolunteerPosition $position, array $teamNames = []): array
{
    $teamId = $position->getTeamId() === null ? null : (int) $position->getTeamId();

    return [
        'id' => (int) $position->getId(),
        'ministryId' => (int) $position->getMinistryId(),
        'teamId' => $teamId,
        'teamName' => $teamId === null ? null : ($teamNames[$teamId] ?? null),
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
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], $e->getStatusCode(), null, $request);
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
        if ($position->getTeamId() !== null) {
            $teamIds[(int) $position->getTeamId()] = true;
        }
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

    return SlimUtils::renderJSON($response, ['ministry' => volunteerMinistryToArray($ministry)], 201);
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

    $teamPositionCounts = $service->countPositionsByTeam(
        array_map(static fn (VolunteerTeam $t): int => (int) $t->getId(), $teams)
    );
    $teamNames = volunteerSetupTeamNames($positions);

    return SlimUtils::renderJSON($response, [
        'ministry' => volunteerMinistryToArray($ministry, [
            'teamCount' => count($teams),
            'positionCount' => count($positions),
        ]),
        'teams' => array_map(
            static fn (VolunteerTeam $t): array => volunteerTeamToArray($t, $teamPositionCounts[(int) $t->getId()] ?? 0),
            $teams
        ),
        'positions' => array_map(
            static fn (VolunteerPosition $p): array => volunteerPositionToArray($p, $teamNames),
            $positions
        ),
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
 *         @OA\Property(property="active", type="boolean")
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
            volunteerSetupFields($request, ['name', 'description', 'active'], ['active']),
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
 *     summary="Delete a team that owns no positions or schedules",
 *     description="Returns 409 while the team still owns positions or schedules: vpos_vtem_ID is ON DELETE SET NULL, so deleting it would silently promote every team-scoped position to ministry-wide.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="teamId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this team, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such team"),
 *     @OA\Response(response=409, description="The team still owns positions or schedules"),
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
 *     summary="Create a position, ministry-wide or scoped to one team",
 *     description="A ministry coordinator may create either; a team leader may create one in their own team only (design §4.6).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"name"},
 *         @OA\Property(property="name", type="string", maxLength=100),
 *         @OA\Property(property="description", type="string", maxLength=255),
 *         @OA\Property(property="teamId", type="integer", nullable=true, description="Omit or null for a ministry-wide position"),
 *         @OA\Property(property="order", type="integer", description="Display order within the ministry")
 *     )),
 *     @OA\Response(response=400, description="The name is missing, or the team belongs to another ministry"),
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
    $team = null;

    if (isset($body['teamId']) && $body['teamId'] !== '' && $body['teamId'] !== null) {
        $team = VolunteerTeamQuery::create()->findPk((int) $body['teamId']);
        if ($team === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Team not found'), [], 404, null, $request);
        }
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

    $teamNames = $team === null ? [] : [(int) $team->getId() => $team->getName()];

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
 *         @OA\Property(property="teamId", type="integer", nullable=true),
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
