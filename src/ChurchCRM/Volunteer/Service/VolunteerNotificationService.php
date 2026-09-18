<?php

namespace ChurchCRM\Volunteer\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\BaseEmail;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\VolunteerAssignment;
use ChurchCRM\model\ChurchCRM\VolunteerAssignmentQuery;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerNotification;
use ChurchCRM\model\ChurchCRM\VolunteerNotificationQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSwap;
use ChurchCRM\model\ChurchCRM\VolunteerSwapQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Volunteer\Email\VolunteerAssignmentEmail;
use ChurchCRM\Volunteer\Email\VolunteerDeclineAlertEmail;
use ChurchCRM\Volunteer\Email\VolunteerEmailContext;
use ChurchCRM\Volunteer\Email\VolunteerGapAlertEmail;
use ChurchCRM\Volunteer\Email\VolunteerHelpOfferEmail;
use ChurchCRM\Volunteer\Email\VolunteerReminderEmail;
use ChurchCRM\Volunteer\Email\VolunteerSignupConfirmEmail;
use ChurchCRM\Volunteer\Email\VolunteerSwapProposedEmail;
use ChurchCRM\Volunteer\Email\VolunteerSwapResolvedEmail;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Log\LoggerInterface;

/**
 * The Volunteer v2 notification outbox — enqueue AND delivery (#9709 + #9710;
 * design §2.14, §3.6).
 *
 * D15: there is no scheduler in ChurchCRM (F9), so V2 decouples "decide a message is
 * due" from "deliver it". #9709 built the first half; #9710 added the second —
 * `scheduleReminders()`, `drainOutbox()`, the seven `BaseEmail` subclasses under
 * `ChurchCRM\Volunteer\Email` and the send-time `Reply-To` resolution. The split is
 * still load-bearing at runtime: the assignment workflow records that a message is owed
 * inside its own transaction and never waits on SMTP, and a delivery failure therefore
 * cannot roll an assignment back.
 *
 * **Idempotency is the whole point.** Every enqueue is a `findOneOrCreate()` on
 * `vntf_DedupeKey`, which is `UNIQUE` (§2.14) — the `event_attend`
 * `UNIQUE(event_id, person_id)` + `findOneOrCreate()` idiom from `Event.php:87-90`. A
 * retried assign, a double-submitted decline or a re-run swap approval therefore
 * produces no second message, which is #9710's "duplicate notifications are avoided
 * when operations are retried" satisfied by the schema rather than by a check that can
 * be forgotten.
 *
 * **Call it inside the caller's transaction.** `VolunteerAssignmentService` wraps every
 * state change in one Propel transaction and enqueues inside it, so a rolled-back
 * decline leaves no decline alert behind. The converse — delivery failing and rolling
 * back the state change — cannot happen here because nothing is delivered here.
 *
 * The dedupe-key builders for all seven types live beside the enqueuers. `reminder` rows
 * are written by `scheduleReminders()` rather than by the assignment workflow: §3.6
 * schedules them from the occurrence's start minus `iVolunteerReminderLeadHours`, which
 * means the decision is a property of the clock, not of an operator's click, and so
 * belongs in the timer job.
 */
class VolunteerNotificationService
{
    public const TYPE_ASSIGNMENT = VolunteerNotification::TYPE_ASSIGNMENT;
    public const TYPE_REMINDER = VolunteerNotification::TYPE_REMINDER;
    public const TYPE_DECLINE_ALERT = VolunteerNotification::TYPE_DECLINE_ALERT;
    public const TYPE_GAP_ALERT = VolunteerNotification::TYPE_GAP_ALERT;
    public const TYPE_SIGNUP_CONFIRM = VolunteerNotification::TYPE_SIGNUP_CONFIRM;
    public const TYPE_SWAP_PROPOSED = VolunteerNotification::TYPE_SWAP_PROPOSED;
    public const TYPE_SWAP_RESOLVED = VolunteerNotification::TYPE_SWAP_RESOLVED;
    public const TYPE_HELP_OFFER = VolunteerNotification::TYPE_HELP_OFFER;

    /**
     * Deliveries attempted before a row is given up on (§2.14).
     *
     * Five, and the row stays `pending` — and therefore still due — for the first four,
     * which is what makes retry implicit in the drain's selection filter rather than a
     * separate `retrying` state nothing would ever clear.
     */
    public const MAX_ATTEMPTS = 5;

    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    /**
     * Enqueue one message, idempotently, on its dedupe key.
     *
     * Returns the row whether it was created or already existed; the caller can tell
     * which from `$notification->isNew()` being false and `wasJustCreated()` below.
     * A row that already exists is NEVER re-armed here — that is what makes a retry
     * silent. `POST /assignments/{id}/notify?force=1` is the one deliberate exception
     * and calls `rearm()`.
     *
     * `$scheduledFor` is naive wall-clock in `sTimeZone` like every other V2 timestamp
     * (§2.0, F24); `null` means "now", i.e. the next drain.
     */
    public function enqueue(
        string $type,
        int $recipientPersonId,
        ?int $assignmentId,
        ?int $occurrenceId,
        \DateTimeInterface $scheduledFor,
        string $dedupeKey,
        ?array $context = null
    ): VolunteerNotification {
        $notification = VolunteerNotificationQuery::create()
            ->filterByDedupeKey($dedupeKey)
            ->findOneOrCreate();

        if (!$notification->isNew()) {
            return $notification;
        }

        $notification->setType($type);
        $notification->setChannel(VolunteerNotification::CHANNEL_EMAIL);
        $notification->setPersonId($recipientPersonId);
        $notification->setAssignmentId($assignmentId);
        $notification->setOccurrenceId($occurrenceId);
        $notification->setDedupeKey($dedupeKey);
        $notification->setScheduledFor($scheduledFor);
        $notification->setStatus(VolunteerNotification::STATUS_PENDING);
        $notification->setAttempts(0);
        // D19: the row's own context, for a type that hangs off neither an assignment
        // nor an occurrence. Written once, at enqueue, because that is the only moment
        // the fact is true — see `help_offer` below.
        $notification->setContext($context === null ? null : json_encode($context));
        $notification->save();

        $this->logger->info('Volunteer notification enqueued', [
            'type' => $type,
            'personId' => $recipientPersonId,
            'assignmentId' => $assignmentId,
            'occurrenceId' => $occurrenceId,
            'dedupeKey' => $dedupeKey,
        ]);

        return $notification;
    }

