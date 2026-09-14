<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Event;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerRequirement;
use ChurchCRM\model\ChurchCRM\VolunteerRequirementQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSchedule;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\VolunteerAssignmentService;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Service\VolunteerScheduleService;
use ChurchCRM\Slim\Middleware\Api\VolunteerMinistryMiddleware;
use ChurchCRM\Slim\Middleware\Api\VolunteerOccurrenceMiddleware;
use ChurchCRM\Slim\Middleware\Api\VolunteerScheduleMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\VolunteerCoordinatorRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\VolunteerV2EnabledMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Volunteer Management V2 API — schedules, occurrences and staffing requirements (#9708).
 *
 * The schedule half of design §3.3.2. Assignments and the staffing workhorse
 * (`/occurrences/{id}/staffing`, `/eligible`, `/assignments`) live in `volunteer-assignment.php`
 * (#9709).
 *
 * The derived `liveCount` / `gapCount` / `openCount` / `pendingCount` columns of the occurrence
 * list and detail ARE served from here (#9709 filled them in), but they are not computed here:
 * every one of them comes from `VolunteerAssignmentService::getGaps()`, the single gap
 * implementation (§2.11.3). This file must never grow a second derivation.
 *
 * This group chains `VolunteerV2EnabledMiddleware` and the coordinator role gate itself:
 * Slim 4 scopes `->add()` to the single RouteCollectorProxy it is chained on, so nothing
 * propagates from the groups opened in volunteer-status.php or volunteer-scopes.php, and an
 * ungated group would be reachable in every rollout state.
 *
 * Middleware order is LIFO — the last `->add()` runs first. On every route the order is
 * therefore: rollout gate → coarse role gate → entity load + per-record scope check →
 * input sanitizer → handler. The sanitizer runs last on purpose: there is no point
 * normalising a payload for a caller who is about to be refused.
 */
$app->group('/volunteer', function (RouteCollectorProxy $group): void {
    // ── Schedules under a ministry ──────────────────────────────────────────
    $group->group('/ministries/{ministryId:[0-9]+}/schedules', function (RouteCollectorProxy $schedules): void {
        $schedules->get('', 'listVolunteerSchedules');
        $schedules->post('', 'createVolunteerSchedule')
            ->add(new InputSanitizationMiddleware([
                'name' => 'text',
                'linkMode' => 'enum:' . VolunteerSchedule::LINK_MODE_EVENT_TYPE . ',' . VolunteerSchedule::LINK_MODE_STANDALONE,
                'titleFilter' => 'text',
                // Optional so an absent field still reaches the service's own §2.8
                // invariant check with its specific message, rather than being rejected
                // here with a generic "is required".
                'recurType' => 'enum?:' . implode(',', VolunteerSchedule::allRecurTypes()),
                'recurDow' => 'enum?:' . implode(',', VolunteerSchedule::allRecurDows()),
                'windowStart' => 'date?',
                'windowEnd' => 'date?',
            ]));
    })->add(new VolunteerMinistryMiddleware());

    // ── One schedule ────────────────────────────────────────────────────────
    $group->group('/schedules/{scheduleId:[0-9]+}', function (RouteCollectorProxy $schedule): void {
        $schedule->get('', 'getVolunteerSchedule');
        $schedule->post('', 'updateVolunteerSchedule')
            ->add(new InputSanitizationMiddleware([
                'name' => 'text',
                'linkMode' => 'enum?:' . VolunteerSchedule::LINK_MODE_EVENT_TYPE . ',' . VolunteerSchedule::LINK_MODE_STANDALONE,
                'titleFilter' => 'text',
                'recurType' => 'enum?:' . implode(',', VolunteerSchedule::allRecurTypes()),
                'recurDow' => 'enum?:' . implode(',', VolunteerSchedule::allRecurDows()),
                'windowStart' => 'date?',
                'windowEnd' => 'date?',
            ]));
        $schedule->delete('', 'deleteVolunteerSchedule');

        $schedule->post('/generate', 'generateVolunteerOccurrences')
            ->add(new InputSanitizationMiddleware(['through' => 'date?']));

        $schedule->get('/requirements', 'listVolunteerScheduleRequirements');
        $schedule->post('/requirements', 'upsertVolunteerScheduleRequirement')
            ->add(new InputSanitizationMiddleware(['positionId' => 'int', 'notes' => 'text']));
    })->add(new VolunteerScheduleMiddleware());

    // ── Occurrences ─────────────────────────────────────────────────────────
    // Declared before the {occurrenceId} group so the literal collection path is not
    // swallowed by the parameterised one.
    $group->get('/occurrences', 'listVolunteerOccurrences');

    $group->group('/occurrences/{occurrenceId:[0-9]+}', function (RouteCollectorProxy $occurrence): void {
        $occurrence->get('', 'getVolunteerOccurrence');
        $occurrence->post('/status', 'setVolunteerOccurrenceStatus')
            ->add(new InputSanitizationMiddleware([
                'status' => 'enum:' . implode(',', VolunteerOccurrence::allStatuses()),
            ]));
        $occurrence->get('/requirements', 'listVolunteerOccurrenceRequirements');
        $occurrence->post('/requirements', 'upsertVolunteerOccurrenceRequirement')
            ->add(new InputSanitizationMiddleware(['positionId' => 'int', 'notes' => 'text']));
        // "Set the whole plan for this week" and "go back to the schedule's plan". The
        // per-position upsert above cannot say "and NOT this position", which is exactly
        // what an editor listing every position with a checkbox has to say.
        //
        // No InputSanitizationMiddleware: its field map is declarative and per-field, and
        // the only field here is a nested array it has no type for. The rows are
        // validated and their notes sanitized in VolunteerScheduleService.
        $occurrence->post('/requirements/replace', 'replaceVolunteerOccurrenceRequirements');
        $occurrence->delete('/requirements', 'clearVolunteerOccurrenceRequirements');
    })->add(new VolunteerOccurrenceMiddleware());

    // ── One requirement ─────────────────────────────────────────────────────
    // There is no RequirementMiddleware among #9706's seven: a requirement has no scope of
    // its own, it inherits the scope of whichever parent it names, so the handler resolves
    // that parent and asks the authorization service directly.
    $group->delete('/requirements/{requirementId:[0-9]+}', 'deleteVolunteerRequirement');
})->add(VolunteerCoordinatorRoleAuthMiddleware::class)->add(new VolunteerV2EnabledMiddleware());

// ── Wire shaping ────────────────────────────────────────────────────────────

/**
 * One schedule for the wire. `occurrenceCount` is the cheap "has this been generated yet?"
 * signal a list screen needs; it is a COUNT, not a hydration of the occurrences.
 */
function volunteerScheduleToArray(VolunteerSchedule $schedule): array
{
    $eventType = $schedule->getEventTypeId() === null
        ? null
        : EventTypeQuery::create()->findPk((int) $schedule->getEventTypeId());

    return [
        'id' => (int) $schedule->getId(),
        'ministryId' => (int) $schedule->getMinistryId(),
        'teamId' => (int) $schedule->getTeamId(),
        'name' => $schedule->getName(),
        'linkMode' => $schedule->getLinkMode(),
        'eventTypeId' => $schedule->getEventTypeId() === null ? null : (int) $schedule->getEventTypeId(),
        'eventTypeName' => $eventType !== null ? $eventType->getName() : null,
        'titleFilter' => $schedule->getTitleFilter(),
        'recurType' => $schedule->getRecurType(),
        'recurDow' => $schedule->getRecurDow(),
        'recurDom' => $schedule->getRecurDom() === null ? null : (int) $schedule->getRecurDom(),
        'startTime' => $schedule->getStartTime('H:i:s'),
        'endTime' => $schedule->getEndTime('H:i:s'),
        'windowStart' => $schedule->getWindowStart('Y-m-d'),
        'windowEnd' => $schedule->getWindowEnd('Y-m-d'),
        'generateAheadDays' => (int) $schedule->getGenerateAheadDays(),
        'active' => (bool) $schedule->getActive(),
        'occurrenceCount' => VolunteerOccurrenceQuery::create()
            ->filterByScheduleId((int) $schedule->getId())
            ->count(),
    ];
}

/**
 * One occurrence for the wire.
 *
 * `startDateTime` / `endDateTime` are the raw columns — **null for a linked occurrence**, so
 * a client can see for itself that V2 keeps no copy — while `start` / `end` are the effective
 * times resolved by VolunteerScheduleService::resolveOccurrenceWindow(), which reads the
 * event row when the occurrence is linked. That pair is the whole of D4 made visible.
 */
function volunteerOccurrenceToArray(
    VolunteerOccurrence $occurrence,
    VolunteerScheduleService $service,
    ?VolunteerSchedule $schedule = null,
    array $counts = []
): array {
    $schedule ??= VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());
    $window = $service->resolveOccurrenceWindow($occurrence);

    $requirements = $service->getEffectiveRequirements((int) $occurrence->getId());
    $requiredCount = 0;
    $requirementCount = 0;
    $overridden = false;
    foreach ($requirements as $requirement) {
        $min = (int) $requirement->getMinCount();
        $requiredCount += $min;
        if (($requirement->getMaxCount() === null ? $min : (int) $requirement->getMaxCount()) > 0) {
            $requirementCount++;
        }
        if ($requirement->getOccurrenceId() !== null) {
            $overridden = true;
        }
    }

    return [
        'id' => (int) $occurrence->getId(),
        'scheduleId' => (int) $occurrence->getScheduleId(),
        'scheduleName' => $schedule !== null ? $schedule->getName() : null,
        'ministryId' => $schedule !== null ? (int) $schedule->getMinistryId() : null,
        'teamId' => $schedule !== null ? (int) $schedule->getTeamId() : null,
        'eventId' => $occurrence->getEventId() === null ? null : (int) $occurrence->getEventId(),
        'occurrenceDate' => $occurrence->getOccurrenceDate('Y-m-d'),
        'startDateTime' => $occurrence->getStartDateTime('Y-m-d H:i:s'),
        'endDateTime' => $occurrence->getEndDateTime('Y-m-d H:i:s'),
        'start' => $window['start'] === null ? null : $window['start']->format('Y-m-d H:i:s'),
        'end' => $window['end'] === null ? null : $window['end']->format('Y-m-d H:i:s'),
        'status' => $occurrence->getStatus(),
        'notes' => $occurrence->getNotes(),
        'requiredCount' => $requiredCount,
        // How many positions this occurrence can actually take someone in. Not
        // `requiredCount`, which adds the minimums up — a Min 0 / Max 1 requirement is a
        // real slot with no required body — and not a bare row count either, because a
        // Min 0 / Max 0 row is an occurrence saying "not this week" about a position its
        // schedule asks for. A client uses it to say "No staffing needs set" instead of
        // wrongly reporting an unplanned occurrence as fully staffed (§2.10).
        'requirementCount' => $requirementCount,
        // True when the occurrence carries override rows of its own, so a screen can
        // offer "use the schedule's needs again" only when there is one to go back to.
        // Read off the merge that was already resolved above rather than counting rows
        // again — this shape is built up to MAX_OCCURRENCE_LIST times per listing.
        'requirementsOverridden' => $overridden,
        // Derived staffing counts (#9709, §2.11.3). Present only when the caller passed
        // them in from VolunteerAssignmentService::getGaps(); a caller that did not ask
        // gets zeros rather than a silently different second derivation.
        'liveCount' => (int) ($counts['liveCount'] ?? 0),
        'gapCount' => (int) ($counts['gapCount'] ?? 0),
        'openCount' => (int) ($counts['openCount'] ?? 0),
        'pendingCount' => (int) ($counts['pendingCount'] ?? 0),
        // The short positions by name, so a list can say "1 Lead Teacher, 2 Helper"
        // instead of a bare number a coordinator cannot act on. Sliced out of the same
        // getGaps() result the totals come from — never a second derivation.
        'gaps' => volunteerOccurrenceGapList($counts),
        'generatedDate' => $occurrence->getGeneratedDate('Y-m-d H:i:s'),
    ];
}

/**
 * The genuinely-short positions of one getGaps() summary, in the order the merge
 * resolved them.
 *
 * @param array<string, mixed> $counts
 *
 * @return array<int, array{positionId: int, positionName: ?string, gapCount: int}>
 */
function volunteerOccurrenceGapList(array $counts): array
{
    $gaps = [];
    foreach ((array) ($counts['requirements'] ?? []) as $requirement) {
        if ((int) ($requirement['gapCount'] ?? 0) <= 0) {
            continue;
        }
        $gaps[] = [
            'positionId' => (int) $requirement['positionId'],
            'positionName' => $requirement['positionName'] ?? null,
            'gapCount' => (int) $requirement['gapCount'],
        ];
    }

    return $gaps;
}

/**
 * One requirement for the wire. `source` says which level the row came from, so a staffing
 * screen can show "overridden for this week" without re-deriving the merge.
 */
function volunteerRequirementToArray(VolunteerRequirement $requirement, array $counts = []): array
{
    $position = VolunteerPositionQuery::create()->findPk((int) $requirement->getPositionId());

    return [
        'id' => (int) $requirement->getId(),
        'scheduleId' => $requirement->getScheduleId() === null ? null : (int) $requirement->getScheduleId(),
        'occurrenceId' => $requirement->getOccurrenceId() === null ? null : (int) $requirement->getOccurrenceId(),
        'positionId' => (int) $requirement->getPositionId(),
        'positionName' => $position !== null ? $position->getName() : null,
        'minCount' => (int) $requirement->getMinCount(),
        'maxCount' => $requirement->getMaxCount() === null ? null : (int) $requirement->getMaxCount(),
        'notes' => $requirement->getNotes(),
        'source' => $requirement->getOccurrenceId() === null ? 'schedule' : 'occurrence',
        // Same rule as the occurrence shape: the counts arrive from getGaps(), they are
        // never derived here (#9709, §2.11.3).
        'liveCount' => (int) ($counts['liveCount'] ?? 0),
        'gapCount' => (int) ($counts['gapCount'] ?? 0),
        'openCount' => (int) ($counts['openCount'] ?? 0),
        'pendingCount' => (int) ($counts['pendingCount'] ?? 0),
    ];
}

/**
 * A strict `YYYY-MM-DD` query parameter. The body equivalent is declarative
 * (InputSanitizationMiddleware), but query strings never pass through it.
 */
function volunteerParseDateParam(?string $raw): ?string
{
    if ($raw === null || $raw === '') {
        return null;
    }

    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, DateTimeUtils::getConfiguredTimezone());

    return $parsed !== false && $parsed->format('Y-m-d') === $raw ? $raw : null;
}

/**
 * The `?text=` needle of the occurrence list, sanitised.
 *
 * `InputUtils::sanitizeText()` trims and strips tags, exactly as the body sanitizer
 * would — `InputSanitizationMiddleware` only touches a parsed BODY, and this arrives
 * in the query string, so the route has to do it itself.
 *
 * Returns null when there is nothing to filter on, so the caller can skip the whole
 * clause rather than adding a `LIKE '%%'` that matches everything anyway.
 */
function volunteerOccurrenceTextFilter(mixed $raw): ?string
{
    if (!is_string($raw)) {
        return null;
    }

    $needle = InputUtils::sanitizeText($raw);

    return $needle === '' ? null : $needle;
}

/**
 * One needle as a `LIKE` pattern that matches it literally.
 *
 * `%` and `_` are wildcards to `LIKE` and mean nothing to the person who typed them,
 * so they are escaped: a search for `100%` must find "100% Attendance" and not every
 * title there is. The backslash itself goes first, or the escapes get re-escaped.
 */
function volunteerOccurrenceLikePattern(string $needle): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
}

