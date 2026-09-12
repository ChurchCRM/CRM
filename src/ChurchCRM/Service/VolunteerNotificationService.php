<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\VolunteerAssignment;
use ChurchCRM\model\ChurchCRM\VolunteerNotification;
use ChurchCRM\model\ChurchCRM\VolunteerNotificationQuery;
use ChurchCRM\model\ChurchCRM\VolunteerSwap;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Log\LoggerInterface;

/**
 * The Volunteer v2 notification outbox — the ENQUEUE half only (#9709; design §2.14, §3.6).
 *
 * D15: there is no scheduler in ChurchCRM (F9), so V2 decouples "decide a message is
 * due" from "deliver it". This class owns the first half. #9710 owns the second:
 * `drainOutbox()` here is an explicit, documented no-op, and there is no `BaseEmail`
 * subclass, no template, no `Reply-To` resolution and no send log written anywhere in
 * this issue. That split is deliberate — the assignment workflow must be able to record
 * that a message is owed without waiting on the mail layer, and #9710 must be able to
 * build the mail layer against an outbox that is already being filled by the real
 * workflow (and by its specs, which assert the rows through `cy.dbQuery`).
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
 * The dedupe-key builders for all seven types live in `dedupeKey()`. `reminder` is built
 * but never enqueued by this issue: §3.6 schedules reminders from the occurrence's start
 * minus `iVolunteerReminderLeadHours`, and both that setting and the job that fires it
 * are #9710's. The builder is here so #9710 adds a caller, not a key format.
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
        string $dedupeKey
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
     * Drain the outbox. **#9710 implements this.**
     *
     * It is declared here, `static`, with its final signature and return shape so that
     * `SystemService::runTimerJobs()` can be wired to it in one line when #9710 lands —
     * matching the `BirthdayEmailService::run()` call shape at `SystemService.php:78`
     * (design §3.4). Until then it does nothing and says so: no row is selected, no mail
     * is built, no status is changed. It is NOT a stub that silently swallows work — the
     * outbox is genuinely being filled by #9709, and #9710's job is to start emptying it.
     *
     * What #9710 must add here (design §3.6 "Drain"): select up to `$batchSize` `pending`
     * rows due now ordered by `ScheduledFor`; skip (status `skipped`) when
     * `SystemConfig::isEmailEnabled()` is false, when the recipient is in
     * `PersonService::buildDoNotEmailSet()`, when they have no address, or when a
     * `reminder`'s occurrence has already ended; otherwise build the `BaseEmail` subclass
     * for the type, resolve `Reply-To` through
     * `VolunteerAuthorizationService::getReplyToPersonId()` AT SEND TIME, and send —
     * recording `sent`, or `Attempts + 1` with the row left `pending` until the fifth
     * failure turns it `failed`, each send in its own try/catch.
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    public static function drainOutbox(int $batchSize = 50): array
    {
        return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
    }
}
