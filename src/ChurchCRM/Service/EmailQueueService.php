<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\ComposerEmail;
use ChurchCRM\model\ChurchCRM\EmailLog;
use ChurchCRM\model\ChurchCRM\EmailLogQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Map\EmailLogTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;

/**
 * The composer's outgoing queue (#10356), kept in the email history itself.
 *
 * POST /api/email/send writes one `queued` email_log_eml row per recipient and returns.
 * drain() sends them in bounded runs: from the composer while it is open
 * (POST /api/email/batches/{batch}/send) and from the timer jobs (cron, or the page
 * footer on installs without cron).
 *
 * A run claims each row with a conditional UPDATE (queued -> sending) before sending it,
 * so two runs at the same time never send the same row. A failure the server may get over
 * (no connection, timeout, 4xx) puts the row back in the queue for a later attempt, up to
 * MAX_ATTEMPTS; a refused address or a 5xx reply fails it at once.
 */
class EmailQueueService
{
    public const MAX_ATTEMPTS = 3;

    /** Minutes to wait before the second and the third attempt. */
    public const RETRY_DELAY_MINUTES = [1 => 10, 2 => 60];

    /**
     * A row still `sending` this long after it was claimed belongs to a run that died
     * (PHP limit, server restart). Far longer than any run, so a live run is never overtaken.
     */
    public const CLAIM_MINUTES = 10;

    /** Web requests (composer, page footer) stop claiming rows after this much time or this many messages. */
    public const WEB_MAX_SECONDS = 15;
    public const WEB_MAX_MESSAGES = 100;

    /** The command-line runner (cron) has no request time limit. */
    public const CLI_MAX_SECONDS = 300;
    public const CLI_MAX_MESSAGES = 1000;

    private const PAGE_SIZE = 50;

