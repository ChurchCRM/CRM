<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Volunteer\VolunteerException;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerAssignment;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerNotification;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerResponse;
use ChurchCRM\model\ChurchCRM\VolunteerSchedule;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSwap;
use ChurchCRM\Volunteer\Service\VolunteerAssignmentService;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;
use ChurchCRM\Volunteer\Service\VolunteerNotificationService;
use ChurchCRM\Volunteer\Service\VolunteerScheduleService;
use ChurchCRM\Volunteer\Middleware\VolunteerAssignmentMiddleware;
use ChurchCRM\Volunteer\Middleware\VolunteerOccurrenceMiddleware;
use ChurchCRM\Volunteer\Middleware\VolunteerSwapMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Volunteer\Middleware\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\Volunteer\Middleware\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Volunteer Management V2 API — the assignment half of design §3.3.2 (#9709).
 *
 * The coordinator surface: the staffing workhorse, the eligible picker, assign, the
 * status changes (including recording a response on a volunteer's behalf), cancel,
 * re-notify, the gap list and the swap queue. The volunteer's own four
 * endpoints live in `ministries-me.php`, which carries no role gate at all — mixing the
 * two surfaces in one file is exactly how a `personId` parameter ends up somewhere it
 * must never be (§3.3.3, §4.7).
 *
 * Every route here is coordinator-or-above: the group chains
 * `VolunteerCoordinatorRoleAuthMiddleware` for the coarse "do you coordinate anything"
 * question and an entity middleware for the per-record one, and the service asks
 * `VolunteerAuthorizationService` a third time because it is also reachable from the
 * member surface, where no entity middleware ran (§4.5, three layers).
 *
 * **This group opens its own `$app->group('/ministries', …)` and chains
 * `VolunteerV2EnabledMiddleware` itself.** Slim 4 scopes `->add()` to the single
 * `RouteCollectorProxy` it is chained on; nothing propagates from the group in
 * `ministries-status.php` or `ministries-schedule.php`, and an ungated group would be
 * reachable in every rollout state (§3.3).
 *
 * Middleware order is LIFO — the last `->add()` runs first. On every route:
 * rollout gate → role gate → entity load + per-record scope check → sanitizer → handler.
 *
 * No business logic lives in this file. Every rule — I1–I8, the transition table, the
 * one-transaction swap approval, every notification enqueue — is in
 * `VolunteerAssignmentService`, and these handlers translate an exception's
 * `getStatusCode()` into a response and nothing more.
 */
$app->group('/ministries', function (RouteCollectorProxy $group): void {
    // ── One occurrence: the staffing workhorse (§5.5) ───────────────────────
    $group->group('/occurrences/{occurrenceId:[0-9]+}', function (RouteCollectorProxy $occurrence): void {
        $occurrence->get('/staffing', 'getVolunteerOccurrenceStaffing');
        $occurrence->get('/eligible', 'listVolunteerEligiblePeople');
        $occurrence->post('/assignments', 'createVolunteerAssignment')
            ->add(new InputSanitizationMiddleware([
                'positionId' => 'int',
                'personId' => 'int',
                'notes' => 'text',
            ]));
    })->add(new VolunteerOccurrenceMiddleware());

    // ── One assignment ──────────────────────────────────────────────────────
    $group->group('/assignments/{assignmentId:[0-9]+}', function (RouteCollectorProxy $assignment): void {
        $assignment->get('', 'getVolunteerAssignment');
        $assignment->post('/status', 'setVolunteerAssignmentStatus')
            ->add(new InputSanitizationMiddleware([
                // Only the three a coordinator may set directly; `substituted` comes
                // from a swap approval and `completed` from the timer job (§2.11.1).
                'status' => 'enum:' . implode(',', [
                    VolunteerAssignment::STATUS_ACCEPTED,
                    VolunteerAssignment::STATUS_DECLINED,
                    VolunteerAssignment::STATUS_CANCELLED,
                ]),
                'comment' => 'text',
            ]));
        $assignment->delete('', 'deleteVolunteerAssignment');
        $assignment->post('/notify', 'notifyVolunteerAssignment');
        $assignment->get('/notifications', 'listVolunteerAssignmentNotifications');
    })->add(new VolunteerAssignmentMiddleware());

    // ── Gaps across the caller's scope ──────────────────────────────────────
    $group->get('/gaps', 'listVolunteerGaps');

    // ── The swap queue ──────────────────────────────────────────────────────
    // The collection is declared before the parameterised group so the literal path
    // is not swallowed by it.
    $group->get('/swaps', 'listVolunteerSwaps');

    $group->group('/swaps/{swapId:[0-9]+}', function (RouteCollectorProxy $swap): void {
        $swap->post('/approve', 'approveVolunteerSwap')
            ->add(new InputSanitizationMiddleware(['comment' => 'text']));
        $swap->post('/reject', 'rejectVolunteerSwap')
            ->add(new InputSanitizationMiddleware(['comment' => 'text']));
    })->add(new VolunteerSwapMiddleware());

    // There is no cart sink on this surface any more. "Assign everyone in the cart"
    // was taken off the occurrence page by the product owner: assigning is a
    // per-person act with per-person eligibility rules (I1-I5), and a button that
    // half-succeeded and reported a list of reasons was a worse answer than the
    // single-person Assign flow beside it. The Cart still feeds V2 — it fills the
    // ministry's volunteer POOL (`/ministries/{id}/pool/from-cart`, ministries-setup.php).
})->add(VolunteerCoordinatorRoleAuthMiddleware::class)->add(new VolunteerV2EnabledMiddleware());

// ─── Wire shapes ─────────────────────────────────────────────────────────────
//
// One array per entity, shared by every handler below (and by ministries-me.php) so a
// field can never be spelled two ways across the two surfaces. Names that would need a
// query per row are passed in from a single lookup instead.

/**
 * One assignment for the wire.
 *
 * `attendance` is present only for a linked occurrence (E10) and is read-only — V2
 * writes nothing to `event_attend`. `replacesAssignmentId` is what makes a substitution
 * chain readable from the client without a second request.
 *
 * @param array<int, string> $personNames
 * @param array<int, string> $attendance
 */
function volunteerAssignmentToArray(
    VolunteerAssignment $assignment,
    array $personNames = [],
    array $attendance = [],
    ?string $positionName = null
): array {
    $personId = (int) $assignment->getPersonId();

    return [
        'id' => (int) $assignment->getId(),
        'occurrenceId' => (int) $assignment->getOccurrenceId(),
        'positionId' => (int) $assignment->getPositionId(),
        'positionName' => $positionName,
        'personId' => $personId,
        'displayName' => $personNames[$personId] ?? null,
        'requirementId' => $assignment->getRequirementId() === null ? null : (int) $assignment->getRequirementId(),
        'status' => $assignment->getStatus(),
        'source' => $assignment->getSource(),
        'assignedDate' => $assignment->getAssignedDate('Y-m-d H:i:s'),
        'assignedByPersonId' => $assignment->getAssignedByPersonId() === null
            ? null
            : (int) $assignment->getAssignedByPersonId(),
        'respondedDate' => $assignment->getRespondedDate('Y-m-d H:i:s'),
        'replacesAssignmentId' => $assignment->getReplacesAssignmentId() === null
            ? null
            : (int) $assignment->getReplacesAssignmentId(),
        'notes' => $assignment->getNotes(),
        'attendance' => $attendance[$personId] ?? null,
    ];
}

/**
 * One response row. The history is what `GET /assignments/{id}` exposes so a spec can
 * count it and prove idempotency (§6.6) — and what a coordinator reads to see that the
 * decline was recorded by the office, not by the volunteer (`channel`).
 *
 * @param array<int, string> $personNames
 */
function volunteerResponseToArray(VolunteerResponse $row, array $personNames = []): array
{
    $personId = (int) $row->getPersonId();

    return [
        'id' => (int) $row->getId(),
        'assignmentId' => (int) $row->getAssignmentId(),
        'personId' => $personId,
        'displayName' => $personNames[$personId] ?? null,
        'response' => $row->getResponse(),
        'responseDate' => $row->getResponseDate('Y-m-d H:i:s'),
        'channel' => $row->getChannel(),
        'comment' => $row->getComment(),
    ];
}

/**
 * One swap for the wire, carrying the names and the occurrence a queue needs so the
 * screen does not have to fetch each assignment to render a row.
 *
 * @param array<int, string> $personNames
 */
function volunteerSwapToArray(
    VolunteerSwap $swap,
    array $personNames = [],
    ?VolunteerAssignment $assignment = null,
    ?string $positionName = null
): array {
    $proposedBy = (int) $swap->getProposedByPersonId();
    $proposed = (int) $swap->getProposedPersonId();

    return [
        'id' => (int) $swap->getId(),
        'assignmentId' => (int) $swap->getAssignmentId(),
        'occurrenceId' => $assignment === null ? null : (int) $assignment->getOccurrenceId(),
        'positionId' => $assignment === null ? null : (int) $assignment->getPositionId(),
        'positionName' => $positionName,
        'proposedByPersonId' => $proposedBy,
        'proposedByName' => $personNames[$proposedBy] ?? null,
        'proposedPersonId' => $proposed,
        'proposedPersonName' => $personNames[$proposed] ?? null,
        'status' => $swap->getStatus(),
        'proposedDate' => $swap->getProposedDate('Y-m-d H:i:s'),
        'decidedDate' => $swap->getDecidedDate('Y-m-d H:i:s'),
        'decidedByPersonId' => $swap->getDecidedByPersonId() === null
            ? null
            : (int) $swap->getDecidedByPersonId(),
        'comment' => $swap->getComment(),
    ];
}

// ─── Shared helpers ──────────────────────────────────────────────────────────

/**
 * Render a service failure using the status the service chose.
 *
 * `getExtra()` is merged into the envelope, which is how §2.11.1's "409 with the current
 * status in the body" arrives as a field rather than as a sentence a client would have
 * to parse. Anything that is not a VolunteerException is a genuine 500 and is
 * logged with its exception, never echoed.
 */
function volunteerAssignmentError(Request $request, Response $response, \Throwable $e): Response
{
    if ($e instanceof VolunteerException) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), $e->getExtra(), $e->getStatusCode(), null, $request);
    }

    return SlimUtils::renderErrorJSON($response, gettext('The volunteer assignment could not be saved'), [], 500, $e, $request);
}

