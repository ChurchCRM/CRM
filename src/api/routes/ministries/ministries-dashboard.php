<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerAssignment;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerNotification;
use ChurchCRM\model\ChurchCRM\VolunteerNotificationQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSchedule;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSwap;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\VolunteerAssignmentService;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Service\VolunteerScheduleService;
use ChurchCRM\Slim\Middleware\Request\Auth\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Volunteer Management V2 API — the coordinator dashboard aggregate (#9711, §3.3.2).
 *
 * ONE endpoint feeding all five panels of S1 (§5.2). The design is explicit that the
 * dashboard "makes exactly one API call and renders all five panels from it. Do not fan
 * out to five endpoints." — so this file is an aggregate, not a sixth CRUD surface. It
 * owns no business rules: gaps come from `VolunteerAssignmentService::getOpenGaps()`,
 * the single gap implementation (§2.11.3); times come from
 * `VolunteerScheduleService::resolveOccurrenceWindow()`, the one method allowed to
 * decide them (D4); the wire shapes are the ones `ministries-schedule.php` and
 * `ministries-assignment.php` already declare, reused verbatim so a field cannot be
 * spelled two ways across surfaces.
 *
 * **Scoping happens in the QUERY** (§4.4). `volunteerScopedOccurrenceIds()` — the helper
 * #9709 wrote for `/gaps` and `/swaps` — resolves the caller's occurrence allow-list
 * from their ministry and team scopes before anything is hydrated, and every panel is
 * built from that one list. An administrator or global manager has no explicit grants,
 * which is why `isGlobalManager()` is asked first; an empty allow-list short-circuits to
 * empty panels rather than being handed to a filter whose behaviour on `[]` would have
 * to be trusted.
 *
 * **This group opens its own `$app->group('/ministries', …)` and chains
 * `VolunteerV2EnabledMiddleware` itself.** Slim 4 scopes `->add()` to the single
 * `RouteCollectorProxy` it is chained on; nothing propagates from the groups opened in
 * the other volunteer route files, and an ungated group would be reachable in every
 * rollout state (§3.3).
 */
$app->group('/ministries', function (RouteCollectorProxy $group): void {
    $group->get('/dashboard', 'getVolunteerDashboard');
})->add(VolunteerCoordinatorRoleAuthMiddleware::class)->add(new VolunteerV2EnabledMiddleware());

/** Default window, per §3.3.2's `?days=28`. */
const VOLUNTEER_DASHBOARD_DEFAULT_DAYS = 28;

/** Widest window a caller may ask for — a year of Sundays is already 52 rows. */
const VOLUNTEER_DASHBOARD_MAX_DAYS = 365;

/**
 * Per-panel row cap.
 *
 * The occurrence allow-list is already capped at `MAX_OCCURRENCE_LIST` (500) upstream;
 * this is the second cap, on what any one panel may put in front of a human. A
 * coordinator cannot act on 300 gap rows, and a dashboard that ships them is slower for
 * no benefit. `capped` says truthfully when it bit.
 */
const VOLUNTEER_DASHBOARD_PANEL_LIMIT = 100;

/**
 * @OA\Get(
 *     path="/ministries/dashboard",
 *     operationId="getVolunteerDashboard",
 *     summary="Everything the coordinator dashboard needs, in one call",
 *     description="The S1 aggregate (design section 5.2): upcoming occurrences, the gaps that need filling, assignments still awaiting a reply, proposed substitutions, and the number of notifications that failed terminally. Scoped to the caller in the query (section 4.4) — a global manager sees every ministry, a coordinator their ministries, a team leader their teams. The scope block additionally names the ministries and teams the caller may navigate to, which is what gives a team-scope-only user an entry point.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="days", in="query", required=false, description="Window length in days, 1-365, default 28", @OA\Schema(type="integer", minimum=1, maximum=365)),
 *     @OA\Response(response=400, description="The days parameter is not a number in the supported range"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not a volunteer coordinator, or V2 is not enabled"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="days", type="integer"),
 *             @OA\Property(property="from", type="string", format="date"),
 *             @OA\Property(property="to", type="string", format="date"),
 *             @OA\Property(property="upcoming", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="gaps", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="pendingResponses", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="proposedSwaps", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="failedNotifications", type="integer"),
 *             @OA\Property(property="scope", type="object"),
 *             @OA\Property(property="capped", type="boolean")
 *         )
 *     )
 * )
 */
function getVolunteerDashboard(Request $request, Response $response): Response
{
    $params = $request->getQueryParams();

    $days = volunteerDashboardDays($params['days'] ?? null);
    if ($days === null) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('The days parameter must be a whole number between 1 and 365'),
            [],
            400,
            null,
            $request
        );
    }

    $currentUser = AuthenticationManager::getCurrentUser();
    $authz = new VolunteerAuthorizationService();

    $today = DateTimeUtils::getStartOfToday();
    $from = $today->format('Y-m-d');
    $to = (clone $today)->modify('+' . $days . ' days')->format('Y-m-d');

    // One scoped allow-list; every panel below is derived from it. `volunteerScopedOccurrenceIds()`
    // is #9709's helper in ministries-assignment.php — the dashboard does not re-derive scope.
    $occurrenceIds = volunteerDashboardActiveOnly(
        volunteerScopedOccurrenceIds($currentUser, $authz, $from, $to)
    );

    $scope = volunteerDashboardScope($currentUser, $authz);

    if ($occurrenceIds === []) {
        return SlimUtils::renderJSON($response, [
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'upcoming' => [],
            'gaps' => [],
            'pendingResponses' => [],
            'proposedSwaps' => [],
            'failedNotifications' => 0,
            'scope' => $scope,
            'limit' => VOLUNTEER_DASHBOARD_PANEL_LIMIT,
            'capped' => false,
        ]);
    }

    $assignments = new VolunteerAssignmentService();
    $schedules = $assignments->getScheduleService();

    // One hydration of the occurrences and their schedules for the whole page; every
    // panel reads from these two maps rather than issuing its own lookup per row.
    $occurrences = [];
    foreach (
        VolunteerOccurrenceQuery::create()
            ->filterById($occurrenceIds, Criteria::IN)
            ->orderByOccurrenceDate()
            ->orderById()
            ->find() as $occurrence
    ) {
        $occurrences[(int) $occurrence->getId()] = $occurrence;
    }

    $scheduleMap = [];
    foreach (
        VolunteerScheduleQuery::create()
            ->filterById(array_values(array_unique(array_map(
                static fn (VolunteerOccurrence $occurrence): int => (int) $occurrence->getScheduleId(),
                $occurrences
            ))), Criteria::IN)
            ->find() as $schedule
    ) {
        $scheduleMap[(int) $schedule->getId()] = $schedule;
    }

    $ministryNames = volunteerDashboardMinistryNames($scheduleMap);
    $teamNames = volunteerDashboardTeamNames($scheduleMap);

    // The single gap implementation, called ONCE for every occurrence on the page.
    $gapSummaries = $assignments->getGaps(array_keys($occurrences));

    $capped = false;

    // ── Panel 4: upcoming occurrences ───────────────────────────────────────
    $upcoming = [];
    foreach ($occurrences as $occurrence) {
        if (count($upcoming) >= VOLUNTEER_DASHBOARD_PANEL_LIMIT) {
            $capped = true;
            break;
        }
        $schedule = $scheduleMap[(int) $occurrence->getScheduleId()] ?? null;
        $upcoming[] = volunteerOccurrenceToArray(
            $occurrence,
            $schedules,
            $schedule,
            $gapSummaries[(int) $occurrence->getId()] ?? []
        ) + volunteerDashboardContext($occurrence, $schedule, $schedules, $ministryNames, $teamNames);
    }

    // ── Panel 1: the gaps that need filling ─────────────────────────────────
    $gaps = [];
    foreach ($assignments->getOpenGaps(array_keys($occurrences)) as $gap) {
        $occurrence = $occurrences[$gap['occurrenceId']] ?? null;
        if ($occurrence === null) {
            continue;
        }
        $schedule = $scheduleMap[(int) $occurrence->getScheduleId()] ?? null;
        $gaps[] = $gap + volunteerDashboardContext($occurrence, $schedule, $schedules, $ministryNames, $teamNames);
    }
    // Soonest deadline first (§5.2 item 1): a gap this Sunday matters more than one in
    // six weeks. Ties break on the position order the coordinator set up.
    usort($gaps, static fn (array $a, array $b): int => [$a['occurrenceDate'] ?? '', $a['positionId']]
        <=> [$b['occurrenceDate'] ?? '', $b['positionId']]);
    if (count($gaps) > VOLUNTEER_DASHBOARD_PANEL_LIMIT) {
        $gaps = array_slice($gaps, 0, VOLUNTEER_DASHBOARD_PANEL_LIMIT);
        $capped = true;
    }

    // ── Panel 2: assignments still awaiting a reply ─────────────────────────
    $pendingRows = iterator_to_array(
        VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId(array_keys($occurrences), Criteria::IN)
            ->filterByStatus(VolunteerAssignment::STATUS_PENDING)
            ->orderById()
            ->limit(VOLUNTEER_DASHBOARD_PANEL_LIMIT + 1)
            ->find(),
        false
    );
    if (count($pendingRows) > VOLUNTEER_DASHBOARD_PANEL_LIMIT) {
        $pendingRows = array_slice($pendingRows, 0, VOLUNTEER_DASHBOARD_PANEL_LIMIT);
        $capped = true;
    }

    $pendingNames = volunteerAssignmentPersonNames(array_map(
        static fn (VolunteerAssignment $assignment): int => (int) $assignment->getPersonId(),
        $pendingRows
    ));
    $pendingPositions = volunteerAssignmentPositionNames(array_map(
        static fn (VolunteerAssignment $assignment): int => (int) $assignment->getPositionId(),
        $pendingRows
    ));

    // §5.2 item 2 asks for the assignments "whose occurrence is inside the reminder
    // window". Every pending row in the window is returned — a coordinator chasing
    // replies wants the whole list — and each row says whether it is inside that window,
    // so the page can mark the urgent ones without a second request. 0 lead hours means
    // reminders are off (Appendix B), and then nothing is inside the window.
    $leadHours = max(0, SystemConfig::getIntValue('iVolunteerReminderLeadHours'));
    $reminderCutoff = $leadHours === 0
        ? null
        : DateTimeUtils::getToday()->modify('+' . $leadHours . ' hours');

    $pendingResponses = [];
    foreach ($pendingRows as $assignment) {
        $occurrence = $occurrences[(int) $assignment->getOccurrenceId()] ?? null;
        if ($occurrence === null) {
            continue;
        }
        $schedule = $scheduleMap[(int) $occurrence->getScheduleId()] ?? null;
        $context = volunteerDashboardContext($occurrence, $schedule, $schedules, $ministryNames, $teamNames);

        $pendingResponses[] = volunteerAssignmentToArray(
            $assignment,
            $pendingNames,
            [],
            $pendingPositions[(int) $assignment->getPositionId()] ?? null
        ) + $context + [
            'withinReminderWindow' => $reminderCutoff !== null
                && $context['start'] !== null
                && $context['start'] <= $reminderCutoff->format('Y-m-d H:i:s'),
        ];
    }
    usort($pendingResponses, static fn (array $a, array $b): int => ($a['occurrenceDate'] ?? '') <=> ($b['occurrenceDate'] ?? ''));

    // ── Panel 3: proposed substitutions ─────────────────────────────────────
    $swaps = $assignments->listSwaps(array_keys($occurrences), VolunteerSwap::STATUS_PROPOSED);
    if (count($swaps) > VOLUNTEER_DASHBOARD_PANEL_LIMIT) {
        $swaps = array_slice($swaps, 0, VOLUNTEER_DASHBOARD_PANEL_LIMIT);
        $capped = true;
    }

    $swapAssignments = [];
    foreach (
        VolunteerAssignmentQuery::create()
            ->filterById(array_map(static fn (VolunteerSwap $swap): int => (int) $swap->getAssignmentId(), $swaps), Criteria::IN)
            ->find() as $assignment
    ) {
        $swapAssignments[(int) $assignment->getId()] = $assignment;
    }

    $swapPersonIds = [];
    foreach ($swaps as $swap) {
        $swapPersonIds[] = (int) $swap->getProposedByPersonId();
        $swapPersonIds[] = (int) $swap->getProposedPersonId();
    }
    $swapNames = volunteerAssignmentPersonNames($swapPersonIds);
    $swapPositions = volunteerAssignmentPositionNames(array_map(
        static fn (VolunteerAssignment $assignment): int => (int) $assignment->getPositionId(),
        $swapAssignments
    ));

    $proposedSwaps = [];
    foreach ($swaps as $swap) {
        $assignment = $swapAssignments[(int) $swap->getAssignmentId()] ?? null;
        $occurrence = $assignment === null
            ? null
            : ($occurrences[(int) $assignment->getOccurrenceId()] ?? null);
        $schedule = $occurrence === null ? null : ($scheduleMap[(int) $occurrence->getScheduleId()] ?? null);

        $row = volunteerSwapToArray(
            $swap,
            $swapNames,
            $assignment,
            $assignment === null ? null : ($swapPositions[(int) $assignment->getPositionId()] ?? null)
        );

        $proposedSwaps[] = $occurrence === null
            ? $row
            : $row + volunteerDashboardContext($occurrence, $schedule, $schedules, $ministryNames, $teamNames);
    }

    return SlimUtils::renderJSON($response, [
        'days' => $days,
        'from' => $from,
        'to' => $to,
        'upcoming' => $upcoming,
        'gaps' => $gaps,
        'pendingResponses' => $pendingResponses,
        'proposedSwaps' => $proposedSwaps,
        'failedNotifications' => volunteerDashboardFailedNotifications(array_keys($occurrences)),
        'scope' => $scope,
        'limit' => VOLUNTEER_DASHBOARD_PANEL_LIMIT,
        'capped' => $capped,
    ]);
}