    /**
     * Put a row that has already been sent (or given up on) back in the queue.
     *
     * The only caller is `POST /assignments/{id}/notify?force=1` — "send it again, I
     * know". The row is reused rather than duplicated so the dedupe key keeps meaning
     * what it says and the outbox never grows a second row for the same message.
     */
    public function rearm(VolunteerNotification $notification): VolunteerNotification
    {
        $notification->setStatus(VolunteerNotification::STATUS_PENDING);
        $notification->setScheduledFor(DateTimeUtils::getToday());
        $notification->setAttempts(0);
        $notification->setLastError(null);
        $notification->save();

        $this->logger->info('Volunteer notification re-armed', [
            'notificationId' => $notification->getId(),
            'dedupeKey' => $notification->getDedupeKey(),
        ]);

        return $notification;
    }

    /**
     * Delete the `pending` outbox rows of an assignment that has stopped being live.
     *
     * §3.6: a cancelled or declined assignment must never produce a reminder. Only
     * `pending` rows are removed — a `sent` row is a delivery record and is history.
     *
     * @return int rows removed
     */
    public function cancelPendingFor(int $assignmentId): int
    {
        $removed = VolunteerNotificationQuery::create()
            ->filterByAssignmentId($assignmentId)
            ->filterByStatus(VolunteerNotification::STATUS_PENDING)
            ->delete();

        if ($removed > 0) {
            $this->logger->info('Volunteer notifications cancelled', [
                'assignmentId' => $assignmentId,
                'removed' => $removed,
            ]);
        }

        return $removed;
    }

    /** The rows already queued for one assignment, newest first. Used by the API read. */
    public function listForAssignment(int $assignmentId): array
    {
        return iterator_to_array(
            VolunteerNotificationQuery::create()
                ->filterByAssignmentId($assignmentId)
                ->orderById(Criteria::DESC)
                ->find(),
            false
        );
    }

    public function findByDedupeKey(string $dedupeKey): ?VolunteerNotification
    {
        return VolunteerNotificationQuery::create()->filterByDedupeKey($dedupeKey)->findOne();
    }

    // ── Dedupe keys (§2.14) ────────────────────────────────────────────────
    //
    // Stable, documented, and never parsed — only compared. Each is built from ids
    // that already exist when the message is decided, so the same operation retried
    // builds the same key and `findOneOrCreate()` does the rest.

    public function assignmentKey(int $assignmentId, int $personId): string
    {
        return sprintf('%s:%d:%d', self::TYPE_ASSIGNMENT, $assignmentId, $personId);
    }

    /** Built for #9710's reminder job; nothing in #9709 enqueues one (see the class docblock). */
    public function reminderKey(int $assignmentId, int $personId): string
    {
        return sprintf('%s:%d:%d', self::TYPE_REMINDER, $assignmentId, $personId);
    }

    public function declineAlertKey(int $assignmentId, int $coordinatorPersonId): string
    {
        return sprintf('%s:%d:%d', self::TYPE_DECLINE_ALERT, $assignmentId, $coordinatorPersonId);
    }

    /**
     * One per coordinator per occurrence PER DAY — the date is in the key on purpose
     * (§2.14), so a ministry that loses three volunteers in one afternoon produces one
     * alert, not three.
     */
    public function gapAlertKey(int $occurrenceId, int $coordinatorPersonId, ?\DateTimeInterface $day = null): string
    {
        $day ??= DateTimeUtils::getToday();

        return sprintf(
            '%s:%d:%d:%s',
            self::TYPE_GAP_ALERT,
            $occurrenceId,
            $coordinatorPersonId,
            $day->format('Y-m-d')
        );
    }

    /** Built for #9712's self-signup; nothing in #9709 enqueues one. */
    public function signupConfirmKey(int $assignmentId, int $personId): string
    {
        return sprintf('%s:%d:%d', self::TYPE_SIGNUP_CONFIRM, $assignmentId, $personId);
    }

    public function swapProposedKey(int $swapId, int $coordinatorPersonId): string
    {
        return sprintf('%s:%d:%d', self::TYPE_SWAP_PROPOSED, $swapId, $coordinatorPersonId);
    }