function volunteerAssignmentActor(): User
{
    return AuthenticationManager::getCurrentUser();
}

/**
 * Display names for a set of people, in one query.
 *
 * @param int[] $personIds
 *
 * @return array<int, string>
 */
function volunteerAssignmentPersonNames(array $personIds): array
{
    $personIds = array_values(array_unique(array_filter(array_map('intval', $personIds))));
    if ($personIds === []) {
        return [];
    }

    $names = [];
    foreach (PersonQuery::create()->filterById($personIds, Criteria::IN)->find() as $person) {
        /** @var Person $person */
        $names[(int) $person->getId()] = (string) $person->getFullName();
    }

    return $names;
}

/**
 * Position names for a set of ids, in one query.
 *
 * @param int[] $positionIds
 *
 * @return array<int, string>
 */
function volunteerAssignmentPositionNames(array $positionIds): array
{
    $positionIds = array_values(array_unique(array_filter(array_map('intval', $positionIds))));
    if ($positionIds === []) {
        return [];
    }

    $names = [];
    foreach (VolunteerPositionQuery::create()->filterById($positionIds, Criteria::IN)->find() as $position) {
        $names[(int) $position->getId()] = (string) $position->getName();
    }

    return $names;
}

/**
 * The occurrence ids the caller may manage inside a date window.
 *
 * Scoping happens in the QUERY, never in PHP after hydration (§4.4), and an
 * administrator or global manager has no explicit grants at all — which is why
 * `isGlobalManager()` is asked first (see VolunteerAuthorizationService's class
 * docblock). An empty allow-list short-circuits to no rows.
 *
 * @return int[]
 */