// ── Schedules ───────────────────────────────────────────────────────────────

/**
 * @OA\Get(
 *     path="/volunteer/ministries/{ministryId}/schedules",
 *     operationId="listVolunteerSchedules",
 *     summary="List a ministry's volunteer schedules",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="teamId", in="query", required=false, @OA\Schema(type="integer"),
 *         description="Only schedules owned by this team"),
 *     @OA\Parameter(name="active", in="query", required=false, @OA\Schema(type="boolean"),
 *         description="Only active schedules"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="schedules", type="array", @OA\Items(type="object")))
 *     )
 * )
 */
function listVolunteerSchedules(Request $request, Response $response): Response
{
    $ministry = $request->getAttribute('volunteerMinistry');
    $params = $request->getQueryParams();

    $query = VolunteerScheduleQuery::create()->filterByMinistryId((int) $ministry->getId());

    if (isset($params['teamId']) && $params['teamId'] !== '') {
        $query->filterByTeamId((int) $params['teamId']);
    }
    if (isset($params['active']) && $params['active'] !== '') {
        $query->filterByActive(filter_var($params['active'], FILTER_VALIDATE_BOOLEAN));
    }

    $schedules = $query->orderByName()->find();

    return SlimUtils::renderJSON($response, [
        'schedules' => array_map('volunteerScheduleToArray', iterator_to_array($schedules, false)),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/ministries/{ministryId}/schedules",
 *     operationId="createVolunteerSchedule",
 *     summary="Create a volunteer schedule, linked to an event type or standalone",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="ministryId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"name","linkMode","teamId","windowStart"},
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="linkMode", type="string", enum={"event_type","standalone"}),
 *         @OA\Property(property="teamId", type="integer", description="Required: a schedule always belongs to one of the ministry's teams"),
 *         @OA\Property(property="eventTypeId", type="integer", nullable=true, description="Required when linkMode is event_type"),
 *         @OA\Property(property="titleFilter", type="string", nullable=true, description="Optional LIKE narrowing on the event title"),
 *         @OA\Property(property="recurType", type="string", nullable=true, enum={"none","weekly","monthly","yearly"}, description="Standalone schedules only"),
 *         @OA\Property(property="recurDow", type="string", nullable=true, description="Standalone weekly schedules"),
 *         @OA\Property(property="recurDom", type="integer", nullable=true, description="Standalone monthly schedules"),
 *         @OA\Property(property="startTime", type="string", nullable=true, example="19:00:00"),
 *         @OA\Property(property="endTime", type="string", nullable=true, example="20:30:00"),
 *         @OA\Property(property="windowStart", type="string", format="date"),
 *         @OA\Property(property="windowEnd", type="string", format="date", nullable=true),
 *         @OA\Property(property="generateAheadDays", type="integer", nullable=true, example=56),
 *         @OA\Property(property="requirements", type="array", nullable=true,
 *             description="The schedule's whole staffing plan, set in one request. Omit the field to leave the plan alone; send an empty array to clear it. Positions not listed are removed. The schedule row and its plan are written in one transaction.",
 *             @OA\Items(type="object",
 *                 required={"positionId","minCount"},
 *                 @OA\Property(property="positionId", type="integer"),
 *                 @OA\Property(property="minCount", type="integer", example=1),
 *                 @OA\Property(property="maxCount", type="integer", nullable=true, example=1),
 *                 @OA\Property(property="notes", type="string", nullable=true)
 *             )
 *         )
 *     )),
 *     @OA\Response(response=400, description="A design section 2.8 invariant was violated"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this ministry, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such ministry"),
 *     @OA\Response(response=201, description="Created")
 * )
 */