/**
 * Validate `?days=`. Returns null when the caller sent something that is not a whole
 * number in range — a 400 rather than a silent fallback, because a coordinator who asked
 * for a window and got a different one has been lied to.
 */
function volunteerDashboardDays(mixed $raw): ?int
{
    if ($raw === null || $raw === '') {
        return VOLUNTEER_DASHBOARD_DEFAULT_DAYS;
    }

    if (!is_string($raw) && !is_int($raw)) {
        return null;
    }

    $value = filter_var((string) $raw, FILTER_VALIDATE_INT);
    if ($value === false || $value < 1 || $value > VOLUNTEER_DASHBOARD_MAX_DAYS) {
        return null;
    }

    return $value;
}

/**
 * The occurrence context every panel row carries so it can be rendered and clicked
 * without a second request: when it is, which schedule and ministry it belongs to, and
 * which team — the last of which is what lets a team-scoped screen group its rows.
 *
 * `start` / `end` come from `resolveOccurrenceWindow()`, so a LINKED occurrence reports
 * the event's time and V2 keeps no copy of it (D4).
 *
 * @param array<int, string> $ministryNames
 * @param array<int, string> $teamNames
 *
 * @return array<string, mixed>
 */
function volunteerDashboardContext(
    VolunteerOccurrence $occurrence,
    ?VolunteerSchedule $schedule,
    VolunteerScheduleService $schedules,
    array $ministryNames,
    array $teamNames
): array {
    $window = $schedules->resolveOccurrenceWindow($occurrence);
    $ministryId = $schedule === null ? null : (int) $schedule->getMinistryId();
    $teamId = $schedule !== null && $schedule->getTeamId() !== null ? (int) $schedule->getTeamId() : null;

    return [
        'occurrenceId' => (int) $occurrence->getId(),
        'occurrenceDate' => $occurrence->getOccurrenceDate('Y-m-d'),
        'start' => $window['start'] === null ? null : $window['start']->format('Y-m-d H:i:s'),
        'end' => $window['end'] === null ? null : $window['end']->format('Y-m-d H:i:s'),
        'occurrenceStatus' => $occurrence->getStatus(),
        'eventId' => $occurrence->getEventId() === null ? null : (int) $occurrence->getEventId(),
        'scheduleId' => $schedule === null ? null : (int) $schedule->getId(),
        'scheduleName' => $schedule === null ? null : $schedule->getName(),
        'ministryId' => $ministryId,
        'ministryName' => $ministryId === null ? null : ($ministryNames[$ministryId] ?? null),
        'teamId' => $teamId,
        'teamName' => $teamId === null ? null : ($teamNames[$teamId] ?? null),
    ];
}