function volunteerScopedOccurrenceIds(
    User $user,
    VolunteerAuthorizationService $authz,
    ?string $from = null,
    ?string $to = null,
    ?int $ministryId = null
): array {
    $scheduleQuery = VolunteerScheduleQuery::create();

    if (!$authz->isGlobalManager($user)) {
        $ministryIds = $authz->getManagedMinistryIds($user);
        $teamIds = $authz->getManagedTeamIds($user);
        if ($ministryIds === [] && $teamIds === []) {
            return [];
        }

        $scheduleQuery
            ->condition('byMinistry', 'VolunteerSchedule.MinistryId IN ?', $ministryIds === [] ? [0] : $ministryIds)
            ->condition('byTeam', 'VolunteerSchedule.TeamId IN ?', $teamIds === [] ? [0] : $teamIds)
            ->where(['byMinistry', 'byTeam'], Criteria::LOGICAL_OR);
    }

    if ($ministryId !== null) {
        $scheduleQuery->filterByMinistryId($ministryId);
    }

    $scheduleIds = array_map('intval', $scheduleQuery->select(['Id'])->find()->toArray());
    if ($scheduleIds === []) {
        return [];
    }

    $query = VolunteerOccurrenceQuery::create()->filterByScheduleId($scheduleIds, Criteria::IN);
    if ($from !== null) {
        $query->filterByOccurrenceDate($from, Criteria::GREATER_EQUAL);
    }
    if ($to !== null) {
        $query->filterByOccurrenceDate($to, Criteria::LESS_EQUAL);
    }

    return array_map('intval', $query
        ->limit(VolunteerScheduleService::MAX_OCCURRENCE_LIST)
        ->select(['Id'])
        ->find()
        ->toArray());
}

// ─── Handlers ────────────────────────────────────────────────────────────────