function createVolunteerSchedule(Request $request, Response $response): Response
{
    $ministry = $request->getAttribute('volunteerMinistry');
    $input = (array) $request->getParsedBody();

    try {
        $schedule = (new VolunteerScheduleService())->createSchedule(
            $ministry,
            $input,
            AuthenticationManager::getCurrentUser()
        );
    } catch (\RuntimeException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, ['schedule' => volunteerScheduleToArray($schedule)], 201);
}

/**
 * @OA\Get(
 *     path="/volunteer/schedules/{scheduleId}",
 *     operationId="getVolunteerSchedule",
 *     summary="Read one volunteer schedule",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="scheduleId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this schedule, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such schedule"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function getVolunteerSchedule(Request $request, Response $response): Response
{
    $schedule = $request->getAttribute('volunteerSchedule');

    return SlimUtils::renderJSON($response, ['schedule' => volunteerScheduleToArray($schedule)]);
}

/**
 * @OA\Post(
 *     path="/volunteer/schedules/{scheduleId}",
 *     operationId="updateVolunteerSchedule",
 *     summary="Update a volunteer schedule",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="scheduleId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(type="object",
 *         description="Any subset of the create payload; omitted fields keep their stored value. A `requirements` array replaces the whole staffing plan — positions not listed are removed, an empty array clears it, and an absent field leaves it untouched.")),
 *     @OA\Response(response=400, description="A design section 2.8 invariant was violated"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this schedule, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such schedule"),
 *     @OA\Response(response=200, description="Updated")
 * )
 */
