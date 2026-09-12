<?php

namespace ChurchCRM\Service;

use ChurchCRM\Exceptions\VolunteerSetupException;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\Map\VolunteerAssignmentTableMap;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerAssignment;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerNotificationQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPosition;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerRequirement;
use ChurchCRM\model\ChurchCRM\VolunteerResponse;
use ChurchCRM\model\ChurchCRM\VolunteerResponseQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSchedule;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSwap;
use ChurchCRM\model\ChurchCRM\VolunteerSwapQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;

/**
 * Volunteer Management v2 — the assignment, response, gap and substitution workflow
 * (#9709, epic #9701).
 *
 * Normative: design §2.11 (the row, §2.11.1 lifecycle, §2.11.2 invariants I1–I8,
 * §2.11.3 the derived gap), §2.12 (append-only responses), §2.13 (swaps), §3.4 (this
 * signature list), §3.6 (which change enqueues which notification) and §4.6 (who may
 * do what).
 *
 * Five things about this class are load-bearing and should not be "simplified" away:
 *
 * 1. **`getGaps()` is THE gap implementation.** §2.11.3 says a persisted gap is a cache
 *    that will disagree with the assignments the first time a decline is processed off
 *    the happy path, so there is no gap table and no second derivation. The occurrence
 *    list, the occurrence detail, the staffing view, `GET /gaps` and (later) the
 *    dashboard and the self-service opportunities list all call this one method.
 *    `declined` and `cancelled` rows simply do not count as live, which is how "a
 *    decline creates a gap" falls out with no extra code at all.
 *
 * 2. **Every mutation takes the acting `User` and authorizes through
 *    `VolunteerAuthorizationService`.** The route middleware has already answered "may
 *    this caller touch this record"; the service answers it again because the service
 *    is also reachable from the cart sink, the member surface and (later) a timer job,
 *    where no entity middleware ran. UI hiding is never the gate (D5).
 *
 * 3. **Historical rows are immutable (I6).** Once an occurrence's end has passed the
 *    only write allowed is `pending|accepted → completed`. Responses are append-only
 *    (§2.12) and are never updated or deleted by anything here.
 *
 * 4. **Swap approval is ONE transaction** (§2.13) that moves the original to
 *    `substituted`, inserts the replacement, appends a response row to both and enqueues
 *    the outbox rows. The original is never edited beyond its status, so the audit trail
 *    #9709 promises survives intact.
 *
 * 5. **Notifications are enqueued, never sent.** `VolunteerNotificationService::enqueue()`
 *    is called inside the same transaction as the state change (§2.14), so a rolled-back
 *    decline leaves no alert behind. Delivery is #9710's.
 *
 * Errors are `VolunteerSetupException` — the shared Volunteer v2 error carrying its own
 * HTTP status, so a route handler renders `getStatusCode()` and never re-derives one
 * from the message. The statuses are fixed by §2.11.2 and §4.8: I2 is `403`, I1/I3/I5
 * and every illegal transition are `409`, I4 is `400`.
 */
class VolunteerAssignmentService
{
    /** Statuses that occupy a slot, and therefore close a gap (§2.11.3). */
    public const LIVE_STATUSES = [
        VolunteerAssignment::STATUS_PENDING,
        VolunteerAssignment::STATUS_ACCEPTED,
    ];

    /**
     * Statuses that count as "this person served (or is down to serve) on that date"
     * for the last-served rotation ordering. See `getEligiblePeople()`.
     */
    private const SERVED_STATUSES = [
        VolunteerAssignment::STATUS_PENDING,
        VolunteerAssignment::STATUS_ACCEPTED,
        VolunteerAssignment::STATUS_COMPLETED,
    ];

    /**
     * A row in one of these states is spent and may be re-used by a later assign()
     * of the same person to the same position on the same occurrence (I8). Anything
     * else is either live (I1 refuses a duplicate) or history (I6 refuses a rewrite).
     */
    private const REUSABLE_STATUSES = [
        VolunteerAssignment::STATUS_DECLINED,
        VolunteerAssignment::STATUS_CANCELLED,
    ];

    private LoggerInterface $logger;

    private VolunteerAuthorizationService $authz;

    private VolunteerSetupService $setup;

    private VolunteerScheduleService $schedules;

    private VolunteerNotificationService $notifications;

    public function __construct(
        ?VolunteerAuthorizationService $authz = null,
        ?VolunteerSetupService $setup = null,
        ?VolunteerScheduleService $schedules = null,
        ?VolunteerNotificationService $notifications = null
    ) {
        // Constructor injection with defaults, not a container (F17): the collaborators
        // are memoising (the authorization service caches scope rows per request), so
        // handing the same instances down matters, but there is nothing to wire.
        $this->authz = $authz ?? new VolunteerAuthorizationService();
        $this->setup = $setup ?? new VolunteerSetupService($this->authz);
        $this->schedules = $schedules ?? new VolunteerScheduleService();
        $this->notifications = $notifications ?? new VolunteerNotificationService();
        $this->logger = LoggerUtils::getAppLogger();
    }

    public function getAuthorizationService(): VolunteerAuthorizationService
    {
        return $this->authz;
    }

    public function getNotificationService(): VolunteerNotificationService
    {
        return $this->notifications;
    }

    public function getScheduleService(): VolunteerScheduleService
    {
        return $this->schedules;
    }

    // ── Assign ─────────────────────────────────────────────────────────────

    /**
     * Put a person on a position for an occurrence.
     *
     * Enforces, in this order and for the reasons §2.11.2 gives:
     *
     *   I4  the position belongs to the occurrence's schedule's ministry (and team,
     *       when the position is team-scoped)                              → 400
     *   I5  the occurrence is neither cancelled nor already over            → 409
     *   I2  the person holds an ACTIVE qualification for the position       → 403
     *   I3  the person is in a pool group of the owning ministry or team,
     *       unless the caller passes allowOutsidePool (which is logged)     → 409
     *   I1  one row per (occurrence, position, person) — a live duplicate   → 409
     *   I8  …but a spent row (declined/cancelled) is RE-USED, reset to pending
     *
     * I7/D16 is deliberately absent from that list: a person holding another position
     * on the same occurrence is allowed, unconditionally, with no override flag. The
     * staffing view warns; the API returns 201.
     *
     * @param array{requirementId?: int|null, allowOutsidePool?: bool, source?: string, notes?: string|null, assignedBy?: int|null, status?: string} $opts
     *
     * @throws VolunteerSetupException
     */
    public function assign(
        VolunteerOccurrence $occurrence,
        VolunteerPosition $position,
        int $personId,
        User $actor,
        array $opts = []
    ): VolunteerAssignment {
        $source = $opts['source'] ?? VolunteerAssignment::SOURCE_COORDINATOR;
        $selfSignup = $source === VolunteerAssignment::SOURCE_SELF_SIGNUP;

        if (!$selfSignup && !$this->authz->canManageOccurrence($actor, $occurrence)) {
            throw VolunteerSetupException::forbidden(gettext('Not authorized for this occurrence'));
        }

        $schedule = $this->schedules->requireSchedule($occurrence);
        $this->assertPositionBelongsToSchedule($position, $schedule);
        $this->assertOccurrenceAssignable($occurrence);
        $this->assertPersonExists($personId);
        $this->assertQualified($personId, $position);

        $allowOutsidePool = (bool) ($opts['allowOutsidePool'] ?? false);
        $inPool = $this->isInPool($personId, $schedule);
        if (!$inPool) {
            if (!$allowOutsidePool) {
                throw VolunteerSetupException::conflict(gettext(
                    'That person is not in a volunteer pool for this ministry. Re-send with the out-of-pool override to assign them anyway.'
                ));
            }

            // The override is a deliberate coordinator decision, so it is auditable.
            $this->logger->info('Volunteer assigned from outside the pool', [
                'personId' => $personId,
                'positionId' => $position->getId(),
                'occurrenceId' => $occurrence->getId(),
                'actor' => $actor->getId(),
            ]);
        }

        $existing = VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId((int) $occurrence->getId())
            ->filterByPositionId((int) $position->getId())
            ->filterByPersonId($personId)
            ->findOne();

        if ($existing !== null && !in_array($existing->getStatus(), self::REUSABLE_STATUSES, true)) {
            // I1: the unique key would refuse this anyway; answering 409 here means the
            // caller gets a sentence rather than a driver error.
            throw VolunteerSetupException::conflict(gettext('That person is already assigned to this position on this occurrence'));
        }

        $requirementId = $this->resolveRequirementId($occurrence, $position, $opts['requirementId'] ?? null);

        $connection = Propel::getWriteConnection(VolunteerAssignmentTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            // I8: the SAME row comes back, reset — never a second row, and the response
            // history hanging off it survives, which is the point of reusing it.
            $assignment = $existing ?? new VolunteerAssignment();
            $reused = $existing !== null;

            $assignment->setOccurrenceId((int) $occurrence->getId());
            $assignment->setPositionId((int) $position->getId());
            $assignment->setPersonId($personId);
            $assignment->setRequirementId($requirementId);
            $assignment->setStatus($opts['status'] ?? VolunteerAssignment::STATUS_PENDING);
            $assignment->setSource($source);
            $assignment->setAssignedDate(DateTimeUtils::getToday());
            $assignment->setAssignedByPersonId(
                array_key_exists('assignedBy', $opts)
                    ? $opts['assignedBy']
                    : ($selfSignup ? null : (int) $actor->getId())
            );
            $assignment->setRespondedDate(null);
            $assignment->setNotes($this->normalizeNote($opts['notes'] ?? null));
            $assignment->save($connection);

            // §3.6: assigning enqueues the "you are on the roster, please answer"
            // message. Self-signup is already answered, so #9712 enqueues the
            // `signup_confirm` receipt on that path instead — inside this same
            // transaction (§2.14), so a rolled-back signup leaves no confirmation
            // behind.
            if ($selfSignup) {
                $this->notifications->enqueueSignupConfirm($assignment);
            } else {
                $this->notifications->enqueueAssignment($assignment);
            }

            $connection->commit();

            $this->logger->info($reused ? 'Volunteer assignment reopened' : 'Volunteer assignment created', [
                'assignmentId' => $assignment->getId(),
                'occurrenceId' => $occurrence->getId(),
                'positionId' => $position->getId(),
                'personId' => $personId,
                'source' => $source,
                'inPool' => $inPool,
                'actor' => $actor->getId(),
            ]);

            return $assignment;
        } catch (\Throwable $e) {
            $connection->rollBack();
            if ($e instanceof VolunteerSetupException) {
                throw $e;
            }

            throw VolunteerSetupException::conflict(gettext('That assignment could not be saved'));
        }
    }