    public function swapResolvedKey(int $swapId, int $personId): string
    {
        return sprintf('%s:%d:%d', self::TYPE_SWAP_RESOLVED, $swapId, $personId);
    }

    /**
     * `help_offer:{ministryId}:{personId}:{Y-m-d}:{recipientPersonId}` (D19).
     *
     * The DATE is in the key on purpose and is the whole behaviour: a volunteer who taps
     * "I'd like to help" four times in a row produces one message, and the same volunteer
     * offering again next week produces another — which is a coordinator being told
     * something, not a duplicate.
     *
     * The RECIPIENT is in the key for the same reason it is in `gapAlertKey()`: the key
     * is `UNIQUE` and one row carries one recipient, so a ministry with three
     * coordinators needs three rows. Dedupe is therefore per coordinator per day, which
     * is what "a second click the same day sends nothing" means from the coordinator's
     * side — the only side that can observe it.
     */
    public function helpOfferKey(
        int $ministryId,
        int $personId,
        int $recipientPersonId,
        ?\DateTimeInterface $day = null
    ): string {
        return sprintf(
            '%s:%d:%d:%s:%d',
            self::TYPE_HELP_OFFER,
            $ministryId,
            $personId,
            ($day ?? DateTimeUtils::getToday())->format('Y-m-d'),
            $recipientPersonId
        );
    }

    // ── Convenience enqueuers, one per §3.6 trigger this issue owns ────────

    public function enqueueAssignment(VolunteerAssignment $assignment): VolunteerNotification
    {
        return $this->enqueue(
            self::TYPE_ASSIGNMENT,
            (int) $assignment->getPersonId(),
            (int) $assignment->getId(),
            (int) $assignment->getOccurrenceId(),
            DateTimeUtils::getToday(),
            $this->assignmentKey((int) $assignment->getId(), (int) $assignment->getPersonId())
        );
    }

    /**
     * "You are on the roster, and you put yourself there" (#9712, §3.6).
     *
     * The self-signup counterpart of `enqueueAssignment()`: a volunteer who signed
     * themselves up is already `accepted`, so the assignment message — which asks
     * them to answer — would be nonsense. The dedupe key is per assignment per
     * person, so re-signing up after a decline (I8 re-uses the same row) produces no
     * second confirmation.
     */
    public function enqueueSignupConfirm(VolunteerAssignment $assignment): VolunteerNotification
    {
        return $this->enqueue(
            self::TYPE_SIGNUP_CONFIRM,
            (int) $assignment->getPersonId(),
            (int) $assignment->getId(),
            (int) $assignment->getOccurrenceId(),
            DateTimeUtils::getToday(),
            $this->signupConfirmKey((int) $assignment->getId(), (int) $assignment->getPersonId())
        );
    }

    /**
     * @param int[] $coordinatorPersonIds
     *
     * @return VolunteerNotification[]
     */
    public function enqueueDeclineAlert(VolunteerAssignment $assignment, array $coordinatorPersonIds): array
    {
        $rows = [];
        foreach ($coordinatorPersonIds as $personId) {
            $rows[] = $this->enqueue(
                self::TYPE_DECLINE_ALERT,
                (int) $personId,
                (int) $assignment->getId(),
                (int) $assignment->getOccurrenceId(),
                DateTimeUtils::getToday(),
                $this->declineAlertKey((int) $assignment->getId(), (int) $personId)
            );
        }

        return $rows;
    }

    /**
     * A gap has just opened on this occurrence. Occurrence-scoped, not assignment-scoped:
     * `vntf_vasg_ID` stays null, which is exactly what that column is nullable for (§2.14).
     *
     * @param int[] $coordinatorPersonIds
     *
     * @return VolunteerNotification[]
     */
    public function enqueueGapAlert(int $occurrenceId, array $coordinatorPersonIds): array
    {
        $rows = [];
        foreach ($coordinatorPersonIds as $personId) {
            $rows[] = $this->enqueue(
                self::TYPE_GAP_ALERT,
                (int) $personId,
                null,
                $occurrenceId,
                DateTimeUtils::getToday(),
                $this->gapAlertKey($occurrenceId, (int) $personId)
            );
        }

        return $rows;
    }

    /**
     * @param int[] $coordinatorPersonIds
     *
     * @return VolunteerNotification[]
     */
    public function enqueueSwapProposed(VolunteerSwap $swap, int $occurrenceId, array $coordinatorPersonIds): array
    {
        $rows = [];
        foreach ($coordinatorPersonIds as $personId) {
            $rows[] = $this->enqueue(
                self::TYPE_SWAP_PROPOSED,
                (int) $personId,
                (int) $swap->getAssignmentId(),
                $occurrenceId,
                DateTimeUtils::getToday(),
                $this->swapProposedKey((int) $swap->getId(), (int) $personId)
            );
        }

        return $rows;
    }

    /**
     * Both parties hear the outcome — the proposer and the substitute (§3.6).
     *
     * @return VolunteerNotification[]
     */
    public function enqueueSwapResolved(VolunteerSwap $swap, int $occurrenceId): array
    {
        $rows = [];
        $recipients = array_unique([
            (int) $swap->getProposedByPersonId(),
            (int) $swap->getProposedPersonId(),
        ]);

        foreach ($recipients as $personId) {
            $rows[] = $this->enqueue(
                self::TYPE_SWAP_RESOLVED,
                $personId,
                (int) $swap->getAssignmentId(),
                $occurrenceId,
                DateTimeUtils::getToday(),
                $this->swapResolvedKey((int) $swap->getId(), $personId)
            );
        }

        return $rows;
    }