function updateVolunteerSchedule(Request $request, Response $response): Response
{
    $schedule = $request->getAttribute('volunteerSchedule');
    $input = (array) $request->getParsedBody();

    try {
        $updated = (new VolunteerScheduleService())->updateSchedule(
            $schedule,
            $input,
            AuthenticationManager::getCurrentUser()
        );
    } catch (\RuntimeException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, ['schedule' => volunteerScheduleToArray($updated)]);
}

/**
 * @OA\Delete(
 *     path="/volunteer/schedules/{scheduleId}",
 *     operationId="deleteVolunteerSchedule",
 *     summary="Delete a volunteer schedule and its generated occurrences",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="scheduleId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this schedule, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such schedule"),
 *     @OA\Response(response=409, description="Occurrences of this schedule still carry assignments"),
 *     @OA\Response(response=200, description="Deleted")
 * )
 */
function deleteVolunteerSchedule(Request $request, Response $response): Response
{
    $schedule = $request->getAttribute('volunteerSchedule');

    try {
        (new VolunteerScheduleService())->deleteSchedule($schedule, AuthenticationManager::getCurrentUser());
    } catch (\RuntimeException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 409, null, $request);
    }

    return SlimUtils::renderSuccessJSON($response);
}

/**
 * @OA\Post(
 *     path="/volunteer/schedules/{scheduleId}/generate",
 *     operationId="generateVolunteerOccurrences",
 *     summary="Materialise this schedule's occurrences up to a date",
 *     description="Idempotent. A linked schedule attaches one occurrence to each existing event of its type inside the window; a standalone schedule generates its own dates. No calendar event is ever created.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="scheduleId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=false, @OA\JsonContent(
 *         @OA\Property(property="through", type="string", format="date", nullable=true,
 *             description="Defaults to today plus the schedule's generateAheadDays")
 *     )),
 *     @OA\Response(response=400, description="Malformed date, or the run would exceed the occurrence cap"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this schedule, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such schedule"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="created", type="integer"),
 *             @OA\Property(property="existing", type="integer"),
 *             @OA\Property(property="through", type="string", format="date")
 *         )
 *     )
 * )
 */
function generateVolunteerOccurrences(Request $request, Response $response): Response
{
    $schedule = $request->getAttribute('volunteerSchedule');
    $input = (array) $request->getParsedBody();

    $through = null;
    if (isset($input['through']) && $input['through'] !== '') {
        // The sanitizer has already proved the shape; this only turns it into a DateTime.
        $through = DateTimeUtils::createDateTime((string) $input['through']);
    }

    try {
        $result = (new VolunteerScheduleService())->generateOccurrences($schedule, $through);
    } catch (\RuntimeException | \InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, $result);
}

// ── Requirements ────────────────────────────────────────────────────────────

/**
 * @OA\Get(
 *     path="/volunteer/schedules/{scheduleId}/requirements",
 *     operationId="listVolunteerScheduleRequirements",
 *     summary="List a schedule's template staffing requirements",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="scheduleId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this schedule, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such schedule"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(@OA\Property(property="requirements", type="array", @OA\Items(type="object")))
 *     )
 * )
 */