    /**
     * Queues one message per recipient, all or none.
     *
     * @param list<array{personId: ?int, familyId: ?int, name: string, email: string}> $recipients
     * @param string $html the rendered message, the same for every recipient
     *
     * @return string the batch id
     */
    public function enqueue(array $recipients, string $subject, string $html, ?int $sentByUserId): string
    {
        $batch = bin2hex(random_bytes(16));
        $now = DateTimeUtils::getNowDateTime();

        $connection = Propel::getWriteConnection(EmailLogTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        try {
            foreach ($recipients as $recipient) {
                $row = new EmailLog();
                $row->setPerId($recipient['personId']);
                $row->setFamId($recipient['familyId']);
                $row->setUsrId($sentByUserId);
                $row->setAddress(mb_substr($recipient['email'], 0, 255));
                $row->setKind('composer');
                $row->setSubject(mb_substr($subject, 0, 255));
                $row->setBody($html);
                $row->setStatus(EmailLogService::STATUS_QUEUED);
                $row->setDateSent($now);
                $row->setBatch($batch);
                $row->setAttempts(0);
                $row->setNextAttempt($now);
                $row->save($connection);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        return $batch;
    }

    /** The timer job: every due row, within the limits of the current kind of run. */
    public static function runTimerJob(): void
    {
        (new self())->drain();
    }

    /**
     * Sends due queued rows, oldest first, until the time or message limit is reached or
     * nothing is due. Does nothing while email is disabled: the rows wait.
     *
     * @param ?string $batch only this batch (the composer); null for every batch
     *
     * @return array{sent: int, failed: int, retrying: int}
     */
    public function drain(?string $batch = null, ?int $maxSeconds = null, ?int $maxMessages = null): array
    {
        $counts = ['sent' => 0, 'failed' => 0, 'retrying' => 0];
        if (!SystemConfig::isEmailEnabled()) {
            return $counts;
        }

        $cli = PHP_SAPI === 'cli';
        $maxSeconds ??= $cli ? self::CLI_MAX_SECONDS : self::WEB_MAX_SECONDS;
        $maxMessages ??= $cli ? self::CLI_MAX_MESSAGES : self::WEB_MAX_MESSAGES;
        $phpLimit = (int) ini_get('max_execution_time');
        if ($phpLimit > 0) {
            $maxSeconds = min($maxSeconds, max(1, intdiv($phpLimit, 2)));
        }
        $deadline = microtime(true) + $maxSeconds;

        $this->releaseAbandonedClaims();

        $attempted = 0;
        while ($attempted < $maxMessages && microtime(true) < $deadline) {
            $candidates = $this->dueRows($batch, min(self::PAGE_SIZE, $maxMessages - $attempted));
            $claimedThisPage = 0;
            foreach ($candidates as $candidate) {
                if ($attempted >= $maxMessages || microtime(true) >= $deadline) {
                    break 2;
                }
                $row = $this->claim((int) $candidate['Id'], (int) $candidate['Attempts']);
                if ($row === null) {
                    continue;
                }
                $claimedThisPage++;
                $attempted++;
                $counts[$this->deliver($row)]++;
            }
            // Nothing due, or another run took every row of this page and is sending them.
            if ($claimedThisPage === 0) {
                break;
            }
        }

        if ($attempted > 0) {
            LoggerUtils::getAppLogger()->info('Email queue drained', $counts + ['batch' => $batch ?? '']);
        }

        return $counts;
    }

    /**
     * Where a batch stands. `due` is how many queued rows may be sent right now; the rest of
     * the queued rows wait for a retry at `nextAttempt`.
     *
     * @return array{batch: string, total: int, queued: int, sending: int, sent: int, failed: int, skipped: int, due: int, nextAttempt: ?string, done: bool, failures: list<array{address: string, error: string}>}|null null for an unknown batch
     */
    public function getBatchProgress(string $batch): ?array
    {
        $rows = EmailLogQuery::create()
            ->filterByBatch($batch)
            ->orderById()
            ->select(['Status', 'NextAttempt', 'Address', 'Error'])
            ->find()
            ->toArray();
        if ($rows === []) {
            return null;
        }

        $now = DateTimeUtils::getNowDateTime();
        $progress = [
            'batch'       => $batch,
            'total'       => count($rows),
            'queued'      => 0,
            'sending'     => 0,
            'sent'        => 0,
            'failed'      => 0,
            'skipped'     => 0,
            'due'         => 0,
            'nextAttempt' => null,
            'done'        => false,
            'failures'    => [],
        ];
        foreach ($rows as $row) {
            $status = (string) $row['Status'];
            if (in_array($status, EmailLogService::STATUSES, true)) {
                $progress[$status]++;
            }
            if ($status === EmailLogService::STATUS_FAILED) {
                $progress['failures'][] = ['address' => (string) $row['Address'], 'error' => (string) $row['Error']];
            }
            if ($status !== EmailLogService::STATUS_QUEUED) {
                continue;
            }
            $next = self::formatDateTime($row['NextAttempt']);
            if ($next === null || $next <= $now) {
                $progress['due']++;
            } elseif ($progress['nextAttempt'] === null || $next < $progress['nextAttempt']) {
                $progress['nextAttempt'] = $next;
            }
        }
        $progress['done'] = $progress['queued'] === 0 && $progress['sending'] === 0;

        return $progress;
    }

    /**
     * Deletes every `queued` row, in every batch, so none of it is sent. Rows being sent
     * right now (`sending`) belong to a run and are left alone, as is the history of
     * mail that was sent, failed or skipped.
     *
     * @return int how many rows were deleted
     */
    public function deleteQueued(User $deletedBy): int
    {
        $deleted = EmailLogQuery::create()
            ->filterByStatus(EmailLogService::STATUS_QUEUED)
            ->delete();

        LoggerUtils::getAppLogger()->info('Email queue deleted', [
            'deletedBy' => $deletedBy->getUserName(),
            'userId'    => (int) $deletedBy->getId(),
            'deleted'   => $deleted,
        ]);

        return $deleted;
    }

    /** The batch's first row (it carries the sender), or null for an unknown batch. */
    public function findBatch(string $batch): ?EmailLog
    {
        return EmailLogQuery::create()->filterByBatch($batch)->orderById()->findOne();
    }

    /**
     * @return list<array{Id: int, Attempts: int}>
     */
    private function dueRows(?string $batch, int $limit): array
    {
        $query = EmailLogQuery::create()
            ->filterByStatus(EmailLogService::STATUS_QUEUED)
            ->filterByNextAttempt(DateTimeUtils::getNowDateTime(), Criteria::LESS_EQUAL);
        if ($batch !== null) {
            $query->filterByBatch($batch);
        }

        return $query->orderById()
            ->limit(max(1, $limit))
            ->select(['Id', 'Attempts'])
            ->find()
            ->toArray();
    }

    /**
     * Takes one row for this run. Only one concurrent UPDATE can move a row out of
     * `queued`, so a row another run claimed first comes back null here.
     */
    private function claim(int $id, int $attempts): ?EmailLog
    {
        $now = DateTimeUtils::getToday();
        $claimed = EmailLogQuery::create()
            ->filterById($id)
            ->filterByStatus(EmailLogService::STATUS_QUEUED)
            ->filterByAttempts($attempts)
            ->filterByNextAttempt($now->format('Y-m-d H:i:s'), Criteria::LESS_EQUAL)
            ->update([
                'Status'      => EmailLogService::STATUS_SENDING,
                'Attempts'    => $attempts + 1,
                'NextAttempt' => (clone $now)->modify('+' . self::CLAIM_MINUTES . ' minutes')->format('Y-m-d H:i:s'),
            ]);
        if ($claimed !== 1) {
            return null;
        }
        EmailLogTableMap::removeInstanceFromPool($id);

        return EmailLogQuery::create()->findPk($id);
    }

    /**
     * Sends one claimed row. BaseEmail::send() records sent or failed on the row; a
     * failure that may pass later is then put back in the queue.
     *
     * @return 'sent'|'failed'|'retrying'
     */
    private function deliver(EmailLog $row): string
    {
        $permanent = false;
        try {
            $email = new ComposerEmail(
                (string) $row->getAddress(),
                $this->recipientName($row),
                (string) $row->getSubject(),
                '',
                (string) $row->getBody(),
            );
            $email->setQueuedLogRow($row);
            if ($email->send()) {
                return 'sent';
            }
            $permanent = $email->isPermanentFailure();
            $error = $email->getError();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $error = trim($error) !== '' ? $error : gettext('Unknown delivery error');
        $retry = !$permanent && (int) $row->getAttempts() < self::MAX_ATTEMPTS;
        $row->setStatus($retry ? EmailLogService::STATUS_QUEUED : EmailLogService::STATUS_FAILED);
        $row->setError($error);
        $row->setNextAttempt(
            $retry
                ? DateTimeUtils::getToday()->modify('+' . self::RETRY_DELAY_MINUTES[(int) $row->getAttempts()] . ' minutes')->format('Y-m-d H:i:s')
                : null
        );
        $row->save();

        LoggerUtils::getAppLogger()->warning('Queued email not sent', [
            'emailLogId' => $row->getId(),
            'attempts'   => $row->getAttempts(),
            'retrying'   => $retry,
            'error'      => $error,
        ]);

        return $retry ? 'retrying' : 'failed';
    }

    /**
     * Rows left `sending` by a run that died. A row that has had its last attempt fails;
     * the others go back in the queue. The message may already have been accepted when the
     * run died, so this can send a copy twice; losing it silently would be worse.
     */
    private function releaseAbandonedClaims(): void
    {
        $now = DateTimeUtils::getNowDateTime();
        $interrupted = gettext('Sending was interrupted before the server answered.');

        EmailLogQuery::create()
            ->filterByStatus(EmailLogService::STATUS_SENDING)
            ->filterByNextAttempt($now, Criteria::LESS_THAN)
            ->filterByAttempts(self::MAX_ATTEMPTS, Criteria::GREATER_EQUAL)
            ->update(['Status' => EmailLogService::STATUS_FAILED, 'Error' => $interrupted, 'NextAttempt' => null]);

        EmailLogQuery::create()
            ->filterByStatus(EmailLogService::STATUS_SENDING)
            ->filterByNextAttempt($now, Criteria::LESS_THAN)
            ->update(['Status' => EmailLogService::STATUS_QUEUED, 'Error' => $interrupted, 'NextAttempt' => $now]);
    }

    /** The To name, read at send time from the record the row is for. */
    private function recipientName(EmailLog $row): string
    {
        if ($row->getPerId() !== null) {
            $person = PersonQuery::create()->findPk((int) $row->getPerId());
            if ($person !== null) {
                return $person->getFullName();
            }
        }
        if ($row->getFamId() !== null) {
            $family = FamilyQuery::create()->findPk((int) $row->getFamId());
            if ($family !== null) {
                return sprintf(gettext('%s Family'), $family->getName());
            }
        }

        return '';
    }

    private static function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value === null || $value === '' ? null : (string) $value;
    }
}