/**
 * @OA\Get(
 *     path="/ministries/occurrences/{occurrenceId}/staffing",
 *     operationId="getVolunteerOccurrenceStaffing",
 *     summary="Requirements, live/gap counts and assignment rows for one occurrence",
 *     description="The workhorse coordinator read (design section 5.5). Requirements come from VolunteerScheduleService::getEffectiveRequirements(); the counts come from VolunteerAssignmentService::getGaps(), the single gap implementation. Assignments for a position that has no requirement are returned under otherAssignments rather than being hidden.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="occurrenceId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this occurrence, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such occurrence"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function getVolunteerOccurrenceStaffing(Request $request, Response $response): Response
{
    /** @var VolunteerOccurrence $occurrence */
    $occurrence = $request->getAttribute('volunteerOccurrence');
    $occurrenceId = (int) $occurrence->getId();

    $service = new VolunteerAssignmentService();
    $schedules = $service->getScheduleService();
    $schedule = VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());

    $summary = $service->getGaps([$occurrenceId])[$occurrenceId] ?? ['requirements' => []];
    $byPosition = $service->getAssignmentsByPosition($occurrenceId);

    $allRows = array_merge(...array_values($byPosition ?: [[]]));
    $personNames = volunteerAssignmentPersonNames(array_map(
        static fn (VolunteerAssignment $a): int => (int) $a->getPersonId(),
        $allRows
    ));
    $positionNames = volunteerAssignmentPositionNames(array_keys($byPosition));
    $attendance = $service->getAttendance($occurrence, array_map(
        static fn (VolunteerAssignment $a): int => (int) $a->getPersonId(),
        $allRows
    ));

    $requirements = [];
    $covered = [];
    foreach ($summary['requirements'] as $positionId => $requirement) {
        $positionId = (int) $positionId;
        $covered[$positionId] = true;
        $requirements[] = $requirement + [
            'assignments' => array_map(
                static fn (VolunteerAssignment $a): array => volunteerAssignmentToArray(
                    $a,
                    $personNames,
                    $attendance,
                    $positionNames[(int) $a->getPositionId()] ?? null
                ),
                $byPosition[$positionId] ?? []
            ),
        ];
    }

    // A requirement can be restructured out from under an assignment (vasg_vreq_ID is
    // ON DELETE SET NULL, §2.11). Those rows are still real commitments, so they are
    // surfaced rather than silently dropped off the screen.
    $other = [];
    foreach ($byPosition as $positionId => $rows) {
        if (isset($covered[(int) $positionId])) {
            continue;
        }
        foreach ($rows as $assignment) {
            $other[] = volunteerAssignmentToArray(
                $assignment,
                $personNames,
                $attendance,
                $positionNames[(int) $positionId] ?? null
            );
        }
    }

    return SlimUtils::renderJSON($response, [
        'occurrence' => volunteerOccurrenceToArray($occurrence, $schedules, $schedule, $summary),
        'ministryId' => $schedule === null ? null : (int) $schedule->getMinistryId(),
        'teamId' => $schedule !== null && $schedule->getTeamId() !== null ? (int) $schedule->getTeamId() : null,
        'requirements' => $requirements,
        'otherAssignments' => $other,
        // Attendance is only meaningful for a linked occurrence (E10); saying so
        // explicitly keeps a client from rendering an empty column for a standalone one.
        'attendanceAvailable' => $occurrence->getEventId() !== null,
    ]);
}

/**
 * @OA\Get(
 *     path="/ministries/occurrences/{occurrenceId}/eligible",
 *     operationId="listVolunteerEligiblePeople",
 *     summary="Who may be assigned to a position on this occurrence",
 *     description="Actively qualified people, ordered by last served date ascending with never-served first - the rotation (design section 2.17). inPool reports pool membership rather than filtering on it, because an out-of-pool qualified person is assignable with the allowOutsidePool override. conflictPositionId names another position the person already holds on this occurrence (I7/D16) and is an annotation, never a filter.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="occurrenceId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="positionId", in="query", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string"), description="Case-insensitive name filter"),
 *     @OA\Response(response=400, description="positionId is missing or names a position outside this occurrence's ministry"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this occurrence, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such occurrence"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function listVolunteerEligiblePeople(Request $request, Response $response): Response
{
    /** @var VolunteerOccurrence $occurrence */
    $occurrence = $request->getAttribute('volunteerOccurrence');
    $params = $request->getQueryParams();

    if (!isset($params['positionId']) || !is_numeric($params['positionId'])) {
        return SlimUtils::renderErrorJSON($response, gettext('A position is required'), [], 400, null, $request);
    }

    $position = VolunteerPositionQuery::create()->findPk((int) $params['positionId']);
    if ($position === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Position not found'), [], 400, null, $request);
    }

    $query = isset($params['q']) && $params['q'] !== '' ? (string) $params['q'] : null;

    try {
        $people = (new VolunteerAssignmentService())->getEligiblePeople($occurrence, $position, $query);
    } catch (\Throwable $e) {
        return volunteerAssignmentError($request, $response, $e);
    }

    return SlimUtils::renderJSON($response, ['people' => $people]);
}

/**
 * @OA\Post(
 *     path="/ministries/occurrences/{occurrenceId}/assignments",
 *     operationId="createVolunteerAssignment",
 *     summary="Assign a qualified person to a position on this occurrence",
 *     description="Enforces design section 2.11.2: I4 the position belongs to this schedule's ministry (400), I5 the occurrence is neither cancelled nor past (409), I2 an active qualification (403), I3 membership of the ministry's volunteer pool Group unless allowOutsidePool is passed (409) - NOT applied to self sign-up, which tests qualification only (design D19), I1 one row per person per position per occurrence (409). I8 re-uses a declined or cancelled row rather than inserting a second one. I7/D16 - a person already holding ANOTHER position on this occurrence - is allowed unconditionally and returns 201.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="occurrenceId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"positionId","personId"},
 *         @OA\Property(property="positionId", type="integer"),
 *         @OA\Property(property="personId", type="integer"),
 *         @OA\Property(property="requirementId", type="integer", nullable=true),
 *         @OA\Property(property="allowOutsidePool", type="boolean"),
 *         @OA\Property(property="notes", type="string", nullable=true)
 *     )),
 *     @OA\Response(response=400, description="The position does not belong to this occurrence's schedule"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not qualified, or not authorized, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such occurrence, position or person"),
 *     @OA\Response(response=409, description="Already assigned, outside the pool without the override, or the occurrence is cancelled or past"),
 *     @OA\Response(response=201, description="Assigned")
 * )
 */