function listVolunteerScheduleRequirements(Request $request, Response $response): Response
{
    $schedule = $request->getAttribute('volunteerSchedule');

    $requirements = VolunteerRequirementQuery::create()
        ->filterByScheduleId((int) $schedule->getId())
        ->orderByPositionId()
        ->find();

    return SlimUtils::renderJSON($response, [
        'requirements' => array_map('volunteerRequirementToArray', iterator_to_array($requirements, false)),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/schedules/{scheduleId}/requirements",
 *     operationId="upsertVolunteerScheduleRequirement",
 *     summary="Create or update a template staffing requirement on a schedule",
 *     description="Upsert on the (schedule, position) unique key: re-posting the same position updates the counts on the existing row.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="scheduleId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"positionId","minCount"},
 *         @OA\Property(property="positionId", type="integer"),
 *         @OA\Property(property="minCount", type="integer", example=1),
 *         @OA\Property(property="maxCount", type="integer", nullable=true, example=1),
 *         @OA\Property(property="notes", type="string", nullable=true)
 *     )),
 *     @OA\Response(response=400, description="Unknown position, or counts out of range"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this schedule, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such schedule"),
 *     @OA\Response(response=200, description="The requirement already existed and was updated"),
 *     @OA\Response(response=201, description="Created")
 * )
 */
function upsertVolunteerScheduleRequirement(Request $request, Response $response): Response
{
    $schedule = $request->getAttribute('volunteerSchedule');

    return volunteerUpsertRequirement($request, $response, $schedule, null);
}

/**
 * @OA\Post(
 *     path="/volunteer/occurrences/{occurrenceId}/requirements",
 *     operationId="upsertVolunteerOccurrenceRequirement",
 *     summary="Override a staffing requirement for one occurrence",
 *     description="'This week we need four, not two.' The override wins over the schedule's template for that position only.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="occurrenceId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"positionId","minCount"},
 *         @OA\Property(property="positionId", type="integer"),
 *         @OA\Property(property="minCount", type="integer"),
 *         @OA\Property(property="maxCount", type="integer", nullable=true),
 *         @OA\Property(property="notes", type="string", nullable=true)
 *     )),
 *     @OA\Response(response=400, description="Unknown position, or counts out of range"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this occurrence, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such occurrence"),
 *     @OA\Response(response=200, description="The override already existed and was updated"),
 *     @OA\Response(response=201, description="Created")
 * )
 */
function upsertVolunteerOccurrenceRequirement(Request $request, Response $response): Response
{
    $occurrence = $request->getAttribute('volunteerOccurrence');

    return volunteerUpsertRequirement($request, $response, null, $occurrence);
}

/**
 * @OA\Get(
 *     path="/volunteer/occurrences/{occurrenceId}/requirements",
 *     operationId="listVolunteerOccurrenceRequirements",
 *     summary="This occurrence's effective staffing needs, and the positions it could need",
 *     description="Everything the staffing-needs editor has to draw: the EFFECTIVE requirements from VolunteerScheduleService::getEffectiveRequirements() (each carrying `source`, so the caller can see which are the schedule's and which are this occurrence's own), plus the active positions of the owning team, so a position that has no requirement row can still be offered as an unchecked line. Served under the occurrence's own scope check, so a team leader can edit one week without reaching past their own team.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="occurrenceId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this occurrence, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such occurrence"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function listVolunteerOccurrenceRequirements(Request $request, Response $response): Response
{
    $occurrence = $request->getAttribute('volunteerOccurrence');
    $service = new VolunteerScheduleService();
    $schedule = VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());

    $effective = $service->getEffectiveRequirements((int) $occurrence->getId());

    return SlimUtils::renderJSON($response, [
        'occurrenceId' => (int) $occurrence->getId(),
        'scheduleId' => $schedule === null ? null : (int) $schedule->getId(),
        'scheduleName' => $schedule === null ? null : $schedule->getName(),
        'requirements' => array_map('volunteerRequirementToArray', array_values($effective)),
        'overridden' => volunteerRequirementsAreOverridden($effective),
        'positions' => volunteerCandidatePositions($schedule),
        // Which positions the SCHEDULE asks for. The editor needs them because the merge
        // is a union (§2.10): an occurrence cannot remove a schedule's requirement by
        // leaving it out, only outvote it. Dropping a position for one week is therefore
        // an override of Min 0 / Max 0, and the editor can only know to write one by
        // knowing which positions the schedule provides.
        'schedulePositionIds' => $schedule === null ? [] : array_map(
            static fn (VolunteerRequirement $requirement): int => (int) $requirement->getPositionId(),
            iterator_to_array(
                VolunteerRequirementQuery::create()->filterByScheduleId((int) $schedule->getId())->find(),
                false
            )
        ),
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/occurrences/{occurrenceId}/requirements/replace",
 *     operationId="replaceVolunteerOccurrenceRequirements",
 *     summary="Set this occurrence's whole staffing plan, overriding the schedule's",
 *     description="Writes occurrence-level override rows that match the payload exactly: positions not listed lose their override. An empty array means 'this occurrence needs nobody' and is a real, storable answer — it is NOT the same as having no overrides, which means 'follow the schedule'. Use DELETE on the same path for that.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="occurrenceId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"requirements"},
 *         @OA\Property(property="requirements", type="array",
 *             @OA\Items(type="object",
 *                 required={"positionId","minCount"},
 *                 @OA\Property(property="positionId", type="integer"),
 *                 @OA\Property(property="minCount", type="integer"),
 *                 @OA\Property(property="maxCount", type="integer", nullable=true),
 *                 @OA\Property(property="notes", type="string", nullable=true)
 *             )
 *         )
 *     )),
 *     @OA\Response(response=400, description="Unknown position, a duplicate position, or counts out of range"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this occurrence, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such occurrence"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function replaceVolunteerOccurrenceRequirements(Request $request, Response $response): Response
{
    $occurrence = $request->getAttribute('volunteerOccurrence');
    $input = (array) $request->getParsedBody();

    if (!array_key_exists('requirements', $input)) {
        return SlimUtils::renderErrorJSON($response, gettext('The staffing needs must be a list'), [], 400, null, $request);
    }

    try {
        (new VolunteerScheduleService())->replaceRequirements(null, $occurrence, $input['requirements']);
    } catch (\RuntimeException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return volunteerRenderEffectiveRequirements($response, $occurrence);
}

/**
 * @OA\Delete(
 *     path="/volunteer/occurrences/{occurrenceId}/requirements",
 *     operationId="clearVolunteerOccurrenceRequirements",
 *     summary="Drop this occurrence's overrides so it follows the schedule again",
 *     description="The 'use the schedule's needs' reset. Nothing was ever copied from the schedule at generation time — the merge is derived on every read (section 2.10) — so removing the override rows is the whole of the reset, and the occurrence immediately reflects the schedule's current plan, including requirements added long after it was generated.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="occurrenceId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this occurrence, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such occurrence"),
 *     @OA\Response(response=200, description="OK")
 * )
 */