    /**
     * Somebody offered to help a ministry (D19).
     *
     * Ministry-scoped, so both foreign-key columns stay null and `vntf_Context` carries
     * what the message needs: which ministry, who offered, and whether this click is what
     * put them in the pool. That last fact is recorded HERE because it stops being true
     * the moment it is recorded — by the time the outbox drains, the person is in the
     * pool either way, and a delivery-time recomputation would always say "already a
     * member".
     *
     * One row per coordinator per day (the dedupe key), so a ministry with three
     * coordinators gets three messages and a volunteer clicking three times gets none
     * extra.
     *
     * @param int[] $recipientPersonIds
     *
     * @return VolunteerNotification[]
     */
    public function enqueueHelpOffer(
        int $ministryId,
        int $offeringPersonId,
        array $recipientPersonIds,
        bool $joinedPool
    ): array {
        $rows = [];
        foreach ($recipientPersonIds as $personId) {
            $rows[] = $this->enqueue(
                self::TYPE_HELP_OFFER,
                (int) $personId,
                null,
                null,
                DateTimeUtils::getToday(),
                $this->helpOfferKey($ministryId, $offeringPersonId, (int) $personId, DateTimeUtils::getToday()),
                [
                    'ministryId' => $ministryId,
                    'personId' => $offeringPersonId,
                    'joinedPool' => $joinedPool,
                ]
            );
        }

        return $rows;
    }

    // ── Reminders (§3.6, Appendix B) ───────────────────────────────────────

    /**
     * Enqueue a `reminder` for every live assignment whose occurrence starts inside
     * `iVolunteerReminderLeadHours` and has not already ended (UC5, D11).
     *
     * Called from `SystemService::runTimerJobs()` **before** the drain, so a reminder
     * that becomes due between two runs is scheduled and delivered in the same pass.
     *
     * `ScheduledFor` is the occurrence's start minus the lead, which for an occurrence
     * already inside the window is in the past — that is correct and is the point: the
     * message is due now. The value is stored rather than recomputed so that changing
     * the setting later does not silently reschedule mail that was already queued.
     *
     * **A lead of 0 (or less) schedules nothing**, which makes the setting a kill switch
     * an administrator can reach without turning off all church email.
     *
     * Idempotent by construction: `reminder:{assignmentId}:{personId}` is the dedupe key,
     * so running the timer job every fifteen minutes produces one reminder, not ninety-six.
     *
     * @return int rows enqueued (existing rows are not counted)
     */
    public function scheduleReminders(): int
    {
        $leadHours = SystemConfig::getIntValue('iVolunteerReminderLeadHours');
        if ($leadHours <= 0) {
            return 0;
        }

        $now = DateTimeUtils::getToday();
        $horizon = DateTimeUtils::createDateTime($now->format('Y-m-d H:i:s'))
            ->modify(sprintf('+%d hours', $leadHours));

        // Bound the scan by occurrence DATE first — the precise window question is
        // answered per row below, but there is no reason to hydrate every occurrence
        // in the table to find out. A day of slack on each side covers an occurrence
        // whose event time sits either side of midnight.
        // Two filters rather than one range: Propel has no Criteria::BETWEEN, and
        // the generated filterBy*() range form takes an array without a comparison
        // constant, which reads as an IN to anyone skimming it.
        $occurrences = VolunteerOccurrenceQuery::create()
            ->filterByStatus(VolunteerOccurrence::STATUS_SCHEDULED)
            ->filterByOccurrenceDate(
                DateTimeUtils::createDateTime($now->format('Y-m-d'))->modify('-1 day'),
                Criteria::GREATER_EQUAL
            )
            ->filterByOccurrenceDate(
                DateTimeUtils::createDateTime($horizon->format('Y-m-d'))->modify('+1 day'),
                Criteria::LESS_EQUAL
            )
            ->find();

        if (count($occurrences) === 0) {
            return 0;
        }

        $schedules = new VolunteerScheduleService();
        $due = [];
        foreach ($occurrences as $occurrence) {
            $window = $schedules->resolveOccurrenceWindow($occurrence);
            $start = $window['start'] ?? null;
            $end = $window['end'] ?? $start;

            if ($start === null || $start > $horizon) {
                continue;
            }

            // An occurrence that is already over gets no reminder: the drain would
            // only skip it again (§3.6 step 2).
            if ($end !== null && $end < $now) {
                continue;
            }

            $due[(int) $occurrence->getId()] = DateTimeUtils::createDateTime($start->format('Y-m-d H:i:s'))
                ->modify(sprintf('-%d hours', $leadHours));
        }

        if ($due === []) {
            return 0;
        }

        $assignments = VolunteerAssignmentQuery::create()
            ->filterByOccurrenceId(array_keys($due), Criteria::IN)
            ->filterByStatus(VolunteerAssignmentService::LIVE_STATUSES, Criteria::IN)
            ->find();

        $enqueued = 0;
        foreach ($assignments as $assignment) {
            $assignmentId = (int) $assignment->getId();
            $personId = (int) $assignment->getPersonId();
            $key = $this->reminderKey($assignmentId, $personId);

            if ($this->findByDedupeKey($key) !== null) {
                continue;
            }

            $this->enqueue(
                self::TYPE_REMINDER,
                $personId,
                $assignmentId,
                (int) $assignment->getOccurrenceId(),
                $due[(int) $assignment->getOccurrenceId()],
                $key
            );
            $enqueued++;
        }

        if ($enqueued > 0) {
            $this->logger->info('Volunteer reminders scheduled', [
                'count' => $enqueued,
                'leadHours' => $leadHours,
            ]);
        }

        return $enqueued;
    }