function createVolunteerAssignment(Request $request, Response $response): Response
{
    /** @var VolunteerOccurrence $occurrence */
    $occurrence = $request->getAttribute('volunteerOccurrence');
    $input = (array) $request->getParsedBody();

    $position = VolunteerPositionQuery::create()->findPk((int) ($input['positionId'] ?? 0));
    if ($position === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Position not found'), [], 404, null, $request);
    }

    $requirementId = isset($input['requirementId']) && $input['requirementId'] !== '' && $input['requirementId'] !== null
        ? (int) $input['requirementId']
        : null;

    try {
        $assignment = (new VolunteerAssignmentService())->assign(
            $occurrence,
            $position,
            (int) ($input['personId'] ?? 0),
            volunteerAssignmentActor(),
            [
                'requirementId' => $requirementId,
                'allowOutsidePool' => filter_var($input['allowOutsidePool'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'notes' => $input['notes'] ?? null,
            ]
        );
    } catch (\Throwable $e) {
        return volunteerAssignmentError($request, $response, $e);
    }

    $names = volunteerAssignmentPersonNames([(int) $assignment->getPersonId()]);

    return SlimUtils::renderJSON(
        $response,
        ['assignment' => volunteerAssignmentToArray($assignment, $names, [], (string) $position->getName())],
        201
    );
}

/**
 * @OA\Get(
 *     path="/ministries/assignments/{assignmentId}",
 *     operationId="getVolunteerAssignment",
 *     summary="One assignment with its full response history",
 *     description="The history is returned because it is the only way to prove the idempotency rule of design section 2.12 from outside - accepting twice must leave exactly one response row (section 6.6). Swap rows are included for the same reason.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="assignmentId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this assignment, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such assignment"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function getVolunteerAssignment(Request $request, Response $response): Response
{
    /** @var VolunteerAssignment $assignment */
    $assignment = $request->getAttribute('volunteerAssignment');
    $service = new VolunteerAssignmentService();

    $responses = $service->getResponses((int) $assignment->getId());
    $swaps = $service->getSwapsForAssignment((int) $assignment->getId());

    $personIds = [(int) $assignment->getPersonId()];
    foreach ($responses as $row) {
        $personIds[] = (int) $row->getPersonId();
    }
    foreach ($swaps as $swap) {
        $personIds[] = (int) $swap->getProposedByPersonId();
        $personIds[] = (int) $swap->getProposedPersonId();
    }
    $names = volunteerAssignmentPersonNames($personIds);
    $positionNames = volunteerAssignmentPositionNames([(int) $assignment->getPositionId()]);
    $positionName = $positionNames[(int) $assignment->getPositionId()] ?? null;

    $occurrence = VolunteerOccurrenceQuery::create()->findPk((int) $assignment->getOccurrenceId());
    $attendance = $occurrence === null
        ? []
        : $service->getAttendance($occurrence, [(int) $assignment->getPersonId()]);

    return SlimUtils::renderJSON($response, [
        'assignment' => volunteerAssignmentToArray($assignment, $names, $attendance, $positionName),
        'responses' => array_map(
            static fn (VolunteerResponse $row): array => volunteerResponseToArray($row, $names),
            $responses
        ),
        'swaps' => array_map(
            static fn (VolunteerSwap $swap): array => volunteerSwapToArray($swap, $names, $assignment, $positionName),
            $swaps
        ),
        // The same shape GET /notifications returns, from the same builder — one
        // outbox row must not look different depending on which read found it.
        'notifications' => array_map(
            'volunteerNotificationToArray',
            $service->getNotificationService()->listForAssignment((int) $assignment->getId())
        ),
    ]);
}

/**
 * @OA\Post(
 *     path="/ministries/assignments/{assignmentId}/status",
 *     operationId="setVolunteerAssignmentStatus",
 *     summary="Change an assignment's status, or record a response on the volunteer's behalf",
 *     description="accepted and declined write a response row with channel 'coordinator' and the COORDINATOR as vrsp_per_ID, so the audit trail shows who actually recorded it (design section 2.11.1). A coordinator-recorded decline deliberately enqueues no decline_alert - the coordinators already know (section 3.6). cancelled is a coordinator act. An illegal transition is a 409 whose body carries currentStatus.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="assignmentId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"status"},
 *         @OA\Property(property="status", type="string", enum={"accepted","declined","cancelled"}),
 *         @OA\Property(property="comment", type="string", nullable=true)
 *     )),
 *     @OA\Response(response=400, description="Not a status a coordinator may set directly"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this assignment, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such assignment"),
 *     @OA\Response(response=409, description="Illegal transition; the body carries currentStatus"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function setVolunteerAssignmentStatus(Request $request, Response $response): Response
{
    /** @var VolunteerAssignment $assignment */
    $assignment = $request->getAttribute('volunteerAssignment');
    $input = (array) $request->getParsedBody();

    try {
        $assignment = (new VolunteerAssignmentService())->setStatus(
            $assignment,
            (string) $input['status'],
            volunteerAssignmentActor(),
            $input['comment'] ?? null
        );
    } catch (\Throwable $e) {
        return volunteerAssignmentError($request, $response, $e);
    }

    $names = volunteerAssignmentPersonNames([(int) $assignment->getPersonId()]);
    $positionNames = volunteerAssignmentPositionNames([(int) $assignment->getPositionId()]);

    return SlimUtils::renderJSON($response, [
        'assignment' => volunteerAssignmentToArray(
            $assignment,
            $names,
            [],
            $positionNames[(int) $assignment->getPositionId()] ?? null
        ),
    ]);
}