/**
 * Ministry names for every schedule on the page, in one query.
 *
 * @param array<int, VolunteerSchedule> $schedules
 *
 * @return array<int, string>
 */
function volunteerDashboardMinistryNames(array $schedules): array
{
    $ids = array_values(array_unique(array_map(
        static fn (VolunteerSchedule $schedule): int => (int) $schedule->getMinistryId(),
        $schedules
    )));
    if ($ids === []) {
        return [];
    }

    $names = [];
    foreach (VolunteerMinistryQuery::create()->filterById($ids, Criteria::IN)->find() as $ministry) {
        $names[(int) $ministry->getId()] = (string) $ministry->getName();
    }

    return $names;
}

/**
 * Team names for every schedule on the page that has one, in one query.
 *
 * @param array<int, VolunteerSchedule> $schedules
 *
 * @return array<int, string>
 */
function volunteerDashboardTeamNames(array $schedules): array
{
    $ids = [];
    foreach ($schedules as $schedule) {
        if ($schedule->getTeamId() !== null) {
            $ids[] = (int) $schedule->getTeamId();
        }
    }
    $ids = array_values(array_unique($ids));
    if ($ids === []) {
        return [];
    }

    $names = [];
    foreach (VolunteerTeamQuery::create()->filterById($ids, Criteria::IN)->find() as $team) {
        $names[(int) $team->getId()] = (string) $team->getName();
    }

    return $names;
}

