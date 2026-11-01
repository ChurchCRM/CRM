<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\EmailLog;
use ChurchCRM\model\ChurchCRM\EmailLogQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use PHPMailer\PHPMailer\PHPMailer;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Writes and reads the email history (email_log_eml): one row per recipient for every
 * email that passes through BaseEmail::send(). Writing never throws: a logging failure is
 * reported to the app log and the send result is returned to the caller untouched.
 * Composer messages start as queued rows (EmailQueueService) that the send then completes.
 */
class EmailLogService
{
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';

    public const STATUSES = [
        self::STATUS_SENT,
        self::STATUS_FAILED,
        self::STATUS_SKIPPED,
        self::STATUS_QUEUED,
        self::STATUS_SENDING,
    ];

    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;

    /** getLog() sentBy value for mail no user queued (birthday greetings, account email) */
    public const SENT_BY_AUTOMATIC = 'automatic';
    public const MAX_SEARCH_LENGTH = 100;

    /** @var array<string, array{personId: ?int, familyId: ?int}> per-request address cache */
    private array $resolved = [];

    /**
     * Logs one row per To/Cc/Bcc address of a message that was just handed to PHPMailer.
     *
     * @param string $status    one of the STATUS_* constants
     * @param string $kind      email class kind, e.g. composer, birthday, account.reset
     * @param bool   $storeBody whether the rendered body may be kept (never for account emails)
     * @param ?int   $personId  the person the message was addressed to, when the sender knows
     * @param ?int   $familyId  the family the message was addressed to, when the sender knows
     * @param ?int   $userId    the user who sent it (composer); NULL for automated sends
     * @param ?EmailLog $queuedRow the queued row this send completes; it is updated instead of adding rows
     */
    public function logSend(
        PHPMailer $mail,
        string $status,
        string $kind,
        bool $storeBody,
        ?int $personId = null,
        ?int $familyId = null,
        ?int $userId = null,
        ?EmailLog $queuedRow = null,
    ): void {
        try {
            $error = $status === self::STATUS_FAILED ? trim((string) $mail->ErrorInfo) : '';
            $messageId = $status === self::STATUS_SENT ? (string) $mail->getLastMessageID() : '';
            $now = DateTimeUtils::getNowDateTime();

            if ($queuedRow !== null) {
                $queuedRow->setStatus($status);
                $queuedRow->setError($error !== '' ? $error : null);
                $queuedRow->setMessageId($messageId !== '' ? mb_substr($messageId, 0, 255) : null);
                $queuedRow->setDateSent($now);
                $queuedRow->setNextAttempt(null);
                $queuedRow->save();

                return;
            }

            $addresses = array_merge($mail->getToAddresses(), $mail->getCcAddresses(), $mail->getBccAddresses());
            if ($addresses === []) {
                return;
            }

            foreach ($addresses as $entry) {
                $address = trim((string) ($entry[0] ?? ''));
                if ($address === '') {
                    continue;
                }
                $link = ['personId' => $personId, 'familyId' => $familyId];
                if ($personId === null && $familyId === null) {
                    $link = $this->resolveAddress($address);
                }

                $row = new EmailLog();
                $row->setPerId($link['personId']);
                $row->setFamId($link['familyId']);
                $row->setUsrId($userId);
                $row->setAddress(mb_substr($address, 0, 255));
                $row->setKind(mb_substr($kind, 0, 50));
                $row->setSubject(mb_substr((string) $mail->Subject, 0, 255));
                $row->setBody($storeBody ? (string) $mail->Body : null);
                $row->setStatus($status);
                $row->setError($error !== '' ? $error : null);
                $row->setMessageId($messageId !== '' ? mb_substr($messageId, 0, 255) : null);
                $row->setDateSent($now);
                $row->setAttempts($status === self::STATUS_SKIPPED ? 0 : 1);
                $row->save();
            }
        } catch (\Throwable $e) {
            LoggerUtils::getAppLogger()->error('Email log write failed', [
                'kind'  => $kind,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Finds the person (primary or work email) behind an address.
     *
     * @return array{personId: ?int, familyId: ?int}
     */
    public function resolveAddress(string $address): array
    {
        $key = strtolower($address);
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }
        $link = ['personId' => null, 'familyId' => null];
        $person = PersonQuery::create()->filterByEmail($address)->orderById()->findOne()
            ?? PersonQuery::create()->filterByWorkEmail($address)->orderById()->findOne();
        if ($person !== null) {
            $link['personId'] = (int) $person->getId();
        }
        $this->resolved[$key] = $link;

        return $link;
    }

    /**
     * Newest-first page of a person's history.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getForPerson(int $personId, int $page = 1, int $limit = self::DEFAULT_PAGE_SIZE): array
    {
        return $this->page(EmailLogQuery::create()->filterByPerId($personId), $page, $limit);
    }

    /**
     * Newest-first page of the mail that actually went out to a member (Member Portal):
     * `sent` rows only, in the member shape (no delivery status). Queued, sending, failed
     * and skipped mail never reached them.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getSentForMember(int $personId, int $page = 1, int $limit = self::DEFAULT_PAGE_SIZE): array
    {
        $query = EmailLogQuery::create()->filterByPerId($personId)->filterByStatus(self::STATUS_SENT);

        return $this->page($query, $page, $limit, true);
    }

    /** One sent row addressed to this member, or null: another person's row and an unsent row look the same. */
    public function findSentForMember(int $id, int $personId): ?EmailLog
    {
        return EmailLogQuery::create()
            ->filterById($id)
            ->filterByPerId($personId)
            ->filterByStatus(self::STATUS_SENT)
            ->findOne();
    }

    /**
     * Newest-first page of a family's history: rows for the family address plus rows for
     * its current members.
     *
     * @param int[] $memberPersonIds
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getForFamily(int $familyId, array $memberPersonIds, int $page = 1, int $limit = self::DEFAULT_PAGE_SIZE): array
    {
        $query = EmailLogQuery::create()->filterByFamId($familyId);
        if ($memberPersonIds !== []) {
            $query->_or()->filterByPerId($memberPersonIds, Criteria::IN);
        }

        return $this->page($query, $page, $limit);
    }

    /**
     * Newest-first page of the email log table: everyone's mail, or only the mail
     * $senderUserId queued. Every word of the search must appear in the subject, the
     * address or the recipient's name. Rows carry recipientName and recipientUrl, the
     * person (or else family) record the address belonged to.
     *
     * @param array{status?: ?string, kind?: ?string, sentBy?: int|string|null, search?: ?string} $filters
     *        sentBy is a user id or SENT_BY_AUTOMATIC
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getLog(array $filters, ?int $senderUserId = null, int $page = 1, int $limit = self::DEFAULT_PAGE_SIZE): array
    {
        $query = $this->logQuery($senderUserId);
        if (($filters['status'] ?? '') !== '') {
            $query->filterByStatus($filters['status']);
        }
        if (($filters['kind'] ?? '') !== '') {
            $query->filterByKind($filters['kind']);
        }
        $sentBy = $filters['sentBy'] ?? null;
        if ($sentBy === self::SENT_BY_AUTOMATIC) {
            $query->filterByUsrId(null, Criteria::ISNULL);
        } elseif (is_int($sentBy)) {
            $query->filterByUsrId($sentBy);
        }
        $words = preg_split('/\s+/u', trim((string) ($filters['search'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words !== []) {
            $query->joinPerson(null, Criteria::LEFT_JOIN)->joinFamily(null, Criteria::LEFT_JOIN);
            $matches = [];
            foreach ($words as $index => $word) {
                $like = '%' . addcslashes($word, '%_\\') . '%';
                $query->condition("subject$index", 'EmailLog.Subject LIKE ?', $like)
                    ->condition("address$index", 'EmailLog.Address LIKE ?', $like)
                    ->condition("first$index", 'Person.FirstName LIKE ?', $like)
                    ->condition("last$index", 'Person.LastName LIKE ?', $like)
                    ->condition("family$index", 'Family.Name LIKE ?', $like)
                    ->combine(["subject$index", "address$index", "first$index", "last$index", "family$index"], Criteria::LOGICAL_OR, "word$index");
                $matches[] = "word$index";
            }
            $query->where($matches);
        }

        $result = $this->page($query, $page, $limit);
        $result['rows'] = $this->withRecipients($result['rows']);

        return $result;
    }

    /**
     * What the email log table can filter by within the mail the viewer may see: the types
     * and senders that occur, and how many messages failed or wait in the queue.
     *
     * @return array{kinds: array<string, string>, senders: array<int, string>, failed: int, queued: int}
     */
    public function getLogFacets(?int $senderUserId = null): array
    {
        $kinds = [];
        foreach ($this->logQuery($senderUserId)->select('Kind')->distinct()->find() as $kind) {
            $kinds[(string) $kind] = self::kindLabel((string) $kind);
        }
        asort($kinds, SORT_NATURAL | SORT_FLAG_CASE);

        $senderIds = $this->logQuery($senderUserId)->filterByUsrId(null, Criteria::ISNOTNULL)->select('UsrId')->distinct()->find()->toArray();
        $senders = [];
        if ($senderIds !== []) {
            foreach (PersonQuery::create()->filterById($senderIds, Criteria::IN)->find() as $person) {
                $senders[(int) $person->getId()] = $person->getFullName();
            }
        }
        asort($senders, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'kinds'   => $kinds,
            'senders' => $senders,
            'failed'  => $this->logQuery($senderUserId)->filterByStatus(self::STATUS_FAILED)->count(),
            'queued'  => $this->logQuery($senderUserId)->filterByStatus(self::STATUS_QUEUED)->count(),
        ];
    }

    public function find(int $id): ?EmailLog
    {
        return EmailLogQuery::create()->findPk($id);
    }

    /**
     * API / view shape of one row. The body is included only when asked for, since it can
     * be large and the list views only need the subject.
     *
     * @return array<string, mixed>
     */
    public function toArray(EmailLog $row, bool $withBody = false): array
    {
        // A user id is the person id, so the sender's name outlives a deleted account.
        $sentBy = $row->getUsrId() === null ? null : PersonQuery::create()->findPk((int) $row->getUsrId())?->getFullName();
        $out = [
            'id'           => (int) $row->getId(),
            'personId'     => $row->getPerId() !== null ? (int) $row->getPerId() : null,
            'familyId'     => $row->getFamId() !== null ? (int) $row->getFamId() : null,
            'sentByUserId' => $row->getUsrId() !== null ? (int) $row->getUsrId() : null,
            'sentBy'       => $sentBy,
            'address'      => $row->getAddress(),
            'kind'         => $row->getKind(),
            'kindLabel'    => self::kindLabel($row->getKind()),
            'subject'      => $row->getSubject(),
            'status'       => $row->getStatus(),
            'statusLabel'  => self::statusLabel((string) $row->getStatus()),
            'attempts'     => (int) $row->getAttempts(),
            'error'        => $row->getError(),
            'dateSent'     => $row->getDateSent('Y-m-d H:i:s'),
            'hasBody'      => $row->getBody() !== null && $row->getBody() !== '',
        ];
        if ($withBody) {
            $out['body'] = $row->getBody();
        }

        return $out;
    }

    /**
     * The mail server's error can name its host or the account that failed to sign in,
     * so only administrators see it; others still see the failed status.
     *
     * @param array<string, mixed> $row from toArray()
     *
     * @return array<string, mixed>
     */
    public function forViewer(array $row, bool $isAdmin): array
    {
        if (!$isAdmin) {
            $row['error'] = null;
        }

        return $row;
    }

    /**
     * What a member may see of one of their own rows: toArray() without the delivery
     * status, attempts and server error, which are for staff.
     *
     * @return array<string, mixed>
     */
    public function toMemberArray(EmailLog $row, bool $withBody = false): array
    {
        return array_diff_key($this->toArray($row, $withBody), array_flip(['status', 'statusLabel', 'attempts', 'error']));
    }

    /** Human label for an eml_Status value. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_SENT    => gettext('Sent'),
            self::STATUS_FAILED  => gettext('Failed'),
            self::STATUS_SKIPPED => gettext('Skipped'),
            self::STATUS_QUEUED  => gettext('Queued'),
            self::STATUS_SENDING => gettext('Sending'),
            default              => $status,
        };
    }

    /** Human label for an eml_Kind value. */
    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'composer'            => gettext('Message'),
            'birthday'            => gettext('Birthday greeting'),
            'verify'              => gettext('Family verification'),
            'notification'        => gettext('Notification'),
            'notification.new-record' => gettext('New record notification'),
            'notification.portal-sign-in' => gettext('Member Portal sign-in notice'),
            'portal.sign-in'      => gettext('Member Portal sign-in code'),
            'account.new'         => gettext('New account'),
            'account.reset'       => gettext('Password reset'),
            'account.reset-token' => gettext('Password reset link'),
            'account.locked'      => gettext('Account locked'),
            'account.unlocked'    => gettext('Account unlocked'),
            'account.deleted'     => gettext('Account deleted'),
            'signin.email-notice' => gettext('Email address changed'),
            'signin.email-confirm' => gettext('Confirm email address'),
            'signin.passkey-added' => gettext('Passkey added'),
            'test'                => gettext('SMTP test'),
            // Volunteer v2 messages log the default kind, their class name.
            'volunteerassignment'   => gettext('Volunteer assignment'),
            'volunteerreminder'     => gettext('Volunteer reminder'),
            'volunteerdeclinealert' => gettext('Volunteer declined'),
            'volunteergapalert'     => gettext('Volunteers needed'),
            'volunteersignupconfirm' => gettext('Volunteer sign-up'),
            'volunteerswapproposed' => gettext('Substitute proposed'),
            'volunteerswapresolved' => gettext('Substitute decision'),
            'volunteerhelpoffer'    => gettext('Offer to help'),
            default               => gettext('Email'),
        };
    }

    private function logQuery(?int $senderUserId): EmailLogQuery
    {
        $query = EmailLogQuery::create();
        if ($senderUserId !== null) {
            $query->filterByUsrId($senderUserId);
        }

        return $query;
    }

    /**
     * @param list<array<string, mixed>> $rows toArray() rows
     *
     * @return list<array<string, mixed>>
     */
    private function withRecipients(array $rows): array
    {
        $personIds = array_values(array_unique(array_filter(array_column($rows, 'personId'))));
        $familyIds = array_values(array_unique(array_filter(array_column($rows, 'familyId'))));
        $people = [];
        if ($personIds !== []) {
            foreach (PersonQuery::create()->filterById($personIds, Criteria::IN)->find() as $person) {
                $people[(int) $person->getId()] = $person;
            }
        }
        $families = [];
        if ($familyIds !== []) {
            foreach (FamilyQuery::create()->filterById($familyIds, Criteria::IN)->find() as $family) {
                $families[(int) $family->getId()] = $family;
            }
        }

        foreach ($rows as &$row) {
            $person = $people[$row['personId'] ?? 0] ?? null;
            $family = $families[$row['familyId'] ?? 0] ?? null;
            $row['recipientName'] = $person?->getFullName() ?? $family?->getName();
            $row['recipientUrl'] = $person?->getViewURI() ?? $family?->getViewURI();
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, limit: int, pages: int}
     */
    private function page(EmailLogQuery $query, int $page, int $limit, bool $forMember = false): array
    {
        $limit = max(1, min(self::MAX_PAGE_SIZE, $limit));
        $page = max(1, $page);
        $total = (int) (clone $query)->count();
        $rows = [];
        foreach ($query->orderByDateSent(Criteria::DESC)->orderById(Criteria::DESC)->offset(($page - 1) * $limit)->limit($limit)->find() as $row) {
            $rows[] = $forMember ? $this->toMemberArray($row) : $this->toArray($row);
        }

        return [
            'rows'  => $rows,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => (int) max(1, ceil($total / $limit)),
        ];
    }
}