/**
 * @OA\Delete(
 *     path="/ministries/assignments/{assignmentId}",
 *     operationId="deleteVolunteerAssignment",
 *     summary="Cancel an assignment (hard-deletes only a pending row with no history)",
 *     description="Design section 3.3.2: cancel, never hard-delete once responded. A pending row that carries no response, no swap and is not itself a substitution is a mis-click with nothing to preserve and is removed outright along with its pending outbox rows; everything else is cancelled and kept, because the audit trail is what issue 9709 promises.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="assignmentId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this assignment, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such assignment"),
 *     @OA\Response(response=409, description="The occurrence has already happened"),
 *     @OA\Response(response=200, description="Cancelled or deleted")
 * )
 */
function deleteVolunteerAssignment(Request $request, Response $response): Response
{
    /** @var VolunteerAssignment $assignment */
    $assignment = $request->getAttribute('volunteerAssignment');

    try {
        $result = (new VolunteerAssignmentService())->cancelOrDelete($assignment, volunteerAssignmentActor());
    } catch (\Throwable $e) {
        return volunteerAssignmentError($request, $response, $e);
    }

    if ($result['deleted']) {
        return SlimUtils::renderJSON($response, ['deleted' => true, 'assignment' => null]);
    }

    $row = $result['assignment'];
    $names = volunteerAssignmentPersonNames([(int) $row->getPersonId()]);
    $positionNames = volunteerAssignmentPositionNames([(int) $row->getPositionId()]);

    return SlimUtils::renderJSON($response, [
        'deleted' => false,
        'assignment' => volunteerAssignmentToArray(
            $row,
            $names,
            [],
            $positionNames[(int) $row->getPositionId()] ?? null
        ),
    ]);
}

/**
 * @OA\Post(
 *     path="/ministries/assignments/{assignmentId}/notify",
 *     operationId="notifyVolunteerAssignment",
 *     summary="Re-enqueue the assignment message for this volunteer",
 *     description="Idempotent through the dedupe key (design section 2.14): the row assign() already created is returned with created=false and nothing is duplicated. ?force=1 re-arms that SAME row to pending so the next drain sends it again - it never inserts a second one. Nothing is sent here; delivery is issue 9710's.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="assignmentId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="force", in="query", required=false, @OA\Schema(type="boolean")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this assignment, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such assignment"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function notifyVolunteerAssignment(Request $request, Response $response): Response
{
    /** @var VolunteerAssignment $assignment */
    $assignment = $request->getAttribute('volunteerAssignment');
    $params = $request->getQueryParams();
    $force = isset($params['force']) && filter_var($params['force'], FILTER_VALIDATE_BOOLEAN);

    $notifications = new VolunteerNotificationService();
    $key = $notifications->assignmentKey((int) $assignment->getId(), (int) $assignment->getPersonId());
    $existing = $notifications->findByDedupeKey($key);

    $notification = $notifications->enqueue(
        VolunteerNotificationService::TYPE_ASSIGNMENT,
        (int) $assignment->getPersonId(),
        (int) $assignment->getId(),
        (int) $assignment->getOccurrenceId(),
        DateTimeUtils::getToday(),
        $key
    );

    if ($force) {
        $notification = $notifications->rearm($notification);
    }

    return SlimUtils::renderJSON($response, [
        'created' => $existing === null,
        'notification' => volunteerNotificationToArray($notification),
    ]);
}

/**
 * One outbox row on the wire (design §2.14).
 *
 * Every column except the dedupe key's internals is exposed, because the whole
 * point of the read is to answer "what has this volunteer actually been told,
 * and what went wrong" without a database client — including `attempts` and
 * `lastError`, which are how a coordinator tells "we gave up" from "not yet".
 */
function volunteerNotificationToArray(VolunteerNotification $notification): array
{
    return [
        'id' => (int) $notification->getId(),
        'type' => $notification->getType(),
        'channel' => $notification->getChannel(),
        'personId' => (int) $notification->getPersonId(),
        'assignmentId' => $notification->getAssignmentId() === null ? null : (int) $notification->getAssignmentId(),
        'occurrenceId' => $notification->getOccurrenceId() === null ? null : (int) $notification->getOccurrenceId(),
        'dedupeKey' => $notification->getDedupeKey(),
        'status' => $notification->getStatus(),
        'scheduledFor' => $notification->getScheduledFor('Y-m-d H:i:s'),
        'sentDate' => $notification->getSentDate('Y-m-d H:i:s'),
        'attempts' => (int) $notification->getAttempts(),
        'lastAttemptDate' => $notification->getLastAttemptDate('Y-m-d H:i:s'),
        'lastError' => $notification->getLastError(),
    ];
}

