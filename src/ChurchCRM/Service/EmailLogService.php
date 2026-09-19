<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\EmailLog;
use ChurchCRM\model\ChurchCRM\EmailLogQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use PHPMailer\PHPMailer\PHPMailer;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Writes and reads the email history (email_log_eml): one row per recipient for every
 * email that passes through BaseEmail::send(). Writing never throws: a logging failure is
 * reported to the app log and the send result is returned to the caller untouched.
 */
class EmailLogService
{
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;

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
     */
    public function logSend(
        PHPMailer $mail,
        string $status,
        string $kind,
        bool $storeBody,
        ?int $personId = null,
        ?int $familyId = null,
        ?int $userId = null,
    ): void {
        try {
            $addresses = array_merge($mail->getToAddresses(), $mail->getCcAddresses(), $mail->getBccAddresses());
            if ($addresses === []) {
                return;
            }
            $error = $status === self::STATUS_FAILED ? trim((string) $mail->ErrorInfo) : '';
            $messageId = $status === self::STATUS_SENT ? (string) $mail->getLastMessageID() : '';
            $now = DateTimeUtils::getNowDateTime();

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
     * Finds the person (primary or work email) or family behind an address.
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
        } else {
            $family = FamilyQuery::create()->filterByEmail($address)->orderById()->findOne();
            if ($family !== null) {
                $link['familyId'] = (int) $family->getId();
            }
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
     * Newest-first page across everyone (admin view), optionally by status.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, limit: int, pages: int}
     */
    public function getRecent(?string $status = null, int $page = 1, int $limit = self::DEFAULT_PAGE_SIZE): array
    {
        $query = EmailLogQuery::create();
        if ($status !== null && $status !== '') {
            $query->filterByStatus($status);
        }

        return $this->page($query, $page, $limit);
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
        $sentBy = null;
        if ($row->getUsrId() !== null) {
            $user = UserQuery::create()->findPk($row->getUsrId());
            if ($user !== null) {
                $person = $user->getPerson();
                $sentBy = $person !== null ? $person->getFullName() : $user->getUserName();
            }
        }
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
            'error'        => $row->getError(),
            'dateSent'     => $row->getDateSent('Y-m-d H:i:s'),
            'hasBody'      => $row->getBody() !== null && $row->getBody() !== '',
        ];
        if ($withBody) {
            $out['body'] = $row->getBody();
        }

        return $out;
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
            'account.new'         => gettext('New account'),
            'account.reset'       => gettext('Password reset'),
            'account.reset-token' => gettext('Password reset link'),
            'account.locked'      => gettext('Account locked'),
            'account.unlocked'    => gettext('Account unlocked'),
            'account.deleted'     => gettext('Account deleted'),
            'test'                => gettext('SMTP test'),
            default               => $kind,
        };
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, limit: int, pages: int}
     */
    private function page(EmailLogQuery $query, int $page, int $limit): array
    {
        $limit = max(1, min(self::MAX_PAGE_SIZE, $limit));
        $page = max(1, $page);
        $total = (int) (clone $query)->count();
        $rows = [];
        foreach ($query->orderByDateSent(Criteria::DESC)->orderById(Criteria::DESC)->offset(($page - 1) * $limit)->limit($limit)->find() as $row) {
            $rows[] = $this->toArray($row);
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