    /**
     * Self sign-up (§3.3.3). #9712 owns the endpoint; the rule lives here so the
     * capacity and qualification checks cannot be re-implemented on that surface.
     *
     * @throws VolunteerSetupException
     */
    public function selfSignup(VolunteerOccurrence $occurrence, VolunteerPosition $position, User $actor): VolunteerAssignment
    {
        $personId = (int) $actor->getId();
        $this->assertCapacityAvailable($occurrence, $position);

        return $this->assign($occurrence, $position, $personId, $actor, [
            'source' => VolunteerAssignment::SOURCE_SELF_SIGNUP,
            'status' => VolunteerAssignment::STATUS_ACCEPTED,
            'assignedBy' => null,
        ]);
    }

    /**
     * Assign everyone currently in the session cart to one position (P6, §3.3.2).
     *
     * Per-person failures are reported, never thrown: the coordinator selected fifteen
     * people and wants the twelve who are eligible put on, plus a list of why the other
     * three were not. That is the same contract
     * `VolunteerSetupService::grantQualifications()` already uses for the cart.
     *
     * @param int[] $personIds
     *
     * @return array{assigned: int, skipped: array<int, array{personId: int, reason: string}>, assignments: VolunteerAssignment[]}
     */
    public function assignFromCart(
        VolunteerOccurrence $occurrence,
        VolunteerPosition $position,
        array $personIds,
        User $actor,
        bool $allowOutsidePool = false
    ): array {
        if (!$this->authz->canManageOccurrence($actor, $occurrence)) {
            throw VolunteerSetupException::forbidden(gettext('Not authorized for this occurrence'));
        }

        $assigned = [];
        $skipped = [];

        foreach ($personIds as $personId) {
            try {
                $assigned[] = $this->assign($occurrence, $position, (int) $personId, $actor, [
                    'allowOutsidePool' => $allowOutsidePool,
                ]);
            } catch (VolunteerSetupException $e) {
                $skipped[] = ['personId' => (int) $personId, 'reason' => $e->getMessage()];
            }
        }

        $this->logger->info('Volunteer assignments from cart', [
            'occurrenceId' => $occurrence->getId(),
            'positionId' => $position->getId(),
            'assigned' => count($assigned),
            'skipped' => count($skipped),
            'actor' => $actor->getId(),
        ]);

        return ['assigned' => count($assigned), 'skipped' => $skipped, 'assignments' => $assigned];
    }

    // ── Respond and status changes ─────────────────────────────────────────