/**
 * How many notifications in the caller's scope failed terminally.
 *
 * `failed` means five attempts and no more retries (§2.14); `pending` is still due and
 * `skipped` was a deliberate non-send (email off, do-not-email, an occurrence that has
 * already ended), so neither is a failure and neither is counted. An occurrence-scoped
 * `gap_alert` row carries `vntf_vocc_ID` and no assignment, so both shapes are counted —
 * in one query, scoped by the same occurrence allow-list as every other panel.
 *
 * @param int[] $occurrenceIds
 */
function volunteerDashboardFailedNotifications(array $occurrenceIds): int
{
    if ($occurrenceIds === []) {
        return 0;
    }

    $assignmentIds = array_map('intval', VolunteerAssignmentQuery::create()
        ->filterByOccurrenceId($occurrenceIds, Criteria::IN)
        ->select(['Id'])
        ->find()
        ->toArray());

    $query = VolunteerNotificationQuery::create()
        ->filterByStatus(VolunteerNotification::STATUS_FAILED);

    if ($assignmentIds === []) {
        return $query->filterByOccurrenceId($occurrenceIds, Criteria::IN)->count();
    }

    return $query
        ->condition('byOccurrence', 'VolunteerNotification.OccurrenceId IN ?', $occurrenceIds)
        ->condition('byAssignment', 'VolunteerNotification.AssignmentId IN ?', $assignmentIds)
        ->where(['byOccurrence', 'byAssignment'], Criteria::LOGICAL_OR)
        ->count();
}