/**
 * @OA\Get(
 *     path="/ministries/assignments/{assignmentId}/notifications",
 *     operationId="listVolunteerAssignmentNotifications",
 *     summary="The notification outbox rows for one assignment",
 *     description="Design section 6.6: the only way to prove from outside that a retried operation produced no second message. Newest first. Coordinator surface - the volunteer's own view of what they were sent is not this endpoint.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="assignmentId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this assignment, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such assignment"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function listVolunteerAssignmentNotifications(Request $request, Response $response): Response
{
    /** @var VolunteerAssignment $assignment */
    $assignment = $request->getAttribute('volunteerAssignment');

    $rows = (new VolunteerNotificationService())->listForAssignment((int) $assignment->getId());

    return SlimUtils::renderJSON($response, [
        'notifications' => array_map('volunteerNotificationToArray', $rows),
    ]);
}

/**
 * @OA\Get(
 *     path="/ministries/gaps",
 *     operationId="listVolunteerGaps",
 *     summary="Unfilled staffing requirements across the caller's scope",
 *     description="from and to are required, matching GET /ministries/occurrences. Every count comes from VolunteerAssignmentService::getGaps() - there is no gap table (design section 2.11.3).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="from", in="query", required=true, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", required=true, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="ministryId", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Response(response=400, description="from or to missing or malformed"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not a coordinator, or V2 is not enabled"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function listVolunteerGaps(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();

    $from = volunteerParseDateParam($params['from'] ?? null);
    $to = volunteerParseDateParam($params['to'] ?? null);

    if ($from === null || $to === null) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('A from and a to date in YYYY-MM-DD form are required'),
            [],
            400,
            null,
            $request
        );
    }
    if ($to < $from) {
        return SlimUtils::renderErrorJSON($response, gettext('The window ends before it starts'), [], 400, null, $request);
    }

    $currentUser = volunteerAssignmentActor();
    $authz = new VolunteerAuthorizationService();
    $ministryId = isset($params['ministryId']) && $params['ministryId'] !== ''
        ? (int) $params['ministryId']
        : null;

    $occurrenceIds = volunteerScopedOccurrenceIds($currentUser, $authz, $from, $to, $ministryId);
    if ($occurrenceIds === []) {
        return SlimUtils::renderJSON($response, ['gaps' => []]);
    }

    $service = new VolunteerAssignmentService();
    $schedules = $service->getScheduleService();
    $gaps = $service->getOpenGaps($occurrenceIds);

    // One occurrence lookup for the whole list; the effective time comes from the one
    // method allowed to decide it (§3.4).
    $occurrences = [];
    foreach (VolunteerOccurrenceQuery::create()->filterById($occurrenceIds, Criteria::IN)->find() as $occurrence) {
        $occurrences[(int) $occurrence->getId()] = $occurrence;
    }

    $payload = [];
    foreach ($gaps as $gap) {
        $occurrence = $occurrences[$gap['occurrenceId']] ?? null;
        $window = $occurrence === null ? ['start' => null, 'end' => null] : $schedules->resolveOccurrenceWindow($occurrence);
        $schedule = $occurrence === null
            ? null
            : VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());

        $payload[] = $gap + [
            'occurrenceDate' => $occurrence === null ? null : $occurrence->getOccurrenceDate('Y-m-d'),
            'start' => $window['start'] === null ? null : $window['start']->format('Y-m-d H:i:s'),
            'end' => $window['end'] === null ? null : $window['end']->format('Y-m-d H:i:s'),
            'scheduleId' => $schedule === null ? null : (int) $schedule->getId(),
            'scheduleName' => $schedule === null ? null : $schedule->getName(),
            'ministryId' => $schedule === null ? null : (int) $schedule->getMinistryId(),
        ];
    }

    // Soonest first: a gap this Sunday matters more than one in six weeks.
    usort($payload, static fn (array $a, array $b): int => ($a['occurrenceDate'] ?? '') <=> ($b['occurrenceDate'] ?? ''));

    return SlimUtils::renderJSON($response, ['gaps' => $payload]);
}

/**
 * @OA\Get(
 *     path="/ministries/swaps",
 *     operationId="listVolunteerSwaps",
 *     summary="The substitution queue for the caller's scope",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"proposed","approved","rejected","withdrawn"})),
 *     @OA\Parameter(name="ministryId", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="occurrenceId", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not a coordinator, or V2 is not enabled"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function listVolunteerSwaps(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();
    $currentUser = volunteerAssignmentActor();
    $authz = new VolunteerAuthorizationService();

    $ministryId = isset($params['ministryId']) && $params['ministryId'] !== ''
        ? (int) $params['ministryId']
        : null;

    $occurrenceIds = volunteerScopedOccurrenceIds($currentUser, $authz, null, null, $ministryId);

    if (isset($params['occurrenceId']) && $params['occurrenceId'] !== '') {
        $wanted = (int) $params['occurrenceId'];
        // Intersect rather than replace: a caller must not widen their own scope by
        // naming an occurrence they cannot see.
        $occurrenceIds = in_array($wanted, $occurrenceIds, true) ? [$wanted] : [];
    }

    if ($occurrenceIds === []) {
        return SlimUtils::renderJSON($response, ['swaps' => []]);
    }

    $service = new VolunteerAssignmentService();
    $swaps = $service->listSwaps($occurrenceIds, $params['status'] ?? null);
    if ($swaps === []) {
        return SlimUtils::renderJSON($response, ['swaps' => []]);
    }

    $assignments = [];
    foreach (
        VolunteerAssignmentQuery::create()
            ->filterById(array_map(static fn (VolunteerSwap $s): int => (int) $s->getAssignmentId(), $swaps), Criteria::IN)
            ->find() as $assignment
    ) {
        $assignments[(int) $assignment->getId()] = $assignment;
    }

    $personIds = [];
    foreach ($swaps as $swap) {
        $personIds[] = (int) $swap->getProposedByPersonId();
        $personIds[] = (int) $swap->getProposedPersonId();
    }
    $names = volunteerAssignmentPersonNames($personIds);
    $positionNames = volunteerAssignmentPositionNames(array_map(
        static fn (VolunteerAssignment $a): int => (int) $a->getPositionId(),
        $assignments
    ));

    $payload = [];
    foreach ($swaps as $swap) {
        $assignment = $assignments[(int) $swap->getAssignmentId()] ?? null;
        $payload[] = volunteerSwapToArray(
            $swap,
            $names,
            $assignment,
            $assignment === null ? null : ($positionNames[(int) $assignment->getPositionId()] ?? null)
        );
    }

    return SlimUtils::renderJSON($response, ['swaps' => $payload]);
}

/**
 * @OA\Post(
 *     path="/ministries/swaps/{swapId}/approve",
 *     operationId="approveVolunteerSwap",
 *     summary="Approve a proposed substitution",
 *     description="ONE transaction (design section 2.13): the original goes accepted to substituted, a new row is inserted for the substitute with source='substitute', status='accepted' and vasg_Replaces_vasg_ID pointing at the original, a response row is appended to both, and both parties get a swap_resolved outbox row. The original is never edited beyond its status, so the audit trail survives.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="swapId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="comment", type="string", nullable=true))),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized, the substitute is no longer qualified, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such substitution request"),
 *     @OA\Response(response=409, description="Already decided, the occurrence is cancelled or past, or the substitute already holds the position"),
 *     @OA\Response(response=200, description="Approved")
 * )
 */