    /**
     * Record an accept or a decline (§2.11.1, §2.12).
     *
     * One method serves both surfaces. The member endpoint calls it with the volunteer
     * as `$actor` and `channel = 'web'`; the coordinator endpoint calls it with the
     * coordinator and `channel = 'coordinator'`, which is how "a volunteer phoned to say
     * they cannot make it" is recorded with an honest audit trail — the response row
     * carries whoever actually typed it, not whoever it is about.
     *
     * **Idempotent** (§2.12, §6.6): when the requested response already matches the
     * current status the assignment is returned unchanged and **no** response row is
     * appended. A double-tapped phone is harmless.
     *
     * @throws VolunteerSetupException
     */
    public function respond(
        VolunteerAssignment $assignment,
        string $response,
        User $actor,
        ?string $comment = null,
        string $channel = VolunteerResponse::CHANNEL_WEB
    ): VolunteerAssignment {
        if (!in_array($response, [VolunteerAssignment::STATUS_ACCEPTED, VolunteerAssignment::STATUS_DECLINED], true)) {
            throw VolunteerSetupException::invalid(gettext('A response must be accepted or declined'));
        }

        $isSelf = (int) $assignment->getPersonId() === (int) $actor->getId();
        $isCoordinator = $this->authz->canManageAssignment($actor, $assignment);

        if (!$isSelf && !$isCoordinator) {
            throw VolunteerSetupException::forbidden(gettext('Not authorized for this assignment'));
        }

        // A response typed by the coordinator is recorded as such even when they happen
        // to be answering for themselves — the channel describes the surface, not the
        // relationship.
        if ($channel === VolunteerResponse::CHANNEL_COORDINATOR && !$isCoordinator) {
            throw VolunteerSetupException::forbidden(gettext('Only a coordinator may record a response on someone else\'s behalf'));
        }

        // Idempotency FIRST, before the transition table: "accept an accepted
        // assignment" is not an illegal transition, it is a no-op (§2.12).
        if ($assignment->getStatus() === $response) {
            return $assignment;
        }

        $this->assertTransitionAllowed($assignment, $response);
        $this->assertNotHistorical($assignment);

        $connection = Propel::getWriteConnection(VolunteerAssignmentTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $now = DateTimeUtils::getToday();

            $assignment->setStatus($response);
            $assignment->setRespondedDate($now);
            $assignment->save($connection);

            $this->appendResponse($assignment, (int) $actor->getId(), $response, $channel, $comment, $connection);

            if ($response === VolunteerAssignment::STATUS_DECLINED) {
                // The slot is free again, so nothing is owed to this person any more.
                $this->notifications->cancelPendingFor((int) $assignment->getId());

                // §3.6: only a decline the VOLUNTEER made alerts the coordinators. One
                // the coordinator recorded enqueues nothing — they already know, and
                // mailing them their own data entry is noise.
                if ($channel === VolunteerResponse::CHANNEL_WEB) {
                    $this->notifyGapOpened($assignment, true);
                } else {
                    $this->notifyGapOpened($assignment, false);
                }
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->logger->info('Volunteer assignment response recorded', [
            'assignmentId' => $assignment->getId(),
            'response' => $response,
            'channel' => $channel,
            'actor' => $actor->getId(),
        ]);

        return $assignment;
    }

    /**
     * The coordinator status endpoint (§3.3.2). `accepted` / `declined` are responses
     * recorded on the volunteer's behalf and go through `respond()`; `cancelled` is a
     * coordinator act and goes through `cancel()`. Nothing else is reachable from the
     * API — `substituted` is only ever produced by `approveSwap()` and `completed` only
     * by `markCompleted()`.
     *
     * @throws VolunteerSetupException
     */
    public function setStatus(
        VolunteerAssignment $assignment,
        string $status,
        User $actor,
        ?string $comment = null
    ): VolunteerAssignment {
        return match ($status) {
            VolunteerAssignment::STATUS_ACCEPTED, VolunteerAssignment::STATUS_DECLINED => $this->respond(
                $assignment,
                $status,
                $actor,
                $comment,
                VolunteerResponse::CHANNEL_COORDINATOR
            ),
            VolunteerAssignment::STATUS_CANCELLED => $this->cancel($assignment, $actor, $comment),
            default => throw VolunteerSetupException::invalid(gettext('That status cannot be set directly')),
        };
    }

    /**
     * Cancel an assignment. Coordinator-only, and terminal (§2.11.1).
     *
     * Idempotent in the same sense `respond()` is: cancelling a cancelled row returns
     * it unchanged rather than appending a second `cancelled` response.
     *
     * @throws VolunteerSetupException
     */
    public function cancel(VolunteerAssignment $assignment, User $actor, ?string $comment = null): VolunteerAssignment
    {
        if (!$this->authz->canManageAssignment($actor, $assignment)) {
            throw VolunteerSetupException::forbidden(gettext('Not authorized for this assignment'));
        }

        if ($assignment->getStatus() === VolunteerAssignment::STATUS_CANCELLED) {
            return $assignment;
        }

        $this->assertTransitionAllowed($assignment, VolunteerAssignment::STATUS_CANCELLED);
        $this->assertNotHistorical($assignment);

        $connection = Propel::getWriteConnection(VolunteerAssignmentTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $assignment->setStatus(VolunteerAssignment::STATUS_CANCELLED);
            $assignment->save($connection);

            $this->appendResponse(
                $assignment,
                (int) $actor->getId(),
                VolunteerResponse::RESPONSE_CANCELLED,
                VolunteerResponse::CHANNEL_COORDINATOR,
                $comment,
                $connection
            );

            $this->notifications->cancelPendingFor((int) $assignment->getId());
            // A coordinator cancelling knows the slot is open; no gap_alert is owed.
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->logger->info('Volunteer assignment cancelled', [
            'assignmentId' => $assignment->getId(),
            'actor' => $actor->getId(),
        ]);

        return $assignment;
    }

    /**
     * `DELETE /assignments/{id}` (§3.3.2): "cancel — never hard-deletes once responded;
     * hard-deletes only a pending, never-notified row".
     *
     * "Never-notified" is read against the outbox, which is the only record of what the
     * volunteer has been told: a row is hard-deletable only when **no** notification was
     * ever enqueued for it. Because `assign()` always enqueues one, an assignment a
     * coordinator created is in practice always **cancelled and kept** — which is the
     * behaviour the audit-trail requirement asks for, and what the staffing view shows
     * as a `Cancelled` badge rather than a row that silently vanishes. The delete branch
     * exists for rows created without a message being owed, and keeps the endpoint
     * honest about what §3.3.2 promises.
     *
     * DELETE is also **idempotent on a finished row**: an assignment that is already
     * declined, cancelled, substituted or completed is returned unchanged rather than
     * answering `409`. "Take this off the roster" has already happened, and a teardown
     * or a double-click should not be an error.
     *
     * @return array{assignment: ?VolunteerAssignment, deleted: bool}
     *
     * @throws VolunteerSetupException
     */
    public function cancelOrDelete(VolunteerAssignment $assignment, User $actor, ?string $comment = null): array
    {
        if (!$this->authz->canManageAssignment($actor, $assignment)) {
            throw VolunteerSetupException::forbidden(gettext('Not authorized for this assignment'));
        }

        if (!in_array($assignment->getStatus(), self::LIVE_STATUSES, true)) {
            return ['assignment' => $assignment, 'deleted' => false];
        }

        $hasHistory = VolunteerResponseQuery::create()
            ->filterByAssignmentId((int) $assignment->getId())
            ->count() > 0;
        $hasSwaps = VolunteerSwapQuery::create()
            ->filterByAssignmentId((int) $assignment->getId())
            ->count() > 0;
        $wasNotified = VolunteerNotificationQuery::create()
            ->filterByAssignmentId((int) $assignment->getId())
            ->count() > 0;
        $isReplacement = $assignment->getReplacesAssignmentId() !== null;

        if (
            $assignment->getStatus() === VolunteerAssignment::STATUS_PENDING
            && !$hasHistory
            && !$hasSwaps
            && !$wasNotified
            && !$isReplacement
        ) {
            $assignmentId = (int) $assignment->getId();
            $assignment->delete();

            $this->logger->info('Volunteer assignment deleted', [
                'assignmentId' => $assignmentId,
                'actor' => $actor->getId(),
            ]);

            return ['assignment' => null, 'deleted' => true];
        }

        return ['assignment' => $this->cancel($assignment, $actor, $comment), 'deleted' => false];
    }

    // ── Swaps (§2.13) ──────────────────────────────────────────────────────

    /**
     * The assignee proposes a substitute who has already agreed (D13, UC2).
     *
     * The proposer must be the assignee — this is the volunteer's own act, and the
     * coordinator's equivalent is simply to cancel and assign someone else. At most one
     * `proposed` swap may exist per assignment; MySQL has no partial unique index, so
     * that is enforced here and answers `409`.
     *
     * @throws VolunteerSetupException
     */
    public function proposeSubstitute(
        VolunteerAssignment $assignment,
        int $substitutePersonId,
        User $actor,
        ?string $comment = null
    ): VolunteerSwap {
        if ((int) $assignment->getPersonId() !== (int) $actor->getId()) {
            throw VolunteerSetupException::forbidden(gettext('Only the assigned volunteer may propose a substitute'));
        }

        if (!in_array($assignment->getStatus(), self::LIVE_STATUSES, true)) {
            throw VolunteerSetupException::conflict(gettext('This assignment is no longer active'))
                ->withExtra(['currentStatus' => $assignment->getStatus()]);
        }

        $occurrence = $this->requireOccurrence($assignment);
        $this->assertOccurrenceAssignable($occurrence);

        if ($substitutePersonId === (int) $assignment->getPersonId()) {
            throw VolunteerSetupException::invalid(gettext('You cannot propose yourself as your own substitute'));
        }

        $this->assertPersonExists($substitutePersonId);

        $position = $this->requirePosition((int) $assignment->getPositionId());
        // I2 applies to the replacement too (§2.13) — a substitute who is not qualified
        // is not a substitute.
        $this->assertQualified($substitutePersonId, $position);

        $clash = VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId((int) $assignment->getOccurrenceId())
            ->filterByPositionId((int) $assignment->getPositionId())
            ->filterByPersonId($substitutePersonId)
            ->filterByStatus(self::LIVE_STATUSES, Criteria::IN)
            ->findOne();
        if ($clash !== null) {
            throw VolunteerSetupException::forbidden(gettext('That person already holds this position on this occurrence'));
        }

        $pending = VolunteerSwapQuery::create()
            ->filterByAssignmentId((int) $assignment->getId())
            ->filterByStatus(VolunteerSwap::STATUS_PROPOSED)
            ->findOne();
        if ($pending !== null) {
            throw VolunteerSetupException::conflict(gettext('A substitute has already been proposed for this assignment'));
        }

        $schedule = $this->schedules->requireSchedule($occurrence);

        $connection = Propel::getWriteConnection(VolunteerAssignmentTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $swap = new VolunteerSwap();
            $swap->setAssignmentId((int) $assignment->getId());
            $swap->setProposedByPersonId((int) $actor->getId());
            $swap->setProposedPersonId($substitutePersonId);
            $swap->setStatus(VolunteerSwap::STATUS_PROPOSED);
            $swap->setProposedDate(DateTimeUtils::getToday());
            $swap->setComment($this->normalizeNote($comment));
            $swap->save($connection);

            // §2.13: every swap state leaves a matching response row on the ORIGINAL.
            $this->appendResponse(
                $assignment,
                (int) $actor->getId(),
                VolunteerResponse::RESPONSE_SUBSTITUTE_PROPOSED,
                VolunteerResponse::CHANNEL_WEB,
                $comment,
                $connection
            );

            $this->notifications->enqueueSwapProposed(
                $swap,
                (int) $occurrence->getId(),
                $this->coordinatorsFor($schedule)
            );

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->logger->info('Volunteer substitute proposed', [
            'swapId' => $swap->getId(),
            'assignmentId' => $assignment->getId(),
            'proposedBy' => $actor->getId(),
            'substitute' => $substitutePersonId,
        ]);

        return $swap;
    }

    /**
     * Approve a swap — **one transaction** (§2.13).
     *
     * The original goes `accepted → substituted`, a NEW row is inserted for the
     * substitute with `source = 'substitute'`, `status = 'accepted'` (the substitute has
     * already agreed — that is what "propose a substitute who has already agreed" means)
     * and `vasg_Replaces_vasg_ID` pointing at the original. A response row is appended to
     * both, and both parties are told the outcome. The original is never edited beyond
     * its status, so the audit trail survives.
     *
     * @return array{swap: VolunteerSwap, originalAssignment: VolunteerAssignment, replacementAssignment: VolunteerAssignment}
     *
     * @throws VolunteerSetupException
     */
    public function approveSwap(VolunteerSwap $swap, User $actor, ?string $comment = null): array
    {
        $assignment = $this->requireSwapAssignment($swap);
        $this->assertCanDecideSwap($swap, $assignment, $actor);

        $occurrence = $this->requireOccurrence($assignment);
        $this->assertOccurrenceAssignable($occurrence);

        $position = $this->requirePosition((int) $assignment->getPositionId());
        $substitutePersonId = (int) $swap->getProposedPersonId();

        // Re-validated at DECISION time, not just at proposal time: a qualification can
        // be revoked in between, and the coordinator must not be able to approve a swap
        // that assign() would refuse.
        $this->assertQualified($substitutePersonId, $position);

        $existing = VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId((int) $assignment->getOccurrenceId())
            ->filterByPositionId((int) $assignment->getPositionId())
            ->filterByPersonId($substitutePersonId)
            ->findOne();
        if ($existing !== null && in_array($existing->getStatus(), self::LIVE_STATUSES, true)) {
            throw VolunteerSetupException::conflict(gettext('That person already holds this position on this occurrence'));
        }

        $connection = Propel::getWriteConnection(VolunteerAssignmentTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $assignment->setStatus(VolunteerAssignment::STATUS_SUBSTITUTED);
            $assignment->save($connection);

            // I1/I8 again: a spent row for the substitute on this very slot is reused,
            // so an approve after an earlier decline by the same person still works.
            $replacement = $existing ?? new VolunteerAssignment();
            $replacement->setOccurrenceId((int) $assignment->getOccurrenceId());
            $replacement->setPositionId((int) $assignment->getPositionId());
            $replacement->setPersonId($substitutePersonId);
            $replacement->setRequirementId($assignment->getRequirementId());
            $replacement->setStatus(VolunteerAssignment::STATUS_ACCEPTED);
            $replacement->setSource(VolunteerAssignment::SOURCE_SUBSTITUTE);
            $replacement->setAssignedDate(DateTimeUtils::getToday());
            $replacement->setAssignedByPersonId((int) $actor->getId());
            $replacement->setRespondedDate(DateTimeUtils::getToday());
            $replacement->setReplacesAssignmentId((int) $assignment->getId());
            $replacement->save($connection);

            $swap->setStatus(VolunteerSwap::STATUS_APPROVED);
            $swap->setDecidedDate(DateTimeUtils::getToday());
            $swap->setDecidedByPersonId((int) $actor->getId());
            if ($comment !== null) {
                $swap->setComment($this->normalizeNote($comment));
            }
            $swap->save($connection);

            $this->appendResponse(
                $assignment,
                (int) $actor->getId(),
                VolunteerResponse::RESPONSE_SUBSTITUTE_APPROVED,
                VolunteerResponse::CHANNEL_COORDINATOR,
                $comment,
                $connection
            );
            $this->appendResponse(
                $replacement,
                (int) $actor->getId(),
                VolunteerResponse::RESPONSE_ACCEPTED,
                VolunteerResponse::CHANNEL_COORDINATOR,
                $comment,
                $connection
            );

            // The original is no longer owed anything; the replacement is.
            $this->notifications->cancelPendingFor((int) $assignment->getId());
            $this->notifications->enqueueAssignment($replacement);
            $this->notifications->enqueueSwapResolved($swap, (int) $occurrence->getId());

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->logger->info('Volunteer substitution approved', [
            'swapId' => $swap->getId(),
            'originalAssignmentId' => $assignment->getId(),
            'replacementAssignmentId' => $replacement->getId(),
            'actor' => $actor->getId(),
        ]);

        return [
            'swap' => $swap,
            'originalAssignment' => $assignment,
            'replacementAssignment' => $replacement,
        ];
    }

    /**
     * Reject a swap: the original stays `accepted` and the proposer is told (§2.13).
     * The coordinator may then cancel and assign someone else, which is the ordinary
     * gap loop and needs no special case.
     *
     * @throws VolunteerSetupException
     */
    public function rejectSwap(VolunteerSwap $swap, User $actor, ?string $comment = null): VolunteerSwap
    {
        $assignment = $this->requireSwapAssignment($swap);
        $this->assertCanDecideSwap($swap, $assignment, $actor);

        $occurrence = $this->requireOccurrence($assignment);

        $connection = Propel::getWriteConnection(VolunteerAssignmentTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $swap->setStatus(VolunteerSwap::STATUS_REJECTED);
            $swap->setDecidedDate(DateTimeUtils::getToday());
            $swap->setDecidedByPersonId((int) $actor->getId());
            if ($comment !== null) {
                $swap->setComment($this->normalizeNote($comment));
            }
            $swap->save($connection);

            $this->appendResponse(
                $assignment,
                (int) $actor->getId(),
                VolunteerResponse::RESPONSE_SUBSTITUTE_REJECTED,
                VolunteerResponse::CHANNEL_COORDINATOR,
                $comment,
                $connection
            );

            $this->notifications->enqueueSwapResolved($swap, (int) $occurrence->getId());

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->logger->info('Volunteer substitution rejected', [
            'swapId' => $swap->getId(),
            'assignmentId' => $assignment->getId(),
            'actor' => $actor->getId(),
        ]);

        return $swap;
    }

    /**
     * Withdraw a proposal. **Proposer only** (§2.13, §3.3.3) — a coordinator approves or
     * rejects, they do not withdraw on someone's behalf. Appends a
     * `substitute_withdrawn` response row and changes nothing else: the original stays
     * exactly as it was.
     *
     * @throws VolunteerSetupException
     */
    public function withdrawSwap(VolunteerSwap $swap, User $actor, ?string $comment = null): VolunteerSwap
    {
        if ((int) $swap->getProposedByPersonId() !== (int) $actor->getId()) {
            throw VolunteerSetupException::forbidden(gettext('Only the volunteer who proposed a substitute may withdraw it'));
        }

        if ($swap->getStatus() !== VolunteerSwap::STATUS_PROPOSED) {
            throw VolunteerSetupException::conflict(gettext('This substitution request has already been decided'))
                ->withExtra(['currentStatus' => $swap->getStatus()]);
        }

        $assignment = $this->requireSwapAssignment($swap);

        $connection = Propel::getWriteConnection(VolunteerAssignmentTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $swap->setStatus(VolunteerSwap::STATUS_WITHDRAWN);
            $swap->setDecidedDate(DateTimeUtils::getToday());
            $swap->setDecidedByPersonId((int) $actor->getId());
            if ($comment !== null) {
                $swap->setComment($this->normalizeNote($comment));
            }
            $swap->save($connection);

            $this->appendResponse(
                $assignment,
                (int) $actor->getId(),
                VolunteerResponse::RESPONSE_SUBSTITUTE_WITHDRAWN,
                VolunteerResponse::CHANNEL_WEB,
                $comment,
                $connection
            );

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->logger->info('Volunteer substitution withdrawn', [
            'swapId' => $swap->getId(),
            'assignmentId' => $assignment->getId(),
            'actor' => $actor->getId(),
        ]);

        return $swap;
    }

    // ── Gaps (§2.11.3) — the single implementation ─────────────────────────

    /**
     * Derive live / gap / open counts for every effective requirement of every
     * occurrence asked about.
     *
     *     live(R) = assignments on (O, R.position) whose status is pending or accepted
     *     gap(R)  = max(0, R.MinCount - live(R))
     *     open(R) = (R.MaxCount ?? R.MinCount) - live(R)      -- self-signup capacity
     *
     * Two queries in total regardless of how many occurrences are asked about — one for
     * the assignments, one (inside `getEffectiveRequirements()`) per occurrence for the
     * requirement merge. Callers that need counts for a LIST hand in every id at once.
     *
     * @param int[] $occurrenceIds
     *
     * @return array<int, array{
     *     requirements: array<int, array{requirementId: ?int, positionId: int, positionName: ?string, minCount: int, maxCount: ?int, liveCount: int, gapCount: int, openCount: int, pendingCount: int, acceptedCount: int, source: string}>,
     *     liveCount: int, gapCount: int, openCount: int, pendingCount: int, requiredCount: int
     * }> keyed by occurrence id
     */
    public function getGaps(array $occurrenceIds): array
    {
        $occurrenceIds = array_values(array_unique(array_map('intval', $occurrenceIds)));
        if ($occurrenceIds === []) {
            return [];
        }

        // occurrence id → position id → status → count
        $counts = [];
        $rows = VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId($occurrenceIds, Criteria::IN)
            ->select(['OccurrenceId', 'PositionId', 'Status'])
            ->find();

        foreach ($rows as $row) {
            $occurrenceId = (int) $row['OccurrenceId'];
            $positionId = (int) $row['PositionId'];
            $status = (string) $row['Status'];
            $counts[$occurrenceId][$positionId][$status] = ($counts[$occurrenceId][$positionId][$status] ?? 0) + 1;
        }

        $result = [];
        foreach ($occurrenceIds as $occurrenceId) {
            $requirements = [];
            $totals = ['liveCount' => 0, 'gapCount' => 0, 'openCount' => 0, 'pendingCount' => 0, 'requiredCount' => 0];

            foreach ($this->schedules->getEffectiveRequirements($occurrenceId) as $positionId => $requirement) {
                $positionId = (int) $positionId;
                $byStatus = $counts[$occurrenceId][$positionId] ?? [];

                $pending = (int) ($byStatus[VolunteerAssignment::STATUS_PENDING] ?? 0);
                $accepted = (int) ($byStatus[VolunteerAssignment::STATUS_ACCEPTED] ?? 0);
                $live = $pending + $accepted;

                $min = (int) $requirement->getMinCount();
                $max = $requirement->getMaxCount() === null ? null : (int) $requirement->getMaxCount();
                $capacity = $max ?? $min;

                $requirements[$positionId] = [
                    'requirementId' => $requirement->getId() === null ? null : (int) $requirement->getId(),
                    'positionId' => $positionId,
                    'positionName' => $this->positionName($positionId),
                    'minCount' => $min,
                    'maxCount' => $max,
                    'liveCount' => $live,
                    'gapCount' => max(0, $min - $live),
                    'openCount' => max(0, $capacity - $live),
                    'pendingCount' => $pending,
                    'acceptedCount' => $accepted,
                    // Which level the row came from, so a screen can say "overridden
                    // for this week" without re-deriving the merge (§2.10).
                    'source' => $requirement->getOccurrenceId() === null ? 'schedule' : 'occurrence',
                ];

                $totals['liveCount'] += $live;
                $totals['gapCount'] += max(0, $min - $live);
                $totals['openCount'] += max(0, $capacity - $live);
                $totals['pendingCount'] += $pending;
                $totals['requiredCount'] += $min;
            }

            $result[$occurrenceId] = ['requirements' => $requirements] + $totals;
        }

        return $result;
    }

    /**
     * The flat gap list `GET /gaps` and the coordinator dashboard want: one entry per
     * (occurrence, position) that is genuinely short.
     *
     * @param int[] $occurrenceIds
     *
     * @return array<int, array{occurrenceId: int, positionId: int, positionName: ?string, minCount: int, liveCount: int, gapCount: int}>
     */
    public function getOpenGaps(array $occurrenceIds): array
    {
        $gaps = [];
        foreach ($this->getGaps($occurrenceIds) as $occurrenceId => $summary) {
            foreach ($summary['requirements'] as $requirement) {
                if ($requirement['gapCount'] <= 0) {
                    continue;
                }
                $gaps[] = [
                    'occurrenceId' => (int) $occurrenceId,
                    'positionId' => $requirement['positionId'],
                    'positionName' => $requirement['positionName'],
                    'minCount' => $requirement['minCount'],
                    'liveCount' => $requirement['liveCount'],
                    'gapCount' => $requirement['gapCount'],
                ];
            }
        }

        return $gaps;
    }

    /**
     * Every assignment row of one occurrence, grouped by position id — what the staffing
     * view renders under each requirement card.
     *
     * @return array<int, VolunteerAssignment[]>
     */
    public function getAssignmentsByPosition(int $occurrenceId): array
    {
        $grouped = [];
        $rows = VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId($occurrenceId)
            ->orderByPositionId()
            ->orderById()
            ->find();

        foreach ($rows as $assignment) {
            $grouped[(int) $assignment->getPositionId()][] = $assignment;
        }

        return $grouped;
    }

    // ── The eligible picker (§3.3.2, §2.17 rotation) ───────────────────────

    /**
     * Who may be assigned to this position on this occurrence.
     *
     * The set is **qualified ∩ (pool ∪ qualified-but-flagged)**: everyone actively
     * qualified is offered, and whether they are in a pool group is reported as
     * `inPool` rather than used as a filter. §5.5 is explicit that an out-of-pool
     * qualified person appears under a "Not in the pool" divider and is assignable with
     * the override — hiding them would make the override unreachable from the UI.
     *
     * Ordering is `lastServedDate ASC NULLS FIRST` — **the rotation** (§2.17). There is
     * no rotation table and no algorithm: the person who has served least recently is
     * simply offered first.
     *
     * `lastServedDate` is the latest occurrence date on which the person holds or held a
     * live-or-completed assignment **anywhere in this ministry**, not just on this
     * position. The design says "last served date" without narrowing it; ministry-wide
     * is what UC1's "assignments rotate" means in practice — someone who ran the milk
     * station last Sunday should not be top of the espresso list this Sunday. Future
     * dates count too, so scheduling four weeks ahead in one sitting rotates rather than
     * offering the same person every week.
     *
     * `conflictPositionId` carries I7/D16: the person already holds ANOTHER position on
     * this occurrence. It is an annotation, never a filter and never an error — the UI
     * warns and proceeds (§5.5).
     *
     * @return array<int, array{personId: int, displayName: string, inPool: bool, lastServedDate: ?string, conflictPositionId: ?int, conflictPositionName: ?string}>
     */
    public function getEligiblePeople(VolunteerOccurrence $occurrence, VolunteerPosition $position, ?string $query = null): array
    {
        $schedule = $this->schedules->requireSchedule($occurrence);

        $qualifiedIds = $this->setup->getQualifiedPersonIds((int) $position->getId());
        if ($qualifiedIds === []) {
            return [];
        }

        $poolIds = array_flip($this->setup->getPoolPersonIds(
            (int) $schedule->getMinistryId(),
            $schedule->getTeamId() === null ? null : (int) $schedule->getTeamId()
        ));

        $lastServed = $this->lastServedDates($qualifiedIds, (int) $schedule->getMinistryId());
        $conflicts = $this->conflictingPositions((int) $occurrence->getId(), $qualifiedIds, (int) $position->getId());

        $needle = $query === null ? '' : mb_strtolower(trim($query));

        $people = [];
        foreach (PersonQuery::create()->filterById($qualifiedIds, Criteria::IN)->find() as $person) {
            /** @var Person $person */
            $personId = (int) $person->getId();
            $displayName = (string) $person->getFullName();

            if ($needle !== '' && !str_contains(mb_strtolower($displayName), $needle)) {
                continue;
            }

            $conflictPositionId = $conflicts[$personId] ?? null;

            $people[] = [
                'personId' => $personId,
                'displayName' => $displayName,
                'inPool' => isset($poolIds[$personId]),
                'lastServedDate' => $lastServed[$personId] ?? null,
                'conflictPositionId' => $conflictPositionId,
                'conflictPositionName' => $conflictPositionId === null ? null : $this->positionName($conflictPositionId),
            ];
        }

        // NULLS FIRST, then oldest date, then name so the order is stable when several
        // people have never served — which, on a brand-new ministry, is everyone.
        usort($people, static function (array $a, array $b): int {
            if ($a['lastServedDate'] === null && $b['lastServedDate'] === null) {
                return strcasecmp($a['displayName'], $b['displayName']);
            }
            if ($a['lastServedDate'] === null) {
                return -1;
            }
            if ($b['lastServedDate'] === null) {
                return 1;
            }

            return $a['lastServedDate'] <=> $b['lastServedDate']
                ?: strcasecmp($a['displayName'], $b['displayName']);
        });

        return $people;
    }

    // ── The member surface (§3.3.3, #9712) ─────────────────────────────────

    /**
     * "What is open that I could actually sign up for?" — `GET /me/opportunities`.
     *
     * The list is derived, never stored, and derived from the **same** `getGaps()`
     * every other surface uses (§2.11.3): an opportunity is one effective requirement
     * of one occurrence with `openCount > 0`. Everything else here is a filter that
     * makes the list honest, i.e. every row it returns is a row `selfSignup()` would
     * accept:
     *
     *   - the position is one I hold an **active** qualification for (I2), and is
     *     itself active — so the list is server-side eligibility, never the client's
     *     idea of it;
     *   - I am in a pool group of the owning ministry or team (I3) — `selfSignup()`
     *     passes no out-of-pool override, so offering a row I am not in the pool for
     *     would be offering a guaranteed `409`;
     *   - the occurrence is `scheduled` and not already over (I5);
     *   - I do not already hold that position on that occurrence (I1).
     *
     * Holding a *different* position on the same occurrence is **not** a filter
     * (D16/I7): the row stays, annotated with `alreadyServing`, and §5.6's warning is
     * what the screen does with it. Hiding it would quietly make double-duty
     * impossible from the member side, which is the opposite of the product decision.
     *
     * @return array<int, array{occurrenceId: int, positionId: int, positionName: ?string, ministryName: ?string, teamName: ?string, occurrenceDate: ?string, start: ?string, end: ?string, openCount: int, minCount: int, liveCount: int, alreadyServing: bool, alreadyServingPositionNames: string[]}>
     */
    public function listOpportunitiesForPerson(
        int $personId,
        \DateTimeInterface $from,
        \DateTimeInterface $to
    ): array {
        $positions = $this->activePositionsFor($personId);
        if ($positions === []) {
            return [];
        }

        $ministryIds = array_values(array_unique(array_map(
            static fn (VolunteerPosition $p): int => (int) $p->getMinistryId(),
            $positions
        )));

        $schedules = [];
        foreach (VolunteerScheduleQuery::create()->filterByMinistryId($ministryIds, Criteria::IN)->find() as $schedule) {
            $schedules[(int) $schedule->getId()] = $schedule;
        }
        if ($schedules === []) {
            return [];
        }

        $occurrences = VolunteerOccurrenceQuery::create()
            ->filterByScheduleId(array_keys($schedules), Criteria::IN)
            ->filterByOccurrenceDate($from->format('Y-m-d'), Criteria::GREATER_EQUAL)
            ->filterByOccurrenceDate($to->format('Y-m-d'), Criteria::LESS_EQUAL)
            ->filterByStatus(VolunteerOccurrence::STATUS_SCHEDULED)
            ->orderByOccurrenceDate()
            ->orderById()
            ->limit(VolunteerScheduleService::MAX_OCCURRENCE_LIST)
            ->find();

        $occurrenceRows = iterator_to_array($occurrences, false);
        if ($occurrenceRows === []) {
            return [];
        }

        $occurrenceIds = array_map(static fn (VolunteerOccurrence $o): int => (int) $o->getId(), $occurrenceRows);
        $gaps = $this->getGaps($occurrenceIds);

        // My live rows on every occurrence in the window, in one query: the I1 filter
        // and the D16 annotation both come out of it.
        $mine = [];
        foreach (
            VolunteerAssignmentQuery::create()
                ->filterByPersonId($personId)
                ->filterByOccurrenceId($occurrenceIds, Criteria::IN)
                ->filterByStatus(self::LIVE_STATUSES, Criteria::IN)
                ->find() as $assignment
        ) {
            $mine[(int) $assignment->getOccurrenceId()][(int) $assignment->getPositionId()] = true;
        }

        $now = DateTimeUtils::getToday();
        $poolMemo = [];
        $opportunities = [];

        foreach ($occurrenceRows as $occurrence) {
            $occurrenceId = (int) $occurrence->getId();
            $schedule = $schedules[(int) $occurrence->getScheduleId()] ?? null;
            if ($schedule === null) {
                continue;
            }

            // I5, the half a date filter cannot express: today's occurrence may
            // already be over.
            $window = $this->schedules->resolveOccurrenceWindow($occurrence);
            if (($window['end'] ?? null) !== null && $window['end'] < $now) {
                continue;
            }

            // I3, memoised per (ministry, team) so a 500-occurrence window over one
            // schedule asks the pool question once.
            $poolKey = (int) $schedule->getMinistryId() . ':' . ($schedule->getTeamId() ?? 0);
            if (!array_key_exists($poolKey, $poolMemo)) {
                $poolMemo[$poolKey] = array_flip($this->setup->getPoolPersonIds(
                    (int) $schedule->getMinistryId(),
                    $schedule->getTeamId() === null ? null : (int) $schedule->getTeamId()
                ));
            }
            if (!isset($poolMemo[$poolKey][$personId])) {
                continue;
            }

            $held = $mine[$occurrenceId] ?? [];

            foreach ($gaps[$occurrenceId]['requirements'] ?? [] as $positionId => $requirement) {
                $positionId = (int) $positionId;

                if (!isset($positions[$positionId]) || $requirement['openCount'] <= 0 || isset($held[$positionId])) {
                    continue;
                }

                // I4: a ministry-wide position is offered by every team's schedule, a
                // team-scoped one only by its own team's.
                $positionTeamId = $positions[$positionId]->getTeamId();
                $scheduleTeamId = $schedule->getTeamId();
                if (
                    $positionTeamId !== null && $scheduleTeamId !== null
                    && (int) $positionTeamId !== (int) $scheduleTeamId
                ) {
                    continue;
                }

                $alsoHere = array_values(array_map(
                    fn (int $otherPositionId): string => (string) $this->positionName($otherPositionId),
                    array_keys($held)
                ));

                $opportunities[] = [
                    'occurrenceId' => $occurrenceId,
                    'positionId' => $positionId,
                    'positionName' => $requirement['positionName'],
                    'ministryName' => $this->ministryName((int) $schedule->getMinistryId()),
                    'teamName' => $scheduleTeamId === null ? null : $this->teamName((int) $scheduleTeamId),
                    'occurrenceDate' => $occurrence->getOccurrenceDate('Y-m-d'),
                    'start' => $window['start'] === null ? null : $window['start']->format('Y-m-d H:i:s'),
                    'end' => $window['end'] === null ? null : $window['end']->format('Y-m-d H:i:s'),
                    'openCount' => (int) $requirement['openCount'],
                    'minCount' => (int) $requirement['minCount'],
                    'liveCount' => (int) $requirement['liveCount'],
                    // D16/I7 — an annotation the card warns on, never a filter.
                    'alreadyServing' => $alsoHere !== [],
                    'alreadyServingPositionNames' => $alsoHere,
                ];
            }
        }

        // Soonest first: S6 is "what needs me next", not a catalogue.
        usort($opportunities, static fn (array $a, array $b): int => [$a['start'] ?? '', $a['positionName'] ?? '']
            <=> [$b['start'] ?? '', $b['positionName'] ?? '']);

        return $opportunities;
    }

    /**
     * Who this volunteer may offer as their substitute — the S5 picker (§5.6, CR1).
     *
     * A thin, deliberately *narrower* view of `getEligiblePeople()`: the same
     * qualified-and-annotated list, minus the volunteer themselves and minus anyone
     * who already holds this position on this occurrence. Those are exactly the two
     * cases `proposeSubstitute()` refuses, so the picker cannot offer a name the
     * server will then reject — which is the whole reason it is a separate endpoint
     * rather than the coordinator's `/eligible`.
     *
     * Out-of-pool qualified people are kept (`inPool` says which): `proposeSubstitute()`
     * does not apply I3, so they genuinely are proposable.
     *
     * @return array<int, array{personId: int, displayName: string, inPool: bool, lastServedDate: ?string, conflictPositionId: ?int, conflictPositionName: ?string}>
     */
    public function getSubstituteCandidates(VolunteerAssignment $assignment, ?string $query = null): array
    {
        $occurrence = $this->requireOccurrence($assignment);
        $position = $this->requirePosition((int) $assignment->getPositionId());

        $taken = [(int) $assignment->getPersonId() => true];
        foreach (
            VolunteerAssignmentQuery::create()
                ->filterByOccurrenceId((int) $occurrence->getId())
                ->filterByPositionId((int) $position->getId())
                ->filterByStatus(self::LIVE_STATUSES, Criteria::IN)
                ->select(['PersonId'])
                ->find() as $personId
        ) {
            $taken[(int) $personId] = true;
        }

        return array_values(array_filter(
            $this->getEligiblePeople($occurrence, $position, $query),
            static fn (array $person): bool => !isset($taken[$person['personId']])
        ));
    }

    /**
     * Every ACTIVE position this person holds an ACTIVE qualification for, keyed by id.
     *
     * @return array<int, VolunteerPosition>
     */
    private function activePositionsFor(int $personId): array
    {
        $positionIds = [];
        foreach ($this->setup->listQualificationsForPerson($personId) as $qualification) {
            if ($qualification->getActive()) {
                $positionIds[(int) $qualification->getPositionId()] = true;
            }
        }
        if ($positionIds === []) {
            return [];
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

        return $positions;
    }

    private function ministryName(int $ministryId): ?string
    {
        $ministry = VolunteerMinistryQuery::create()->findPk($ministryId);

        return $ministry === null ? null : (string) $ministry->getName();
    }

    private function teamName(int $teamId): ?string
    {
        $team = VolunteerTeamQuery::create()->findPk($teamId);

        return $team === null ? null : (string) $team->getName();
    }

    // ── Completion (§2.11.1) ───────────────────────────────────────────────

    /**
     * Move `pending`/`accepted` assignments to `completed` once their occurrence has
     * ended. Called from the timer job (#9710 wires it); safe to run repeatedly.
     *
     * "Never blocks on attendance" (§2.11.1): when the occurrence is linked and the
     * person has an `event_attend` check-in, completion is recorded knowing that;
     * otherwise it is recorded anyway. V2 writes nothing to `event_attend` (E10).
     *
     * @return int rows completed
     */
    public function markCompleted(\DateTimeInterface $upTo): int
    {
        $completed = 0;

        $candidates = VolunteerAssignmentQuery::create()
            ->filterByStatus(self::LIVE_STATUSES, Criteria::IN)
            ->find();

        foreach ($candidates as $assignment) {
            $occurrence = VolunteerOccurrenceQuery::create()->findPk((int) $assignment->getOccurrenceId());
            if ($occurrence === null || $occurrence->getStatus() === VolunteerOccurrence::STATUS_CANCELLED) {
                continue;
            }

            $end = $this->occurrenceEnd($occurrence);
            if ($end === null || $end > $upTo) {
                continue;
            }

            $assignment->setStatus(VolunteerAssignment::STATUS_COMPLETED);
            $assignment->save();
            $this->notifications->cancelPendingFor((int) $assignment->getId());
            $completed++;
        }

        if ($completed > 0) {
            $this->logger->info('Volunteer assignments completed', [
                'count' => $completed,
                'upTo' => $upTo->format('Y-m-d H:i:s'),
            ]);
        }

        return $completed;
    }

    /**
     * Read-only attendance beside the roster (UC4, E10). Null for an unlinked
     * occurrence — there is no event to have checked in to.
     *
     * @param int[] $personIds
     *
     * @return array<int, string> person id → checked_in | checked_out | not_checked_in
     */
    public function getAttendance(VolunteerOccurrence $occurrence, array $personIds): array
    {
        $eventId = $occurrence->getEventId();
        if ($eventId === null || $personIds === []) {
            return [];
        }

        $attendance = [];
        foreach ($personIds as $personId) {
            $attendance[(int) $personId] = 'not_checked_in';
        }

        $rows = EventAttendQuery::create()
            ->filterByEventId((int) $eventId)
            ->filterByPersonId($personIds, Criteria::IN)
            ->find();

        foreach ($rows as $row) {
            $personId = (int) $row->getPersonId();
            if ($row->getCheckoutDate() !== null) {
                $attendance[$personId] = 'checked_out';
            } elseif ($row->getCheckinDate() !== null) {
                $attendance[$personId] = 'checked_in';
            }
        }

        return $attendance;
    }

    // ── Reads used by the routes ───────────────────────────────────────────

    /**
     * The append-only response history of one assignment, oldest first — what
     * `GET /assignments/{id}` returns so idempotency can be asserted (§6.6).
     *
     * @return VolunteerResponse[]
     */
    public function getResponses(int $assignmentId): array
    {
        return iterator_to_array(
            VolunteerResponseQuery::create()
                ->filterByAssignmentId($assignmentId)
                ->orderByResponseDate()
                ->orderById()
                ->find(),
            false
        );
    }

    /**
     * @return VolunteerSwap[]
     */
    public function getSwapsForAssignment(int $assignmentId): array
    {
        return iterator_to_array(
            VolunteerSwapQuery::create()
                ->filterByAssignmentId($assignmentId)
                ->orderById()
                ->find(),
            false
        );
    }

    /**
     * The swap queue, scoped to occurrences the caller may manage.
     *
     * Scoping happens in the QUERY (§4.4): the occurrence ids the caller can see are
     * resolved first and an empty allow-list short-circuits, rather than filtering
     * hydrated rows in PHP.
     *
     * @param int[] $occurrenceIds
     *
     * @return VolunteerSwap[]
     */
    public function listSwaps(array $occurrenceIds, ?string $status = null): array
    {
        if ($occurrenceIds === []) {
            return [];
        }

        $assignmentIds = VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId($occurrenceIds, Criteria::IN)
            ->select(['Id'])
            ->find()
            ->toArray();

        if ($assignmentIds === []) {
            return [];
        }

        $query = VolunteerSwapQuery::create()
            ->filterByAssignmentId(array_map('intval', $assignmentIds), Criteria::IN);

        if ($status !== null && $status !== '') {
            $query->filterByStatus($status);
        }

        return iterator_to_array($query->orderById(Criteria::DESC)->find(), false);
    }

    /**
     * A person's own assignments, newest-first by occurrence date — `GET /me/assignments`.
     *
     * @return VolunteerAssignment[]
     */
    public function listAssignmentsForPerson(
        int $personId,
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        bool $includePast = false
    ): array {
        $occurrenceQuery = VolunteerOccurrenceQuery::create();

        if ($from !== null) {
            $occurrenceQuery->filterByOccurrenceDate($from->format('Y-m-d'), Criteria::GREATER_EQUAL);
        } elseif (!$includePast) {
            $occurrenceQuery->filterByOccurrenceDate(
                DateTimeUtils::getTodayDate(),
                Criteria::GREATER_EQUAL
            );
        }
        if ($to !== null) {
            $occurrenceQuery->filterByOccurrenceDate($to->format('Y-m-d'), Criteria::LESS_EQUAL);
        }

        $occurrenceIds = array_map('intval', $occurrenceQuery->select(['Id'])->find()->toArray());
        if ($occurrenceIds === []) {
            return [];
        }

        return iterator_to_array(
            VolunteerAssignmentQuery::create()
                ->filterByPersonId($personId)
                ->filterByOccurrenceId($occurrenceIds, Criteria::IN)
                ->orderById()
                ->find(),
            false
        );
    }

    /** Can this person still answer for this assignment? Drives the member UI's buttons. */
    public function canRespond(VolunteerAssignment $assignment): bool
    {
        if (!in_array($assignment->getStatus(), self::LIVE_STATUSES, true)) {
            return false;
        }

        $occurrence = VolunteerOccurrenceQuery::create()->findPk((int) $assignment->getOccurrenceId());
        if ($occurrence === null || $occurrence->getStatus() === VolunteerOccurrence::STATUS_CANCELLED) {
            return false;
        }

        $end = $this->occurrenceEnd($occurrence);

        return $end === null || $end >= DateTimeUtils::getToday();
    }

    public function canProposeSubstitute(VolunteerAssignment $assignment): bool
    {
        if (!$this->canRespond($assignment)) {
            return false;
        }

        return VolunteerSwapQuery::create()
            ->filterByAssignmentId((int) $assignment->getId())
            ->filterByStatus(VolunteerSwap::STATUS_PROPOSED)
            ->count() === 0;
    }

    // ── Internals ──────────────────────────────────────────────────────────

    /**
     * Append one row to the append-only history (§2.12). Never updates, never deletes.
     */
    private function appendResponse(
        VolunteerAssignment $assignment,
        int $personId,
        string $response,
        string $channel,
        ?string $comment,
        mixed $connection = null
    ): VolunteerResponse {
        $row = new VolunteerResponse();
        $row->setAssignmentId((int) $assignment->getId());
        $row->setPersonId($personId);
        $row->setResponse($response);
        $row->setResponseDate(DateTimeUtils::getToday());
        $row->setChannel($channel);
        $row->setComment($this->normalizeNote($comment));
        $row->save($connection);

        return $row;
    }

    /**
     * A slot has just opened. Alert the coordinators about the gap, and — only when the
     * volunteer declined it themselves — about the decline as well (§3.6).
     */
    private function notifyGapOpened(VolunteerAssignment $assignment, bool $includeDeclineAlert): void
    {
        $occurrence = VolunteerOccurrenceQuery::create()->findPk((int) $assignment->getOccurrenceId());
        if ($occurrence === null) {
            return;
        }

        $schedule = VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());
        if ($schedule === null) {
            return;
        }

        $coordinators = $this->coordinatorsFor($schedule);
        if ($coordinators === []) {
            return;
        }

        if ($includeDeclineAlert) {
            $this->notifications->enqueueDeclineAlert($assignment, $coordinators);
        }

        // Only alert about a gap that genuinely exists now — a position with a spare
        // volunteer already on it is not short just because one person dropped out.
        $summary = $this->getGaps([(int) $occurrence->getId()]);
        $gapCount = $summary[(int) $occurrence->getId()]['gapCount'] ?? 0;
        if ($gapCount > 0) {
            $this->notifications->enqueueGapAlert((int) $occurrence->getId(), $coordinators);
        }
    }

    /** @return int[] */
    private function coordinatorsFor(VolunteerSchedule $schedule): array
    {
        return $this->authz->getCoordinatorPersonIds(
            (int) $schedule->getMinistryId(),
            $schedule->getTeamId() === null ? null : (int) $schedule->getTeamId()
        );
    }

    /**
     * §2.11.1's transition table, and nothing else. An illegal move is a `409` carrying
     * the CURRENT status as a field, so a client can re-render the row without parsing
     * the sentence.
     *
     * @throws VolunteerSetupException
     */
    private function assertTransitionAllowed(VolunteerAssignment $assignment, string $to): void
    {
        $from = (string) $assignment->getStatus();

        $legal = match ($from) {
            VolunteerAssignment::STATUS_PENDING => [
                VolunteerAssignment::STATUS_ACCEPTED,
                VolunteerAssignment::STATUS_DECLINED,
                VolunteerAssignment::STATUS_CANCELLED,
                VolunteerAssignment::STATUS_COMPLETED,
            ],
            VolunteerAssignment::STATUS_ACCEPTED => [
                VolunteerAssignment::STATUS_DECLINED,
                VolunteerAssignment::STATUS_CANCELLED,
                VolunteerAssignment::STATUS_SUBSTITUTED,
                VolunteerAssignment::STATUS_COMPLETED,
            ],
            // declined, cancelled, substituted and completed are terminal. A person can
            // be put back on the roster, but that is assign()'s I8 row reuse, not a
            // transition — and it resets the row rather than editing its state.
            default => [],
        };

        if (!in_array($to, $legal, true)) {
            throw VolunteerSetupException::conflict(sprintf(
                /* translators: %1$s is the current assignment status, %2$s the requested one */
                gettext('This assignment is %1$s and cannot be changed to %2$s'),
                $from,
                $to
            ))->withExtra(['currentStatus' => $from, 'requestedStatus' => $to]);
        }
    }

    /**
     * I6: once an occurrence's end has passed the only write allowed is completion.
     *
     * @throws VolunteerSetupException
     */
    private function assertNotHistorical(VolunteerAssignment $assignment): void
    {
        $occurrence = VolunteerOccurrenceQuery::create()->findPk((int) $assignment->getOccurrenceId());
        if ($occurrence === null) {
            return;
        }

        $end = $this->occurrenceEnd($occurrence);
        if ($end !== null && $end < DateTimeUtils::getToday()) {
            throw VolunteerSetupException::conflict(gettext('This occurrence has already happened and its assignments can no longer be changed'))
                ->withExtra(['currentStatus' => $assignment->getStatus()]);
        }
    }

    /**
     * I5: a cancelled occurrence, or one that is already over, takes no new assignments.
     *
     * @throws VolunteerSetupException
     */
    private function assertOccurrenceAssignable(VolunteerOccurrence $occurrence): void
    {
        if ($occurrence->getStatus() === VolunteerOccurrence::STATUS_CANCELLED) {
            throw VolunteerSetupException::conflict(gettext('This occurrence has been cancelled'));
        }

        $end = $this->occurrenceEnd($occurrence);
        if ($end !== null && $end < DateTimeUtils::getToday()) {
            throw VolunteerSetupException::conflict(gettext('This occurrence has already happened'));
        }
    }

    /**
     * I4: the position must belong to the occurrence's schedule's ministry, and to its
     * team when the schedule names one and the position is team-scoped. A ministry-wide
     * position (`vpos_vtem_ID` null) is usable by every team's schedule.
     *
     * @throws VolunteerSetupException
     */
    private function assertPositionBelongsToSchedule(VolunteerPosition $position, VolunteerSchedule $schedule): void
    {
        if ((int) $position->getMinistryId() !== (int) $schedule->getMinistryId()) {
            throw VolunteerSetupException::invalid(gettext('That position belongs to a different ministry'));
        }

        $positionTeamId = $position->getTeamId() === null ? null : (int) $position->getTeamId();
        $scheduleTeamId = $schedule->getTeamId() === null ? null : (int) $schedule->getTeamId();

        if ($positionTeamId !== null && $scheduleTeamId !== null && $positionTeamId !== $scheduleTeamId) {
            throw VolunteerSetupException::invalid(gettext('That position belongs to a different team'));
        }
    }

    /**
     * I2: an ACTIVE qualification at assign time. A revoked qualification blocks a NEW
     * assignment and leaves existing rows untouched (§2.7) — history stays valid because
     * the assignment references the person and the position, never the qualification row.
     *
     * @throws VolunteerSetupException
     */
    private function assertQualified(int $personId, VolunteerPosition $position): void
    {
        $qualification = $this->setup->findQualification($personId, (int) $position->getId());

        if ($qualification === null || !$qualification->getActive()) {
            throw VolunteerSetupException::forbidden(gettext('That person is not qualified for this position'));
        }
    }

    /** @throws VolunteerSetupException */
    private function assertPersonExists(int $personId): void
    {
        if (PersonQuery::create()->findPk($personId) === null) {
            throw VolunteerSetupException::notFound(gettext('Person not found'));
        }
    }

    /**
     * The self-signup capacity check (§3.3.3): `409` when the requirement is already at
     * `MaxCount`. Re-validated server-side at signup time whatever the UI offered.
     *
     * @throws VolunteerSetupException
     */
    private function assertCapacityAvailable(VolunteerOccurrence $occurrence, VolunteerPosition $position): void
    {
        $summary = $this->getGaps([(int) $occurrence->getId()]);
        $requirement = $summary[(int) $occurrence->getId()]['requirements'][(int) $position->getId()] ?? null;

        if ($requirement === null) {
            throw VolunteerSetupException::conflict(gettext('That position is not being staffed on this occurrence'));
        }

        if ($requirement['openCount'] <= 0) {
            throw VolunteerSetupException::conflict(gettext('That position is already fully staffed'));
        }
    }

    private function isInPool(int $personId, VolunteerSchedule $schedule): bool
    {
        $poolIds = $this->setup->getPoolPersonIds(
            (int) $schedule->getMinistryId(),
            $schedule->getTeamId() === null ? null : (int) $schedule->getTeamId()
        );

        return in_array($personId, $poolIds, true);
    }

    /**
     * Which effective requirement this assignment fills. The caller may name one, but it
     * must be for this position and this occurrence; otherwise the merge decides, which
     * is what makes `requirementId` optional on the API.
     */
    private function resolveRequirementId(VolunteerOccurrence $occurrence, VolunteerPosition $position, ?int $requested): ?int
    {
        $effective = $this->schedules->getEffectiveRequirements((int) $occurrence->getId());
        /** @var ?VolunteerRequirement $requirement */
        $requirement = $effective[(int) $position->getId()] ?? null;

        if ($requested !== null && $requirement !== null && (int) $requirement->getId() === $requested) {
            return $requested;
        }

        return $requirement === null ? null : (int) $requirement->getId();
    }

    /**
     * The last (or next) date each of these people is on the ministry's roster.
     *
     * One query, not one per person: the picker is opened with 15–200 qualified people
     * behind it and must not fan out.
     *
     * @param int[] $personIds
     *
     * @return array<int, string> person id → `Y-m-d`
     */
    private function lastServedDates(array $personIds, int $ministryId): array
    {
        if ($personIds === []) {
            return [];
        }

        $occurrenceIds = $this->ministryOccurrenceIds($ministryId);
        if ($occurrenceIds === []) {
            return [];
        }

        $dates = [];
        $occurrenceDates = [];
        foreach (
            VolunteerOccurrenceQuery::create()
                ->filterById($occurrenceIds, Criteria::IN)
                ->select(['Id', 'OccurrenceDate'])
                ->find() as $row
        ) {
            $raw = $row['OccurrenceDate'];
            $occurrenceDates[(int) $row['Id']] = $raw instanceof \DateTimeInterface
                ? $raw->format('Y-m-d')
                : substr((string) $raw, 0, 10);
        }

        $rows = VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId($occurrenceIds, Criteria::IN)
            ->filterByPersonId($personIds, Criteria::IN)
            ->filterByStatus(self::SERVED_STATUSES, Criteria::IN)
            ->select(['PersonId', 'OccurrenceId'])
            ->find();

        foreach ($rows as $row) {
            $personId = (int) $row['PersonId'];
            $date = $occurrenceDates[(int) $row['OccurrenceId']] ?? null;
            if ($date === null) {
                continue;
            }
            if (!isset($dates[$personId]) || $date > $dates[$personId]) {
                $dates[$personId] = $date;
            }
        }

        return $dates;
    }

    /**
     * I7/D16: which OTHER position each of these people already holds on this
     * occurrence. Reported so the UI can warn; never used to filter or block.
     *
     * @param int[] $personIds
     *
     * @return array<int, int> person id → the other position id
     */
    private function conflictingPositions(int $occurrenceId, array $personIds, int $excludePositionId): array
    {
        if ($personIds === []) {
            return [];
        }

        $conflicts = [];
        $rows = VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId($occurrenceId)
            ->filterByPersonId($personIds, Criteria::IN)
            ->filterByStatus(self::LIVE_STATUSES, Criteria::IN)
            ->select(['PersonId', 'PositionId'])
            ->find();

        foreach ($rows as $row) {
            $positionId = (int) $row['PositionId'];
            if ($positionId === $excludePositionId) {
                continue;
            }
            $conflicts[(int) $row['PersonId']] ??= $positionId;
        }

        return $conflicts;
    }

    /** @return int[] */
    private function ministryOccurrenceIds(int $ministryId): array
    {
        $scheduleIds = array_map('intval', VolunteerScheduleQuery::create()
            ->filterByMinistryId($ministryId)
            ->select(['Id'])
            ->find()
            ->toArray());
        if ($scheduleIds === []) {
            return [];
        }

        return array_map('intval', VolunteerOccurrenceQuery::create()
            ->filterByScheduleId($scheduleIds, Criteria::IN)
            ->select(['Id'])
            ->find()
            ->toArray());
    }

    /**
     * The occurrence's real end, through the ONE method allowed to decide it
     * (`VolunteerScheduleService::resolveOccurrenceWindow()`, §3.4). Null when the
     * occurrence has no end at all — a standalone all-day row, or one whose linked event
     * was deleted — in which case nothing here treats it as past.
     */
    private function occurrenceEnd(VolunteerOccurrence $occurrence): ?\DateTimeInterface
    {
        $window = $this->schedules->resolveOccurrenceWindow($occurrence);

        return $window['end'] ?? null;
    }

    private function positionName(int $positionId): ?string
    {
        $position = VolunteerPositionQuery::create()->findPk($positionId);

        return $position === null ? null : (string) $position->getName();
    }

    /** @throws VolunteerSetupException */
    private function requireOccurrence(VolunteerAssignment $assignment): VolunteerOccurrence
    {
        $occurrence = VolunteerOccurrenceQuery::create()->findPk((int) $assignment->getOccurrenceId());
        if ($occurrence === null) {
            throw VolunteerSetupException::notFound(gettext('Occurrence not found'));
        }

        return $occurrence;
    }

    /** @throws VolunteerSetupException */
    private function requirePosition(int $positionId): VolunteerPosition
    {
        $position = VolunteerPositionQuery::create()->findPk($positionId);
        if ($position === null) {
            throw VolunteerSetupException::notFound(gettext('Position not found'));
        }

        return $position;
    }

    /** @throws VolunteerSetupException */
    private function requireSwapAssignment(VolunteerSwap $swap): VolunteerAssignment
    {
        $assignment = VolunteerAssignmentQuery::create()->findPk((int) $swap->getAssignmentId());
        if ($assignment === null) {
            throw VolunteerSetupException::notFound(gettext('Assignment not found'));
        }

        return $assignment;
    }

    /** @throws VolunteerSetupException */
    private function assertCanDecideSwap(VolunteerSwap $swap, VolunteerAssignment $assignment, User $actor): void
    {
        if (!$this->authz->canManageAssignment($actor, $assignment)) {
            throw VolunteerSetupException::forbidden(gettext('Not authorized for this substitution request'));
        }

        if ($swap->getStatus() !== VolunteerSwap::STATUS_PROPOSED) {
            throw VolunteerSetupException::conflict(gettext('This substitution request has already been decided'))
                ->withExtra(['currentStatus' => $swap->getStatus()]);
        }
    }

    private function normalizeNote(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 255);
    }
}