/**
 * What this caller may navigate to.
 *
 * This is the fix for a genuine hole in the design: §3.3.1's ministry list is scoped to
 * `getManagedMinistryIds()`, which is empty for a **team leader**, so a pure team leader
 * passed the coordinator role gate and then found nothing to open — no ministry, no
 * entry point, a dead dashboard. §4.6 grants a team leader authority over their team's
 * pools, positions, qualifications, schedules and assignments, all of which hang off a
 * ministry they must at least be able to see. So the block reports:
 *
 *   - `ministries` — the ministries they coordinate, plus the parent ministries of the
 *     teams they lead, each flagged `manageable` so the UI can render the second kind
 *     read-only rather than offering edits the server would refuse;
 *   - `teams` — every team they may act on, with its parent named.
 *
 * `manageable` is advisory for rendering only; the server gate is still
 * `canManageMinistry()` on the route (§4.5).
 *
 * @return array<string, mixed>
 */
/**
 * Drop the occurrences of DEACTIVATED ministries (product-owner decision,
 * 2026-09-17): a deactivated ministry is parked, and nothing of it — upcoming
 * occurrences, gaps, pending responses, swaps, failed sends — belongs on the
 * "what needs my attention" page. Its own page still shows all of it. Applied
 * here, after the scope helper, because that helper also serves the occurrence
 * list of a deactivated ministry's page, which must keep working.
 *
 * @param int[] $occurrenceIds
 *
 * @return int[]
 */