    // ── Drain (§3.6) ───────────────────────────────────────────────────────

    /**
     * Drain the outbox.
     *
     * `static` to match the `BirthdayEmailService::run()` call shape at
     * `SystemService.php` (design §3.4); the work is an instance method so the service's
     * own collaborators and logger are available the ordinary way.
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    public static function drainOutbox(int $batchSize = 50): array
    {
        return (new self())->drain($batchSize);
    }

    /**
     * §3.6's four steps, in order.
     *
     * 1. up to `$batchSize` `pending` rows due now, oldest `ScheduledFor` first;
     * 2. the four `skipped` rules — email off (N2), do-not-email (N3), no address, and a
     *    `reminder` for an occurrence that is already over;
     * 3. otherwise build the subclass, resolve `Reply-To` **at send time** and send,
     *    recording `sent` or `Attempts + 1`;
     * 4. retry is implicit — a row below the cap is still `pending` and still due, so the
     *    next drain picks it up. `failed` is terminal and never re-selected.
     *
     * Every row is wrapped in its own `try/catch (\Throwable)` — the per-channel
     * isolation pattern from `dto\Notification::send()` (N4) — so one unreachable SMTP
     * server, one deleted person or one malformed address cannot stop the batch. Nothing
     * in here ever throws to the caller: the timer job must not fail because the mail
     * server is down.
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    private function drain(int $batchSize): array
    {
        $counts = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        $rows = VolunteerNotificationQuery::create()
            ->filterByStatus(VolunteerNotification::STATUS_PENDING)
            ->filterByScheduledFor(DateTimeUtils::getToday(), Criteria::LESS_EQUAL)
            ->orderByScheduledFor(Criteria::ASC)
            ->limit(max(1, $batchSize))
            ->find();

        if (count($rows) === 0) {
            return $counts;
        }

        // Both of these are per-batch, not per-row: the kill switch cannot change
        // halfway through a drain, and the opt-out set is one query instead of one
        // per recipient.
        $emailEnabled = SystemConfig::isEmailEnabled();
        $doNotEmail = (new PersonService())->buildDoNotEmailSet();

        foreach ($rows as $row) {
            try {
                $counts[$this->deliver($row, $emailEnabled, $doNotEmail)]++;
            } catch (\Throwable $e) {
                // A throw from anywhere in the build or the send is a delivery
                // failure like any other, and is counted against the retry cap so a
                // permanently broken row cannot be retried forever.
                $this->recordFailure($row, $e->getMessage());
                $counts['failed']++;
                $this->logger->error('Volunteer notification delivery threw', [
                    'notificationId' => $row->getId(),
                    'type' => $row->getType(),
                    'dedupeKey' => $row->getDedupeKey(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Volunteer notification outbox drained', $counts);

        return $counts;
    }

    /**
     * Deliver one row, or decide not to.
     *
     * @param array<int, bool> $doNotEmail
     *
     * @return string one of 'sent', 'skipped', 'failed' — the key to increment
     */
    private function deliver(VolunteerNotification $row, bool $emailEnabled, array $doNotEmail): string
    {
        // N2: checked BEFORE anything is attempted, because BaseEmail::send() returns
        // false identically for "email is switched off" and "SMTP refused us", and the
        // outbox has to tell those apart or the retry rule is meaningless.
        if (!$emailEnabled) {
            return $this->recordSkipped($row, 'email disabled');
        }

        $recipient = PersonQuery::create()->findPk((int) $row->getPersonId());
        if ($recipient === null) {
            return $this->recordSkipped($row, 'recipient no longer exists');
        }

        // N3: mandatory. A person who asked not to be mailed is not mailed, and the
        // row records that we chose not to rather than that we failed.
        if (isset($doNotEmail[(int) $row->getPersonId()])) {
            return $this->recordSkipped($row, 'recipient is in the do-not-email set');
        }

        // Person::getEmail() falls back to the FAMILY address, so this can legitimately
        // be a shared inbox (Appendix C notes it; V2 cannot fix it).
        $address = (string) ($recipient->getEmail() ?? '');
        if (trim($address) === '') {
            return $this->recordSkipped($row, 'recipient has no email address');
        }

        $context = $this->buildContext($row);
        if ($context === null) {
            return $this->recordSkipped($row, 'the ministry, schedule or occurrence is gone');
        }

        if ($row->getType() === self::TYPE_REMINDER && $context->hasEnded()) {
            return $this->recordSkipped($row, 'the occurrence has already ended');
        }

        $email = $this->buildEmail($row, $recipient, $address, $context);
        if ($email === null) {
            return $this->recordSkipped($row, 'no message could be built for this row');
        }

        $this->applyReplyTo($email, $row, $context);

        if ($email->send()) {
            $row->setStatus(VolunteerNotification::STATUS_SENT);
            $row->setSentDate(DateTimeUtils::getToday());
            $row->setLastAttemptDate(DateTimeUtils::getToday());
            $row->setAttempts((int) $row->getAttempts() + 1);
            $row->setLastError(null);
            $row->save();

            $this->logger->info('Volunteer notification sent', [
                'notificationId' => $row->getId(),
                'type' => $row->getType(),
                'personId' => $row->getPersonId(),
            ]);

            return 'sent';
        }

        $this->recordFailure($row, $email->getError());

        return 'failed';
    }