function approveVolunteerSwap(Request $request, Response $response): Response
{
    /** @var VolunteerSwap $swap */
    $swap = $request->getAttribute('volunteerSwap');
    $input = (array) $request->getParsedBody();

    try {
        $result = (new VolunteerAssignmentService())->approveSwap(
            $swap,
            volunteerAssignmentActor(),
            $input['comment'] ?? null
        );
    } catch (\Throwable $e) {
        return volunteerAssignmentError($request, $response, $e);
    }

    $original = $result['originalAssignment'];
    $replacement = $result['replacementAssignment'];
    $names = volunteerAssignmentPersonNames([
        (int) $original->getPersonId(),
        (int) $replacement->getPersonId(),
        (int) $result['swap']->getProposedByPersonId(),
        (int) $result['swap']->getProposedPersonId(),
    ]);
    $positionNames = volunteerAssignmentPositionNames([(int) $original->getPositionId()]);
    $positionName = $positionNames[(int) $original->getPositionId()] ?? null;

    return SlimUtils::renderJSON($response, [
        'swap' => volunteerSwapToArray($result['swap'], $names, $original, $positionName),
        'originalAssignment' => volunteerAssignmentToArray($original, $names, [], $positionName),
        'replacementAssignment' => volunteerAssignmentToArray($replacement, $names, [], $positionName),
    ]);
}

/**
 * @OA\Post(
 *     path="/ministries/swaps/{swapId}/reject",
 *     operationId="rejectVolunteerSwap",
 *     summary="Reject a proposed substitution",
 *     description="The original assignment stays accepted and both parties are told (design section 2.13). The coordinator may then cancel it and assign someone else, which is the ordinary gap loop.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="swapId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="comment", type="string", nullable=true))),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such substitution request"),
 *     @OA\Response(response=409, description="Already decided"),
 *     @OA\Response(response=200, description="Rejected")
 * )
 */
function rejectVolunteerSwap(Request $request, Response $response): Response
{
    /** @var VolunteerSwap $swap */
    $swap = $request->getAttribute('volunteerSwap');
    $input = (array) $request->getParsedBody();

    try {
        $swap = (new VolunteerAssignmentService())->rejectSwap(
            $swap,
            volunteerAssignmentActor(),
            $input['comment'] ?? null
        );
    } catch (\Throwable $e) {
        return volunteerAssignmentError($request, $response, $e);
    }

    $names = volunteerAssignmentPersonNames([
        (int) $swap->getProposedByPersonId(),
        (int) $swap->getProposedPersonId(),
    ]);

    return SlimUtils::renderJSON($response, ['swap' => volunteerSwapToArray($swap, $names)]);
}