function volunteerDashboardActiveOnly(array $occurrenceIds): array
{
    if ($occurrenceIds === []) {
        return [];
    }

    return array_map('intval', VolunteerOccurrenceQuery::create()
        ->filterById($occurrenceIds, Criteria::IN)
        ->useScheduleQuery()
            ->useMinistryQuery()
                ->filterByActive(true)
            ->endUse()
        ->endUse()
        ->select(['Id'])
        ->find()
        ->toArray());
}

function volunteerDashboardScope(User $user, VolunteerAuthorizationService $authz): array
{
    $isManager = $authz->isGlobalManager($user);

    // A global manager and an administrator hold no explicit grants at all, so
    // `getManagedTeamIds()` is empty for them (it lists grants, not authority). Reporting
    // "no teams" to the one tier that can see every team would be a plain lie, so for
    // them the list is every team, resolved below from the ministries they can see.
    $teamQuery = VolunteerTeamQuery::create();
    $teams = [];
    $teamParentMinistryIds = [];

    if (!$isManager) {
        $teamIds = $authz->getManagedTeamIds($user);
        if ($teamIds === []) {
            $teamQuery = null;
        } else {
            $teamQuery->filterById($teamIds, Criteria::IN);
        }
    }

    if ($teamQuery !== null) {
        // The card is for active ministries only (2026-09-17); a deactivated
        // ministry's teams are reached from its own page.
        $teamQuery->useMinistryQuery()->filterByActive(true)->endUse();
        foreach ($teamQuery->orderByName()->find() as $team) {
            $teams[] = [
                'id' => (int) $team->getId(),
                'name' => $team->getName(),
                'ministryId' => (int) $team->getMinistryId(),
                'active' => (bool) $team->getActive(),
            ];
            $teamParentMinistryIds[] = (int) $team->getMinistryId();
        }
    }

    $manageableIds = $authz->getManagedMinistryIds($user);

    $ministryQuery = VolunteerMinistryQuery::create()->filterByActive(true);
    if (!$isManager) {
        $visibleIds = array_values(array_unique(array_merge($manageableIds, $teamParentMinistryIds)));
        if ($visibleIds === []) {
            return ['isAdmin' => $user->isAdmin(), 'isManager' => $isManager, 'ministries' => [], 'teams' => $teams];
        }
        $ministryQuery->filterById($visibleIds, Criteria::IN);
    }

    $ministries = [];
    foreach ($ministryQuery->orderByName()->find() as $ministry) {
        $id = (int) $ministry->getId();
        $ministries[] = [
            'id' => $id,
            'name' => $ministry->getName(),
            'description' => $ministry->getDescription(),
            'active' => (bool) $ministry->getActive(),
            'manageable' => $isManager || in_array($id, $manageableIds, true),
        ];
    }

    // Teams are named with their parent now that the ministry names are loaded.
    $ministryNames = [];
    foreach ($ministries as $ministry) {
        $ministryNames[$ministry['id']] = $ministry['name'];
    }
    foreach ($teams as $index => $team) {
        $teams[$index]['ministryName'] = $ministryNames[$team['ministryId']] ?? null;
    }

    return [
        'isAdmin' => $user->isAdmin(),
        'isManager' => $isManager,
        'ministries' => $ministries,
        'teams' => $teams,
    ];
}
