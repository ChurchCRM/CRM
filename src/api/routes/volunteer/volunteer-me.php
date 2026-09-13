<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\VolunteerAssignment;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerResponse;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSwap;
use ChurchCRM\model\ChurchCRM\VolunteerSwapQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\VolunteerAssignmentService;
use ChurchCRM\Service\VolunteerSetupService;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Volunteer Management V2 API — the member self-service surface (design §3.3.3).
 *
 * Four of the seven endpoints §3.3.3 lists shipped here with #9709, ahead of #9712:
 * every one of them is half of a workflow #9709 was accountable for ("volunteer can
 * accept or decline", "decline creates a gap", "coordinator can approve/reject proposed
 * substitutions"), and none could be proven without the surface the volunteer actually
 * answers on. **#9712 completes the surface** with the self-service *discovery* half —
 * `/me/opportunities`, `/me/signup`, `/me/qualifications` — plus one endpoint §3.3.3
 * does not list, `/me/assignments/{id}/substitutes`, which exists so §5.6's "Find a
 * sub" picker can only ever offer a name `propose-substitute` will accept.
 *
 * **This group carries NO role gate.** Only `VolunteerV2EnabledMiddleware`. That is the
 * whole design of §3.3.3 and §4.7: the acting person is
 * `AuthenticationManager::getCurrentUser()->getId()` — which IS the person id (F4) —
 * and **no endpoint here accepts a `personId` parameter identifying the actor**. #9712's
 * "unauthorized person IDs cannot be substituted into requests" is therefore satisfied
 * structurally rather than by a check that can be forgotten. (`propose-substitute` does
 * take a `personId`, but it names the *substitute*, never the actor, and the server
 * re-derives every eligibility rule for them.)
 *
 * A volunteer is an EditSelf-exclusive user (D14), so `AuthMiddleware` would normally
 * turn them away entirely; `isLimitedAccessAllowedPath()` (#9706, §4.7) exempts exactly
 * `/api/volunteer/me/` behind the rollout flag. The exemption grants **reachability, not
 * authority**: every handler below still authorizes per record, and answers **403, never
 * 404**, for someone else's assignment — the record exists, and leaking its existence is
 * acceptable where leaking its content is not (§4.8).
 *
 * Authorization itself is not decided here. `VolunteerAssignmentService::respond()`,
 * `proposeSubstitute()` and `withdrawSwap()` each check the acting person against the
 * row, because they are also reachable from the coordinator surface.
 */
/**
 * How far ahead `GET /me/opportunities` looks when the caller names no window.
 *
 * S6 is opened with no parameters at all by a volunteer on a phone, so "what is
 * coming up" needs a definition. A quarter is long enough to cover a schedule
 * generated a season ahead and short enough that the answer stays a list rather
 * than a catalogue.
 */
const VOLUNTEER_ME_DEFAULT_WINDOW_DAYS = 90;

$app->group('/volunteer/me', function (RouteCollectorProxy $group): void {
    $group->get('/assignments', 'listMyVolunteerAssignments');

    // #9712. Declared before the parameterised assignment routes so neither literal
    // path can be swallowed by `{assignmentId}`.
    $group->get('/opportunities', 'listMyVolunteerOpportunities');
    $group->get('/qualifications', 'listMyVolunteerQualifications');
    $group->post('/signup', 'signUpForMyVolunteerOpportunity')
        ->add(new InputSanitizationMiddleware([
            'occurrenceId' => 'int',
            'positionId' => 'int',
            // Deliberately NO 'personId': the actor is the session (§3.3.3). One
            // arriving in the body is simply never read.
        ]));

    $group->get('/assignments/{assignmentId:[0-9]+}/substitutes', 'listMyVolunteerSubstituteCandidates');

    $group->post('/assignments/{assignmentId:[0-9]+}/respond', 'respondToMyVolunteerAssignment')
        ->add(new InputSanitizationMiddleware([
            'response' => 'enum:' . implode(',', [
                VolunteerAssignment::STATUS_ACCEPTED,
                VolunteerAssignment::STATUS_DECLINED,
            ]),
            'comment' => 'text',
        ]));

    $group->post('/assignments/{assignmentId:[0-9]+}/propose-substitute', 'proposeMyVolunteerSubstitute')
        ->add(new InputSanitizationMiddleware([
            // The SUBSTITUTE's id, never the actor's — the actor comes from the session.
            'personId' => 'int',
            'comment' => 'text',
        ]));

    $group->post('/swaps/{swapId:[0-9]+}/withdraw', 'withdrawMyVolunteerSwap')
        ->add(new InputSanitizationMiddleware(['comment' => 'text']));
})->add(new VolunteerV2EnabledMiddleware());

// ─── Handlers ────────────────────────────────────────────────────────────────

/**
 * Load an assignment for the member surface.
 *
 * Returns 404 when it genuinely does not exist and **403 when it belongs to someone
 * else** (§4.8) — deliberately not the entity middleware, which answers the coordinator
 * question (`canManageAssignment`) and would 403 a volunteer on their own row.
 */
function volunteerMeFindAssignment(Request $request, int $personId): array
{
    $assignmentId = (int) SlimUtils::getRouteArgument($request, 'assignmentId');
    $assignment = VolunteerAssignmentQuery::create()->findPk($assignmentId);

    if ($assignment === null) {
        return [null, 404, gettext('Assignment not found')];
    }

    if ((int) $assignment->getPersonId() !== $personId) {
        return [null, 403, gettext('That assignment belongs to someone else')];
    }

    return [$assignment, 200, ''];
}

/**
 * One assignment as the volunteer sees it.
 *
 * Deliberately plain (§5.6): a volunteer never sees the words *requirement*,
 * *occurrence* or *schedule*. They see a date, a time, who they are serving with and
 * what they are doing — plus whether they may still answer, so the card can render the
 * right buttons without guessing.
 */
function volunteerMeAssignmentToArray(
    VolunteerAssignment $assignment,
    VolunteerAssignmentService $service,
    array $context
): array {
    $occurrenceId = (int) $assignment->getOccurrenceId();
    $ctx = $context[$occurrenceId] ?? [];

    $pendingSwap = VolunteerSwapQuery::create()
        ->filterByAssignmentId((int) $assignment->getId())
        ->filterByStatus(VolunteerSwap::STATUS_PROPOSED)
        ->findOne();

    return [
        'id' => (int) $assignment->getId(),
        'occurrenceId' => $occurrenceId,
        'personId' => (int) $assignment->getPersonId(),
        'positionId' => (int) $assignment->getPositionId(),
        'positionName' => $ctx['positionNames'][(int) $assignment->getPositionId()] ?? null,
        'ministryName' => $ctx['ministryName'] ?? null,
        'teamName' => $ctx['teamName'] ?? null,
        'start' => $ctx['start'] ?? null,
        'end' => $ctx['end'] ?? null,
        'occurrenceDate' => $ctx['occurrenceDate'] ?? null,
        'occurrenceStatus' => $ctx['occurrenceStatus'] ?? null,
        'status' => $assignment->getStatus(),
        'source' => $assignment->getSource(),
        'respondedDate' => $assignment->getRespondedDate('Y-m-d H:i:s'),
        'canRespond' => $service->canRespond($assignment),
        'canProposeSubstitute' => $service->canProposeSubstitute($assignment),
        // #9712 / §5.6: a card showing "Substitute proposed — waiting for your
        // coordinator" needs the swap id to offer Withdraw, and the name to say who
        // was asked. Null on every assignment with nothing pending, which is most.
        'pendingSwapId' => $pendingSwap === null ? null : (int) $pendingSwap->getId(),
        'pendingSwapPersonName' => $pendingSwap === null
            ? null
            : (volunteerAssignmentPersonNames([(int) $pendingSwap->getProposedPersonId()])[(int) $pendingSwap->getProposedPersonId()] ?? null),
    ];
}

/**
 * @OA\Get(
 *     path="/volunteer/me/assignments",
 *     operationId="listMyVolunteerAssignments",
 *     summary="My own volunteer commitments",
 *     description="The acting person comes from the session (AuthenticationManager::getCurrentUser()->getId(), which IS the person id). There is deliberately NO personId parameter - a personId in the query string is ignored, which is what makes substitution structurally impossible (design section 3.3.3). Upcoming only by default; includePast=1 adds what has already happened.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="from", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="includePast", in="query", required=false, @OA\Schema(type="boolean")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="V2 is not enabled"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function listMyVolunteerAssignments(Request $request, Response $response): Response
{
    $personId = (int) AuthenticationManager::getCurrentUser()->getId();
    $params = $request->getQueryParams();

    $from = volunteerMeParseDate($params['from'] ?? null);
    $to = volunteerMeParseDate($params['to'] ?? null);
    $includePast = isset($params['includePast']) && filter_var($params['includePast'], FILTER_VALIDATE_BOOLEAN);

    $service = new VolunteerAssignmentService();
    $assignments = $service->listAssignmentsForPerson($personId, $from, $to, $includePast);

    if ($assignments === []) {
        return SlimUtils::renderJSON($response, ['assignments' => []]);
    }

    $context = volunteerMeOccurrenceContext($assignments, $service);

    $payload = array_map(
        static fn (VolunteerAssignment $a): array => volunteerMeAssignmentToArray($a, $service, $context),
        $assignments
    );

    // Soonest first — S5 is "what do I have coming up", not a ledger.
    usort($payload, static fn (array $a, array $b): int => ($a['start'] ?? '') <=> ($b['start'] ?? ''));

    return SlimUtils::renderJSON($response, ['assignments' => $payload]);
}

/**
 * @OA\Post(
 *     path="/volunteer/me/assignments/{assignmentId}/respond",
 *     operationId="respondToMyVolunteerAssignment",
 *     summary="Accept or decline my own assignment",
 *     description="Idempotent (design section 2.12): responding with the status the assignment already has returns 200 with no second response row, so a double tap on a phone is harmless. A decline reopens the gap and enqueues a decline_alert for the coordinators (section 3.6) - which a coordinator-recorded decline on the other surface deliberately does not. Answering for someone else is 403, never 404.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="assignmentId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"response"},
 *         @OA\Property(property="response", type="string", enum={"accepted","declined"}),
 *         @OA\Property(property="comment", type="string", nullable=true)
 *     )),
 *     @OA\Response(response=400, description="Not a valid response"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not my assignment, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such assignment"),
 *     @OA\Response(response=409, description="This assignment can no longer be answered"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function respondToMyVolunteerAssignment(Request $request, Response $response): Response
{
    $currentUser = AuthenticationManager::getCurrentUser();
    [$assignment, $status, $message] = volunteerMeFindAssignment($request, (int) $currentUser->getId());

    if ($assignment === null) {
        return SlimUtils::renderErrorJSON($response, $message, [], $status, null, $request);
    }

    $input = (array) $request->getParsedBody();
    $service = new VolunteerAssignmentService();

    try {
        $assignment = $service->respond(
            $assignment,
            (string) $input['response'],
            $currentUser,
            $input['comment'] ?? null,
            VolunteerResponse::CHANNEL_WEB
        );
    } catch (\Throwable $e) {
        return volunteerAssignmentError($request, $response, $e);
    }

    $context = volunteerMeOccurrenceContext([$assignment], $service);

    return SlimUtils::renderJSON($response, [
        'assignment' => volunteerMeAssignmentToArray($assignment, $service, $context),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/me/assignments/{assignmentId}/propose-substitute",
 *     operationId="proposeMyVolunteerSubstitute",
 *     summary="Propose a substitute who has already agreed",
 *     description="The core of D13/UC2. personId here is the SUBSTITUTE, never the actor - the actor is the session. Eligibility is re-checked server-side whatever the UI offered: the substitute must hold an active qualification for the position (I2 applies to the replacement too) and must not already hold that position on that occurrence (I1). At most one proposal may be pending per assignment; a second is 409. The original assignment is not changed - only a substitute_proposed response row is appended.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="assignmentId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"personId"},
 *         @OA\Property(property="personId", type="integer", description="The substitute"),
 *         @OA\Property(property="comment", type="string", nullable=true)
 *     )),
 *     @OA\Response(response=400, description="Proposing yourself"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not my assignment, the substitute is not qualified or already holds the position, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such assignment or person"),
 *     @OA\Response(response=409, description="A substitute is already proposed, or the assignment is no longer active"),
 *     @OA\Response(response=201, description="Proposed")
 * )
 */
function proposeMyVolunteerSubstitute(Request $request, Response $response): Response
{
    $currentUser = AuthenticationManager::getCurrentUser();
    [$assignment, $status, $message] = volunteerMeFindAssignment($request, (int) $currentUser->getId());

    if ($assignment === null) {
        return SlimUtils::renderErrorJSON($response, $message, [], $status, null, $request);
    }

    $input = (array) $request->getParsedBody();

    try {
        $swap = (new VolunteerAssignmentService())->proposeSubstitute(
            $assignment,
            (int) ($input['personId'] ?? 0),
            $currentUser,
            $input['comment'] ?? null
        );
    } catch (\Throwable $e) {
        return volunteerAssignmentError($request, $response, $e);
    }

    $names = volunteerAssignmentPersonNames([
        (int) $swap->getProposedByPersonId(),
        (int) $swap->getProposedPersonId(),
    ]);
    $positionNames = volunteerAssignmentPositionNames([(int) $assignment->getPositionId()]);

    return SlimUtils::renderJSON(
        $response,
        [
            'swap' => volunteerSwapToArray(
                $swap,
                $names,
                $assignment,
                $positionNames[(int) $assignment->getPositionId()] ?? null
            ),
        ],
        201
    );
}

/**
 * @OA\Post(
 *     path="/volunteer/me/swaps/{swapId}/withdraw",
 *     operationId="withdrawMyVolunteerSwap",
 *     summary="Withdraw my own pending substitution request",
 *     description="Proposer only (design section 2.13) - a coordinator approves or rejects, never withdraws on someone's behalf, so even a coordinator gets 403 here. Appends a substitute_withdrawn response row and changes nothing else: the original assignment stays exactly as it was. 409 unless the request is still proposed.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="swapId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=false, @OA\JsonContent(@OA\Property(property="comment", type="string", nullable=true))),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not the proposer, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such substitution request"),
 *     @OA\Response(response=409, description="Already decided"),
 *     @OA\Response(response=200, description="Withdrawn")
 * )
 */
function withdrawMyVolunteerSwap(Request $request, Response $response): Response
{
    $currentUser = AuthenticationManager::getCurrentUser();
    $swapId = (int) SlimUtils::getRouteArgument($request, 'swapId');
    $swap = VolunteerSwapQuery::create()->findPk($swapId);

    if ($swap === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Substitution request not found'), [], 404, null, $request);
    }

    $input = (array) $request->getParsedBody();

    try {
        // The proposer-only rule lives in the service, not here, so the coordinator
        // surface cannot grow a second, looser copy of it.
        $swap = (new VolunteerAssignmentService())->withdrawSwap(
            $swap,
            $currentUser,
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

/**
 * @OA\Get(
 *     path="/volunteer/me/opportunities",
 *     operationId="listMyVolunteerOpportunities",
 *     summary="Open slots I am qualified for and could sign up to right now",
 *     description="Server-side eligibility, never the client's idea of it: the list is filtered to positions the SESSION person holds an active qualification for, on occurrences that are scheduled, not over and in a pool the person belongs to, with capacity left. Every row it returns is a row POST /me/signup would accept. There is no personId parameter (design section 3.3.3); one in the query string is ignored. Defaults to the next 90 days.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="from", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=400, description="The window ends before it starts"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="V2 is not enabled"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function listMyVolunteerOpportunities(Request $request, Response $response): Response
{
    $personId = (int) AuthenticationManager::getCurrentUser()->getId();
    $params = $request->getQueryParams();

    // A volunteer opens S6 with no dates at all, so the window has a default:
    // "what is coming up", not "everything ever generated".
    $from = volunteerMeParseDate($params['from'] ?? null) ?? DateTimeUtils::getToday();
    $to = volunteerMeParseDate($params['to'] ?? null)
        ?? DateTimeUtils::getToday()->modify('+' . VOLUNTEER_ME_DEFAULT_WINDOW_DAYS . ' days');

    if ($to < $from) {
        return SlimUtils::renderErrorJSON($response, gettext('The window ends before it starts'), [], 400, null, $request);
    }

    $opportunities = (new VolunteerAssignmentService())->listOpportunitiesForPerson($personId, $from, $to);

    return SlimUtils::renderJSON($response, [
        'opportunities' => $opportunities,
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/me/signup",
 *     operationId="signUpForMyVolunteerOpportunity",
 *     summary="Put myself on an open slot",
 *     description="Creates an assignment for the SESSION person with status accepted and source self_signup - a volunteer who volunteered has already answered. Qualification AND capacity are re-validated server-side at signup time whatever the list offered: 403 when unqualified (I2), 409 when the requirement is already at MaxCount, when the occurrence is cancelled or past (I5), or when the person already holds that position (I1). A personId in the body names nobody: the actor is the session.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"occurrenceId","positionId"},
 *         @OA\Property(property="occurrenceId", type="integer"),
 *         @OA\Property(property="positionId", type="integer")
 *     )),
 *     @OA\Response(response=400, description="Missing or mismatched ids"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not qualified, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such occurrence or position"),
 *     @OA\Response(response=409, description="Already full, already mine, cancelled or past"),
 *     @OA\Response(response=201, description="Signed up")
 * )
 */
function signUpForMyVolunteerOpportunity(Request $request, Response $response): Response
{
    $currentUser = AuthenticationManager::getCurrentUser();
    $input = (array) $request->getParsedBody();

    $occurrence = VolunteerOccurrenceQuery::create()->findPk((int) ($input['occurrenceId'] ?? 0));
    if ($occurrence === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Occurrence not found'), [], 404, null, $request);
    }

    $position = VolunteerPositionQuery::create()->findPk((int) ($input['positionId'] ?? 0));
    if ($position === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Position not found'), [], 404, null, $request);
    }

    $service = new VolunteerAssignmentService();

    try {
        // Every rule lives in the service (§3.4) so the member surface cannot grow a
        // second, looser copy of the capacity or qualification check. `signup_confirm`
        // is enqueued inside its transaction.
        $assignment = $service->selfSignup($occurrence, $position, $currentUser);
    } catch (\Throwable $e) {
        return volunteerAssignmentError($request, $response, $e);
    }

    $context = volunteerMeOccurrenceContext([$assignment], $service);

    return SlimUtils::renderJSON($response, [
        'assignment' => volunteerMeAssignmentToArray($assignment, $service, $context),
    ], 201);
}

/**
 * @OA\Get(
 *     path="/volunteer/me/qualifications",
 *     operationId="listMyVolunteerQualifications",
 *     summary="What I am qualified to do",
 *     description="Read-only, and read-only on purpose: volunteers cannot grant themselves anything, so there is no write counterpart on this surface. Scoped to the SESSION person - a personId in the query string is ignored (design section 3.3.3).",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="V2 is not enabled"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function listMyVolunteerQualifications(Request $request, Response $response): Response
{
    $personId = (int) AuthenticationManager::getCurrentUser()->getId();

    $setup = new VolunteerSetupService();
    $qualifications = $setup->listQualificationsForPerson($personId);

    $positionIds = [];
    foreach ($qualifications as $qualification) {
        if ($qualification->getActive()) {
            $positionIds[(int) $qualification->getPositionId()] = true;
        }
    }

    if ($positionIds === []) {
        return SlimUtils::renderJSON($response, ['qualifications' => []]);
    }

    $positions = [];
    foreach (
        VolunteerPositionQuery::create()
            ->filterById(array_keys($positionIds), Criteria::IN)
            ->filterByActive(true)
            ->find() as $position
    ) {
        $positions[(int) $position->getId()] = $position;
    }

    $ministryNames = [];
    foreach (
        VolunteerMinistryQuery::create()
            ->filterById(array_map(static fn ($p): int => (int) $p->getMinistryId(), $positions), Criteria::IN)
            ->find() as $ministry
    ) {
        $ministryNames[(int) $ministry->getId()] = (string) $ministry->getName();
    }

    // D18: every position names a team, so there is nothing to filter out here.
    $teamIds = array_values(array_map(static fn ($p): int => (int) $p->getTeamId(), $positions));
    $teamNames = [];
    if ($teamIds !== []) {
        foreach (VolunteerTeamQuery::create()->filterById($teamIds, Criteria::IN)->find() as $team) {
            $teamNames[(int) $team->getId()] = (string) $team->getName();
        }
    }

    $payload = [];
    foreach ($positions as $positionId => $position) {
        $teamId = (int) $position->getTeamId();
        $payload[] = [
            'positionId' => $positionId,
            'positionName' => (string) $position->getName(),
            'ministryId' => (int) $position->getMinistryId(),
            'ministryName' => $ministryNames[(int) $position->getMinistryId()] ?? null,
            'teamId' => $teamId,
            'teamName' => $teamNames[$teamId] ?? null,
        ];
    }

    usort($payload, static fn (array $a, array $b): int => [$a['ministryName'] ?? '', $a['positionName']]
        <=> [$b['ministryName'] ?? '', $b['positionName']]);

    return SlimUtils::renderJSON($response, ['qualifications' => $payload]);
}

/**
 * @OA\Get(
 *     path="/volunteer/me/assignments/{assignmentId}/substitutes",
 *     operationId="listMyVolunteerSubstituteCandidates",
 *     summary="Who I may offer as my substitute",
 *     description="The picker behind section 5.6's Find a sub. Deliberately narrower than the coordinator's /eligible: it excludes the caller and anyone already holding this position on this occurrence - the exact two cases propose-substitute refuses - so the picker can never offer a name the server will then reject. 403, never 404, for someone else's assignment.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="assignmentId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not my assignment, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such assignment"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function listMyVolunteerSubstituteCandidates(Request $request, Response $response): Response
{
    $personId = (int) AuthenticationManager::getCurrentUser()->getId();
    [$assignment, $status, $message] = volunteerMeFindAssignment($request, $personId);

    if ($assignment === null) {
        return SlimUtils::renderErrorJSON($response, $message, [], $status, null, $request);
    }

    $params = $request->getQueryParams();
    $query = isset($params['q']) && trim((string) $params['q']) !== '' ? trim((string) $params['q']) : null;

    try {
        $people = (new VolunteerAssignmentService())->getSubstituteCandidates($assignment, $query);
    } catch (\Throwable $e) {
        return volunteerAssignmentError($request, $response, $e);
    }

    return SlimUtils::renderJSON($response, ['people' => $people]);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Everything a member card needs about each occurrence behind a set of assignments —
 * times, ministry, team and position names — in one pass rather than per row.
 *
 * Times come from `VolunteerScheduleService::resolveOccurrenceWindow()`, the single
 * source of truth (§3.4): a linked occurrence reports the event's time, so a coordinator
 * moving the service moves what the volunteer sees, with no V2 write at all (D4).
 *
 * @param VolunteerAssignment[] $assignments
 *
 * @return array<int, array{positionNames: array<int, string>, ministryName: ?string, teamName: ?string, start: ?string, end: ?string, occurrenceDate: ?string, occurrenceStatus: ?string}>
 */
function volunteerMeOccurrenceContext(array $assignments, VolunteerAssignmentService $service): array
{
    $occurrenceIds = array_values(array_unique(array_map(
        static fn (VolunteerAssignment $a): int => (int) $a->getOccurrenceId(),
        $assignments
    )));
    if ($occurrenceIds === []) {
        return [];
    }

    $positionNames = volunteerAssignmentPositionNames(array_map(
        static fn (VolunteerAssignment $a): int => (int) $a->getPositionId(),
        $assignments
    ));

    $schedules = $service->getScheduleService();

    $occurrences = [];
    foreach (VolunteerOccurrenceQuery::create()->filterById($occurrenceIds, Criteria::IN)->find() as $occurrence) {
        $occurrences[(int) $occurrence->getId()] = $occurrence;
    }

    $scheduleRows = [];
    foreach (
        VolunteerScheduleQuery::create()
            ->filterById(array_map(
                static fn (VolunteerOccurrence $o): int => (int) $o->getScheduleId(),
                $occurrences
            ), Criteria::IN)
            ->find() as $schedule
    ) {
        $scheduleRows[(int) $schedule->getId()] = $schedule;
    }

    $ministryNames = [];
    foreach (
        VolunteerMinistryQuery::create()
            ->filterById(array_map(static fn ($s): int => (int) $s->getMinistryId(), $scheduleRows), Criteria::IN)
            ->find() as $ministry
    ) {
        $ministryNames[(int) $ministry->getId()] = (string) $ministry->getName();
    }

    // D18: every schedule names a team, so there is nothing to filter out here.
    $teamIds = array_values(array_map(static fn ($s): int => (int) $s->getTeamId(), $scheduleRows));
    $teamNames = [];
    if ($teamIds !== []) {
        foreach (VolunteerTeamQuery::create()->filterById($teamIds, Criteria::IN)->find() as $team) {
            $teamNames[(int) $team->getId()] = (string) $team->getName();
        }
    }

    $context = [];
    foreach ($occurrences as $occurrenceId => $occurrence) {
        $schedule = $scheduleRows[(int) $occurrence->getScheduleId()] ?? null;
        $window = $schedules->resolveOccurrenceWindow($occurrence);
        $teamId = $schedule !== null && $schedule->getTeamId() !== null ? (int) $schedule->getTeamId() : null;

        $context[$occurrenceId] = [
            'positionNames' => $positionNames,
            'ministryName' => $schedule === null ? null : ($ministryNames[(int) $schedule->getMinistryId()] ?? null),
            'teamName' => $teamId === null ? null : ($teamNames[$teamId] ?? null),
            'start' => $window['start'] === null ? null : $window['start']->format('Y-m-d H:i:s'),
            'end' => $window['end'] === null ? null : $window['end']->format('Y-m-d H:i:s'),
            'occurrenceDate' => $occurrence->getOccurrenceDate('Y-m-d'),
            'occurrenceStatus' => $occurrence->getStatus(),
        ];
    }

    return $context;
}

/**
 * A strict `YYYY-MM-DD` query parameter, as a DateTime in `sTimeZone`.
 * Query strings never pass through `InputSanitizationMiddleware`, which is
 * body-only — hence the explicit parse.
 */
function volunteerMeParseDate(?string $raw): ?\DateTimeInterface
{
    if ($raw === null || $raw === '') {
        return null;
    }

    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, DateTimeUtils::getConfiguredTimezone());

    return $parsed === false ? null : $parsed;
}