    /** §2.14: `skipped` means "we chose not to", so no attempt is counted. */
    private function recordSkipped(VolunteerNotification $row, string $reason): string
    {
        $row->setStatus(VolunteerNotification::STATUS_SKIPPED);
        $row->setLastError(mb_substr($reason, 0, 255));
        $row->save();

        $this->logger->info('Volunteer notification skipped', [
            'notificationId' => $row->getId(),
            'type' => $row->getType(),
            'personId' => $row->getPersonId(),
            'reason' => $reason,
        ]);

        return 'skipped';
    }

    /**
     * §2.14 (amended): a failure keeps the row `pending` so the next drain retries it,
     * until `Attempts` reaches the cap — only then is it `failed`, which is terminal and
     * means "gave up", never "will retry".
     */
    private function recordFailure(VolunteerNotification $row, string $error): void
    {
        $attempts = (int) $row->getAttempts() + 1;

        $row->setAttempts($attempts);
        $row->setLastAttemptDate(DateTimeUtils::getToday());
        $row->setLastError(mb_substr($error === '' ? gettext('Unknown delivery error') : $error, 0, 255));
        $row->setStatus(
            $attempts >= self::MAX_ATTEMPTS
                ? VolunteerNotification::STATUS_FAILED
                : VolunteerNotification::STATUS_PENDING
        );
        $row->save();

        $this->logger->warning('Volunteer notification delivery failed', [
            'notificationId' => $row->getId(),
            'type' => $row->getType(),
            'personId' => $row->getPersonId(),
            'attempts' => $attempts,
            'status' => $row->getStatus(),
            'error' => $row->getLastError(),
        ]);
    }