function clearVolunteerOccurrenceRequirements(Request $request, Response $response): Response
{
    $occurrence = $request->getAttribute('volunteerOccurrence');

    try {
        (new VolunteerScheduleService())->clearRequirements(null, $occurrence);
    } catch (\RuntimeException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return volunteerRenderEffectiveRequirements($response, $occurrence);
}

/** The answer both write routes give back: the merge as it now stands. */
function volunteerRenderEffectiveRequirements(Response $response, VolunteerOccurrence $occurrence): Response
{
    $effective = (new VolunteerScheduleService())->getEffectiveRequirements((int) $occurrence->getId());

    return SlimUtils::renderJSON($response, [
        'occurrenceId' => (int) $occurrence->getId(),
        'requirements' => array_map('volunteerRequirementToArray', array_values($effective)),
        'overridden' => volunteerRequirementsAreOverridden($effective),
    ]);
}

/**
 * Whether any row of a resolved merge is the occurrence's own.
 *
 * @param VolunteerRequirement[] $effective
 */
function volunteerRequirementsAreOverridden(array $effective): bool
{
    foreach ($effective as $requirement) {
        if ($requirement->getOccurrenceId() !== null) {
            return true;
        }
    }

    return false;
}

/**
 * The positions a schedule's staffing plan may name: the active positions of its team,
 * and nothing else.
 *
 * D18 removed the "ministry-wide position offered to every team" branch that used to live
 * here. It was the mechanism behind the ambiguity the decision exists to fix — two teams
 * under one ministry could each own a "Lead Teacher", and a list that mixed a team's own
 * positions with the ministry's showed the name twice with nothing to tell them apart.
 * Now a schedule offers exactly its team's positions, so `teamName` is carried only for a
 * caller that shows several teams' positions side by side.
 *
 * @return array<int, array<string, mixed>>
 */
function volunteerCandidatePositions(?VolunteerSchedule $schedule): array
{
    if ($schedule === null) {
        return [];
    }

    $teamId = (int) $schedule->getTeamId();
    $team = VolunteerTeamQuery::create()->findPk($teamId);
    $teamName = $team === null ? null : (string) $team->getName();

    $positions = VolunteerPositionQuery::create()
        ->filterByMinistryId((int) $schedule->getMinistryId())
        ->filterByTeamId($teamId)
        ->filterByActive(true)
        ->orderByOrder()
        ->orderByName()
        ->find();

    $rows = [];
    foreach ($positions as $position) {
        $rows[] = [
            'id' => (int) $position->getId(),
            'name' => $position->getName(),
            'teamId' => $teamId,
            'teamName' => $teamName,
            'order' => (int) $position->getOrder(),
        ];
    }

    return $rows;
}

/**
 * The shared half of the two upsert routes. Exactly one parent is non-null, which is what
 * `vreq_one_parent_chk` enforces in SQL — the route shape makes it structurally impossible
 * for a caller to name both.
 */
function volunteerUpsertRequirement(
    Request $request,
    Response $response,
    ?VolunteerSchedule $schedule,
    ?VolunteerOccurrence $occurrence
): Response {
    $input = (array) $request->getParsedBody();

    $position = VolunteerPositionQuery::create()->findPk((int) $input['positionId']);
    if ($position === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Position not found'), [], 400, null, $request);
    }

    if (!isset($input['minCount']) || !is_numeric($input['minCount'])) {
        return SlimUtils::renderErrorJSON($response, gettext('A minimum count is required'), [], 400, null, $request);
    }
    $maxCount = isset($input['maxCount']) && $input['maxCount'] !== '' && $input['maxCount'] !== null
        ? (int) $input['maxCount']
        : null;

    $existing = VolunteerRequirementQuery::create()
        ->filterByPositionId((int) $position->getId())
        ->filterByScheduleId($schedule === null ? null : (int) $schedule->getId())
        ->filterByOccurrenceId($occurrence === null ? null : (int) $occurrence->getId())
        ->findOne();

    try {
        $requirement = (new VolunteerScheduleService())->upsertRequirement(
            $schedule,
            $occurrence,
            $position,
            (int) $input['minCount'],
            $maxCount,
            isset($input['notes']) ? (string) $input['notes'] : null
        );
    } catch (\RuntimeException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON(
        $response,
        ['requirement' => volunteerRequirementToArray($requirement)],
        $existing === null ? 201 : 200
    );
}

/**
 * @OA\Delete(
 *     path="/volunteer/requirements/{requirementId}",
 *     operationId="deleteVolunteerRequirement",
 *     summary="Remove a staffing requirement",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="requirementId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this requirement's schedule, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such requirement"),
 *     @OA\Response(response=200, description="Deleted")
 * )
 */
function deleteVolunteerRequirement(Request $request, Response $response, array $args): Response
{
    $requirement = VolunteerRequirementQuery::create()->findPk((int) $args['requirementId']);
    if ($requirement === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Requirement not found'), [], 404, null, $request);
    }

    // A requirement carries no scope of its own; it inherits the one of whichever parent it
    // names. 404 before 403, matching every entity middleware.
    $currentUser = AuthenticationManager::getCurrentUser();
    $authz = new VolunteerAuthorizationService();

    if ($requirement->getOccurrenceId() !== null) {
        $occurrence = VolunteerOccurrenceQuery::create()->findPk((int) $requirement->getOccurrenceId());
        $allowed = $occurrence !== null && $authz->canManageOccurrence($currentUser, $occurrence);
    } else {
        $schedule = VolunteerScheduleQuery::create()->findPk((int) $requirement->getScheduleId());
        $allowed = $schedule !== null && $authz->canManageSchedule($currentUser, $schedule);
    }

    if (!$allowed) {
        return SlimUtils::renderErrorJSON($response, gettext('Not authorized for this requirement'), [], 403, null, $request);
    }

    $requirement->delete();

    return SlimUtils::renderSuccessJSON($response);
}

// ── Occurrences ─────────────────────────────────────────────────────────────

/**
 * @OA\Get(
 *     path="/volunteer/occurrences",
 *     operationId="listVolunteerOccurrences",
 *     summary="List occurrences inside a date window, scoped to the caller",
 *     description="`from` and `to` are mandatory (design M9: no pagination protocol is invented for one module) and the result set is hard-capped.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="from", in="query", required=true, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", required=true, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="ministryId", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="teamId", in="query", required=false, @OA\Schema(type="integer"), description="Only the occurrences whose schedule belongs to this team."),
 *     @OA\Parameter(name="text", in="query", required=false, @OA\Schema(type="string", maxLength=100), description="Case-insensitive substring of the occurrence's title - its schedule's name, or for an event-linked occurrence the linked event's title. The narrowing happens in the query; % and _ are escaped so they are matched literally rather than as LIKE wildcards."),
 *     @OA\Parameter(name="hasGaps", in="query", required=false, @OA\Schema(type="boolean"), description="Return only occurrences that are short of at least one required volunteer (#9709). The filter is applied AFTER the counts are derived, because a gap is derived and there is nothing in the occurrence table to filter on."),
 *     @OA\Parameter(name="scheduleId", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"scheduled","cancelled"})),
 *     @OA\Response(response=400, description="Missing, malformed or inverted window"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Volunteer coordinator access is required, or V2 is not enabled"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="occurrences", type="array", @OA\Items(type="object")),
 *             @OA\Property(property="limit", type="integer"),
 *             @OA\Property(property="capped", type="boolean")
 *         )
 *     )
 * )
 */
function listVolunteerOccurrences(Request $request, Response $response): Response
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

    $currentUser = AuthenticationManager::getCurrentUser();
    $authz = new VolunteerAuthorizationService();

    // Read scoping happens in the QUERY, never in PHP after hydration (§4.4): the schedule
    // ids the caller may see are resolved first, and an empty allow-list short-circuits to
    // an empty result rather than being handed to a filter whose behaviour on [] would have
    // to be trusted.
    $scheduleQuery = VolunteerScheduleQuery::create();

    if (!$authz->isGlobalManager($currentUser)) {
        $ministryIds = $authz->getManagedMinistryIds($currentUser);
        $teamIds = $authz->getManagedTeamIds($currentUser);
        if ($ministryIds === [] && $teamIds === []) {
            return SlimUtils::renderJSON($response, [
                'occurrences' => [],
                'limit' => VolunteerScheduleService::MAX_OCCURRENCE_LIST,
                'capped' => false,
            ]);
        }

        $scheduleQuery
            ->condition('byMinistry', 'VolunteerSchedule.MinistryId IN ?', $ministryIds === [] ? [0] : $ministryIds)
            ->condition('byTeam', 'VolunteerSchedule.TeamId IN ?', $teamIds === [] ? [0] : $teamIds)
            ->where(['byMinistry', 'byTeam'], Criteria::LOGICAL_OR);
    }

    if (isset($params['ministryId']) && $params['ministryId'] !== '') {
        $scheduleQuery->filterByMinistryId((int) $params['ministryId']);
    }
    if (isset($params['teamId']) && $params['teamId'] !== '') {
        $scheduleQuery->filterByTeamId((int) $params['teamId']);
    }
    if (isset($params['scheduleId']) && $params['scheduleId'] !== '') {
        $scheduleQuery->filterById((int) $params['scheduleId']);
    }

    $schedules = [];
    foreach ($scheduleQuery->find() as $schedule) {
        $schedules[(int) $schedule->getId()] = $schedule;
    }

    if ($schedules === []) {
        return SlimUtils::renderJSON($response, [
            'occurrences' => [],
            'limit' => VolunteerScheduleService::MAX_OCCURRENCE_LIST,
            'capped' => false,
        ]);
    }

    $query = VolunteerOccurrenceQuery::create()
        ->filterByScheduleId(array_keys($schedules), Criteria::IN)
        ->filterByOccurrenceDate($from, Criteria::GREATER_EQUAL)
        ->filterByOccurrenceDate($to, Criteria::LESS_EQUAL);

    if (isset($params['status']) && $params['status'] !== '') {
        $query->filterByStatus((string) $params['status']);
    }

    // `?text=` — the Event box of the ministry page's Occurrences tab.
    //
    // An occurrence's title is its schedule's name, EXCEPT when it is linked to a
    // calendar event, where the event owns the words a coordinator would search for
    // (D4: a linked occurrence keeps no times and no title of its own). So the needle
    // is matched against both, and an occurrence is kept when either half matches.
    //
    // Both halves resolve to an id list first and the OR lands on the OCCURRENCE
    // query, which is the one that could return hundreds of rows — the narrowing is
    // in the SQL, never a filter over hydrated results and never in the browser.
    // `$schedules` is the caller's already-scoped, already-fetched map, so matching
    // names over it costs no query and cannot widen what the caller may see.
    $text = volunteerOccurrenceTextFilter($params['text'] ?? null);
    if ($text !== null) {
        $titleScheduleIds = [];
        foreach ($schedules as $scheduleId => $schedule) {
            if (stripos((string) $schedule->getName(), $text) !== false) {
                $titleScheduleIds[] = (int) $scheduleId;
            }
        }

        $titleEventIds = array_map(
            static fn (Event $event): int => (int) $event->getId(),
            iterator_to_array(
                EventQuery::create()
                    ->filterByTitle(volunteerOccurrenceLikePattern($text), Criteria::LIKE)
                    ->find(),
                false
            )
        );

        // `[0]` rather than `[]`: Propel renders an empty IN as a clause that is never
        // true, but spelling the impossible id keeps both halves of the OR readable
        // and makes the "no schedule matched, only events did" case obvious.
        $query
            ->condition('byScheduleName', 'VolunteerOccurrence.ScheduleId IN ?', $titleScheduleIds === [] ? [0] : $titleScheduleIds)
            ->condition('byEventTitle', 'VolunteerOccurrence.EventId IN ?', $titleEventIds === [] ? [0] : $titleEventIds)
            ->where(['byScheduleName', 'byEventTitle'], Criteria::LOGICAL_OR);
    }

    // One extra row is fetched so the response can say truthfully whether the cap bit.
    $occurrences = $query
        ->orderByOccurrenceDate()
        ->orderById()
        ->limit(VolunteerScheduleService::MAX_OCCURRENCE_LIST + 1)
        ->find();

    $rows = iterator_to_array($occurrences, false);
    $capped = count($rows) > VolunteerScheduleService::MAX_OCCURRENCE_LIST;
    if ($capped) {
        $rows = array_slice($rows, 0, VolunteerScheduleService::MAX_OCCURRENCE_LIST);
    }

    $service = new VolunteerScheduleService();

    // One getGaps() call for the whole page, never one per row: the gap derivation is
    // the same single implementation the staffing view uses (#9709, §2.11.3), and it is
    // built to take every occurrence id at once precisely so a list does not fan out.
    $assignments = new VolunteerAssignmentService();
    $gapSummaries = $assignments->getGaps(array_map(
        static fn (VolunteerOccurrence $occurrence): int => (int) $occurrence->getId(),
        $rows
    ));

    // `?hasGaps=1` filters AFTER the counts exist, because a gap is derived and there is
    // nothing in the occurrence table to filter on (§2.11.3).
    $onlyGaps = isset($params['hasGaps']) && filter_var($params['hasGaps'], FILTER_VALIDATE_BOOLEAN);

    $payload = [];
    foreach ($rows as $occurrence) {
        $counts = $gapSummaries[(int) $occurrence->getId()] ?? [];
        if ($onlyGaps && (int) ($counts['gapCount'] ?? 0) <= 0) {
            continue;
        }

        $payload[] = volunteerOccurrenceToArray(
            $occurrence,
            $service,
            $schedules[(int) $occurrence->getScheduleId()] ?? null,
            $counts
        );
    }

    return SlimUtils::renderJSON($response, [
        'occurrences' => $payload,
        'limit' => VolunteerScheduleService::MAX_OCCURRENCE_LIST,
        'capped' => $capped,
    ]);
}

/**
 * @OA\Get(
 *     path="/volunteer/occurrences/{occurrenceId}",
 *     operationId="getVolunteerOccurrence",
 *     summary="Read one occurrence with its effective times and effective requirements",
 *     description="The reported start and end come from the linked calendar event when the occurrence is linked; the requirements are the occurrence's own overrides merged over the schedule's templates.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="occurrenceId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this occurrence, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such occurrence"),
 *     @OA\Response(response=200, description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="occurrence", type="object"),
 *             @OA\Property(property="requirements", type="array", @OA\Items(type="object"))
 *         )
 *     )
 * )
 */
function getVolunteerOccurrence(Request $request, Response $response): Response
{
    /** @var VolunteerOccurrence $occurrence */
    $occurrence = $request->getAttribute('volunteerOccurrence');
    $service = new VolunteerScheduleService();
    $occurrenceId = (int) $occurrence->getId();

    // #9709: the detail reports the same derived counts as the list, from the same
    // getGaps() call — one summary, used for both the occurrence and its requirements.
    $summary = (new VolunteerAssignmentService())->getGaps([$occurrenceId])[$occurrenceId] ?? [];
    $perRequirement = $summary['requirements'] ?? [];

    $requirements = [];
    foreach ($service->getEffectiveRequirements($occurrenceId) as $positionId => $requirement) {
        $requirements[] = volunteerRequirementToArray($requirement, $perRequirement[(int) $positionId] ?? []);
    }

    return SlimUtils::renderJSON($response, [
        'occurrence' => volunteerOccurrenceToArray($occurrence, $service, null, $summary),
        'requirements' => $requirements,
    ]);
}

/**
 * @OA\Post(
 *     path="/volunteer/occurrences/{occurrenceId}/status",
 *     operationId="setVolunteerOccurrenceStatus",
 *     summary="Cancel or restore one occurrence without touching its schedule",
 *     description="Cancelling is not deleting: the row survives so a later generation run does not recreate it.",
 *     tags={"Volunteer"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(name="occurrenceId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"status"},
 *         @OA\Property(property="status", type="string", enum={"scheduled","cancelled"})
 *     )),
 *     @OA\Response(response=400, description="Unknown status"),
 *     @OA\Response(response=401, description="Not authenticated"),
 *     @OA\Response(response=403, description="Not authorized for this occurrence, or V2 is not enabled"),
 *     @OA\Response(response=404, description="No such occurrence"),
 *     @OA\Response(response=200, description="Updated")
 * )
 */
function setVolunteerOccurrenceStatus(Request $request, Response $response): Response
{
    $occurrence = $request->getAttribute('volunteerOccurrence');
    $input = (array) $request->getParsedBody();
    $service = new VolunteerScheduleService();

    try {
        $updated = $service->setOccurrenceStatus(
            $occurrence,
            (string) $input['status'],
            AuthenticationManager::getCurrentUser()
        );
    } catch (\RuntimeException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400, null, $request);
    }

    return SlimUtils::renderJSON($response, [
        'occurrence' => volunteerOccurrenceToArray($updated, $service),
    ]);
}