    /**
     * Set the `Reply-To` for this row's direction (§3.6), or set none at all.
     *
     * Volunteer-facing mail replies to the responsible coordinator; a decline alert
     * replies to the volunteer who declined; a swap proposal replies to the volunteer who
     * proposed it; a gap alert names no single volunteer and therefore carries no
     * Reply-To. Resolution happens HERE, at send time, so a coordinator handover between
     * enqueue and delivery is picked up automatically — the outbox stores no address.
     *
     * **Never throws.** No coordinator, no address, an address its owner opted out of and
     * an address PHPMailer rejects all mean "send it without the header" (Appendix C),
     * which degrades to exactly the behaviour every other ChurchCRM email has today.
     */
    private function applyReplyTo(BaseEmail $email, VolunteerNotification $row, VolunteerEmailContext $context): void
    {
        try {
            $personId = match ($row->getType()) {
                self::TYPE_GAP_ALERT => null,
                self::TYPE_DECLINE_ALERT => $this->assignmentPersonId($row),
                self::TYPE_SWAP_PROPOSED => $this->swapProposerPersonId($row),
                // D19: a coordinator reading "Ann wants to help" should be able to hit
                // Reply and reach Ann. The message goes TO the coordinator, so
                // coordinatorPersonId() would point it back at the recipient.
                self::TYPE_HELP_OFFER => (int) ($this->rowContext($row)['personId'] ?? 0) ?: null,
                default => $this->coordinatorPersonId($row),
            };

            if ($personId === null) {
                return;
            }

            $person = PersonQuery::create()->findPk($personId);
            if ($person === null) {
                return;
            }

            $address = (string) ($person->getEmail() ?? '');
            if (trim($address) === '') {
                return;
            }

            // A Reply-To invites mail to an address whose owner may have opted out, so
            // the opt-out is honoured on this side of the header too (§3.6).
            $doNotEmail = (new PersonService())->buildDoNotEmailSet([$personId]);
            if (isset($doNotEmail[$personId])) {
                return;
            }

            $email->setReplyTo($address, $person->getFullName());
        } catch (\Throwable $e) {
            $this->logger->warning('Volunteer notification Reply-To could not be resolved', [
                'notificationId' => $row->getId(),
                'type' => $row->getType(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /** The responsible coordinator for this row's occurrence — the ONE owner of §3.6's order. */
    private function coordinatorPersonId(VolunteerNotification $row): ?int
    {
        $scope = $this->scheduleScopeFor($row);
        if ($scope === null) {
            return null;
        }

        return (new VolunteerAuthorizationService())
            ->getReplyToPersonId($scope['ministryId'], $scope['teamId']);
    }

    private function assignmentPersonId(VolunteerNotification $row): ?int
    {
        $assignment = $this->assignmentFor($row);

        return $assignment === null ? null : (int) $assignment->getPersonId();
    }

    private function swapProposerPersonId(VolunteerNotification $row): ?int
    {
        $swap = $this->swapFor($row, true);

        return $swap === null ? null : (int) $swap->getProposedByPersonId();
    }

    // ── Building the message ───────────────────────────────────────────────

    /**
     * The ministry / team / position / when / where block for this row, or null when
     * the rows it hangs off have been deleted underneath it.
     */
    private function buildContext(VolunteerNotification $row): ?VolunteerEmailContext
    {
        // D19: `help_offer` is about a ministry, not an occurrence. There is no schedule,
        // no date and no position to resolve — that absence IS the message ("somebody
        // wants to help, nothing is arranged yet"), so it takes its own two-line path
        // rather than being forced through the occurrence chain below, which would
        // return null and silently skip the row.
        if ($row->getType() === self::TYPE_HELP_OFFER) {
            $ministry = VolunteerMinistryQuery::create()->findPk($this->helpOfferMinistryId($row));

            return $ministry === null ? null : new VolunteerEmailContext((string) $ministry->getName());
        }

        $occurrence = $this->occurrenceFor($row);
        if ($occurrence === null) {
            return null;
        }

        $schedule = VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());
        if ($schedule === null) {
            return null;
        }

        $ministry = VolunteerMinistryQuery::create()->findPk((int) $schedule->getMinistryId());
        if ($ministry === null) {
            return null;
        }

        $teamName = null;
        if ($schedule->getTeamId() !== null) {
            $team = VolunteerTeamQuery::create()->findPk((int) $schedule->getTeamId());
            $teamName = $team === null ? null : (string) $team->getName();
        }

        $positionName = null;
        $assignment = $this->assignmentFor($row);
        if ($assignment !== null) {
            $position = VolunteerPositionQuery::create()->findPk((int) $assignment->getPositionId());
            $positionName = $position === null ? null : (string) $position->getName();
        }

        // Times come only from resolveOccurrenceWindow() — §3.4 says nothing else may
        // decide an occurrence's window, and a second derivation here is exactly how an
        // email ends up disagreeing with the page it links to.
        $window = (new VolunteerScheduleService())->resolveOccurrenceWindow($occurrence);

        return new VolunteerEmailContext(
            (string) $ministry->getName(),
            $teamName,
            $positionName,
            $window['start'] ?? null,
            $window['end'] ?? null,
            $this->locationNameFor($occurrence),
            (int) $occurrence->getId()
        );
    }

    /** The linked event's location, when there is one. Unlinked occurrences have none. */
    private function locationNameFor(VolunteerOccurrence $occurrence): ?string
    {
        $eventId = $occurrence->getEventId();
        if ($eventId === null) {
            return null;
        }

        $event = EventQuery::create()->findPk((int) $eventId);
        $location = $event?->getLocation();

        return $location === null ? null : (string) $location->getName();
    }

    /** One `BaseEmail` subclass per outbox type (Appendix C). */
    private function buildEmail(
        VolunteerNotification $row,
        Person $recipient,
        string $address,
        VolunteerEmailContext $context
    ): ?BaseEmail {
        $to = [$address];
        $recipientName = (string) $recipient->getFullName();

        switch ($row->getType()) {
            case self::TYPE_ASSIGNMENT:
                return new VolunteerAssignmentEmail($to, $recipientName, $context);

            case self::TYPE_REMINDER:
                return new VolunteerReminderEmail($to, $recipientName, $context);

            case self::TYPE_SIGNUP_CONFIRM:
                return new VolunteerSignupConfirmEmail($to, $recipientName, $context);

            case self::TYPE_DECLINE_ALERT:
                $assignment = $this->assignmentFor($row);
                if ($assignment === null) {
                    return null;
                }

                return new VolunteerDeclineAlertEmail(
                    $to,
                    $recipientName,
                    $context,
                    $this->personName((int) $assignment->getPersonId()),
                    $this->gapForPosition((int) $assignment->getOccurrenceId(), (int) $assignment->getPositionId())
                );

            case self::TYPE_HELP_OFFER:
                $context_ = $this->rowContext($row);
                $offeringPersonId = (int) ($context_['personId'] ?? 0);
                if ($offeringPersonId <= 0) {
                    return null;
                }

                return new VolunteerHelpOfferEmail(
                    $to,
                    $recipientName,
                    $context,
                    $this->personName($offeringPersonId),
                    $this->helpOfferMinistryId($row),
                    (bool) ($context_['joinedPool'] ?? false)
                );

            case self::TYPE_GAP_ALERT:
                return new VolunteerGapAlertEmail(
                    $to,
                    $recipientName,
                    $context,
                    $this->shortPositions((int) $row->getOccurrenceId())
                );

            case self::TYPE_SWAP_PROPOSED:
                $swap = $this->swapFor($row, true);
                if ($swap === null) {
                    return null;
                }

                return new VolunteerSwapProposedEmail(
                    $to,
                    $recipientName,
                    $context,
                    $this->personName((int) $swap->getProposedByPersonId()),
                    $this->personName((int) $swap->getProposedPersonId())
                );

            case self::TYPE_SWAP_RESOLVED:
                $swap = $this->swapFor($row, false);
                if ($swap === null) {
                    return null;
                }

                return new VolunteerSwapResolvedEmail(
                    $to,
                    $recipientName,
                    $context,
                    $this->personName((int) $swap->getProposedByPersonId()),
                    $this->personName((int) $swap->getProposedPersonId()),
                    $swap->getStatus() === VolunteerSwap::STATUS_APPROVED
                        ? VolunteerSwapResolvedEmail::DECISION_APPROVED
                        : VolunteerSwapResolvedEmail::DECISION_REJECTED
                );
        }

        return null;
    }

    /**
     * The JSON `vntf_Context` this row was enqueued with, or an empty array.
     *
     * Never throws: a row whose context is missing or malformed is a row whose message
     * cannot be built, which `buildEmail()` already handles by skipping it.
     *
     * @return array<string, mixed>
     */
    private function rowContext(VolunteerNotification $row): array
    {
        $raw = (string) ($row->getContext() ?? '');
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function helpOfferMinistryId(VolunteerNotification $row): int
    {
        return (int) ($this->rowContext($row)['ministryId'] ?? 0);
    }

    private function personName(int $personId): string
    {
        $person = PersonQuery::create()->findPk($personId);

        return $person === null ? gettext('A volunteer') : (string) $person->getFullName();
    }

    /**
     * How many more people that one position still needs.
     *
     * `VolunteerAssignmentService::getGaps()` is THE gap implementation (§2.11.3); this
     * asks it rather than counting rows, so a mail can never disagree with the staffing
     * page it links to.
     */
    private function gapForPosition(int $occurrenceId, int $positionId): int
    {
        $gaps = (new VolunteerAssignmentService())->getGaps([$occurrenceId]);

        return (int) ($gaps[$occurrenceId]['requirements'][$positionId]['gapCount'] ?? 0);
    }

    /**
     * Every position on this occurrence that is genuinely short, name → how many.
     *
     * @return array<string, int>
     */
    private function shortPositions(int $occurrenceId): array
    {
        $short = [];
        foreach ((new VolunteerAssignmentService())->getOpenGaps([$occurrenceId]) as $gap) {
            $name = $gap['positionName'] ?? null;
            if ($name === null || (int) $gap['gapCount'] <= 0) {
                continue;
            }
            $short[(string) $name] = (int) $gap['gapCount'];
        }

        return $short;
    }

    // ── Row lookups ────────────────────────────────────────────────────────

    private function assignmentFor(VolunteerNotification $row): ?VolunteerAssignment
    {
        $assignmentId = $row->getAssignmentId();

        return $assignmentId === null
            ? null
            : VolunteerAssignmentQuery::create()->findPk((int) $assignmentId);
    }

    private function occurrenceFor(VolunteerNotification $row): ?VolunteerOccurrence
    {
        $occurrenceId = $row->getOccurrenceId();
        if ($occurrenceId !== null) {
            $occurrence = VolunteerOccurrenceQuery::create()->findPk((int) $occurrenceId);
            if ($occurrence !== null) {
                return $occurrence;
            }
        }

        $assignment = $this->assignmentFor($row);

        return $assignment === null
            ? null
            : VolunteerOccurrenceQuery::create()->findPk((int) $assignment->getOccurrenceId());
    }

    /**
     * The swap a `swap_proposed` / `swap_resolved` row is about.
     *
     * The dedupe key carries the swap id, but §2.14 is explicit that keys are compared
     * and never parsed, so the swap is found through the row's assignment instead. That
     * is unambiguous under §2.13's invariant that an assignment has **at most one
     * `proposed` swap at a time**: an unresolved proposal is the only candidate for
     * `swap_proposed`, and the newest resolved one is the only candidate for
     * `swap_resolved`.
     */
    private function swapFor(VolunteerNotification $row, bool $wantProposed): ?VolunteerSwap
    {
        $assignmentId = $row->getAssignmentId();
        if ($assignmentId === null) {
            return null;
        }

        $query = VolunteerSwapQuery::create()->filterByAssignmentId((int) $assignmentId);

        if ($wantProposed) {
            $proposed = (clone $query)
                ->filterByStatus(VolunteerSwap::STATUS_PROPOSED)
                ->orderById(Criteria::DESC)
                ->findOne();

            if ($proposed !== null) {
                return $proposed;
            }
        } else {
            $resolved = (clone $query)
                ->filterByStatus(VolunteerSwap::STATUS_PROPOSED, Criteria::NOT_EQUAL)
                ->orderById(Criteria::DESC)
                ->findOne();

            if ($resolved !== null) {
                return $resolved;
            }
        }

        // The proposal was decided (or re-opened) between enqueue and drain; the newest
        // swap on the assignment is still the one the message is about.
        return $query->orderById(Criteria::DESC)->findOne();
    }

    /**
     * The ministry (and team) that owns this row's occurrence — the two arguments
     * `getReplyToPersonId()` needs.
     *
     * @return array{ministryId: int, teamId: ?int}|null
     */
    private function scheduleScopeFor(VolunteerNotification $row): ?array
    {
        $occurrence = $this->occurrenceFor($row);
        if ($occurrence === null) {
            return null;
        }

        $schedule = VolunteerScheduleQuery::create()->findPk((int) $occurrence->getScheduleId());
        if ($schedule === null) {
            return null;
        }

        return [
            'ministryId' => (int) $schedule->getMinistryId(),
            'teamId' => $schedule->getTeamId() === null ? null : (int) $schedule->getTeamId(),
        ];
    }
}
