<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\FamilyEmailMoveQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\model\ChurchCRM\PortalSigninAuditQuery;
use ChurchCRM\Portal\Auth\MemberSignInRule;
use ChurchCRM\Portal\Auth\SignInAuditEvent;
use ChurchCRM\Portal\Auth\SignInEmailPolicy;
use ChurchCRM\Portal\Auth\SignInMethod;
use ChurchCRM\Portal\Auth\SignInReason;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The lists on Admin → Member Portal → Members (member sign-in design §11.2,
 * A7, and the one-time "Family emails not moved" list of §10.1).
 *
 * "May sign in now" is the blocks for everyone and the member rule
 * (`MemberSignInRule::maySignInQuery()`) plus the email rule: a personal email
 * that no other person who may sign in shares, and that is not unconfirmed.
 * The people the member rule lets in but the email rule stops are "Email needs
 * attention". Every list is read a page at a time.
 */
final class MemberSignInListService
{
    public const MAY_SIGN_IN = 'may-sign-in';
    public const SIGNED_IN_RECENTLY = 'signed-in-recently';
    public const NEVER_SIGNED_IN = 'never-signed-in';
    public const EMAIL_ATTENTION = 'email-attention';
    public const FAMILIES_CONFIRMED = 'families-confirmed';
    public const FAMILY_EMAILS_NOT_MOVED = 'family-emails-not-moved';

    public const LISTS = [
        self::MAY_SIGN_IN,
        self::SIGNED_IN_RECENTLY,
        self::NEVER_SIGNED_IN,
        self::EMAIL_ATTENTION,
        self::FAMILIES_CONFIRMED,
        self::FAMILY_EMAILS_NOT_MOVED,
    ];

    public const EMAIL_PROBLEMS = [
        SignInReason::NO_EMAIL,
        SignInReason::SHARED_EMAIL,
        SignInReason::EMAIL_UNCONFIRMED,
    ];

    /** Kinds of unconfirmed address (§9): moved from the family email, or a replacement after the first sign-in. */
    public const UNCONFIRMED_MOVED = SignInEmailPolicy::SOURCE_FAMILY_EMAIL;
    public const UNCONFIRMED_REPLACEMENT = 'replacement';

    public const RECENT_DAYS = 30;
    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;

    private const SIGNIN = 'signin';
    private const NORMALIZED_EMAIL = 'LOWER(TRIM(Person.Email))';

    private readonly MemberSignInRule $rule;

    /** @var string[]|null */
    private ?array $sharedEmails = null;

    /**
     * @param bool $byAdministrator whether the reader is an administrator, for who may confirm an unconfirmed address (§9)
     */
    public function __construct(?MemberSignInRule $rule = null, private readonly bool $byAdministrator = true)
    {
        $this->rule = $rule ?? MemberSignInRule::fromSettings();
    }

    /**
     * How many rows each list has, and the family email move's outcomes.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $attention = $this->emailAttentionCounts();

        $moves = ['head' => 0, 'spouse' => 0, 'held' => 0, 'noted' => 0, 'pending' => 0];
        $outcomes = FamilyEmailMoveQuery::create()
            ->withColumn('COUNT(*)', 'Total')
            ->groupByOutcome()
            ->select(['Outcome', 'Total'])
            ->find();
        foreach ($outcomes as $row) {
            $moves[(string) $row['Outcome']] = (int) $row['Total'];
        }

        return [
            'recentDays' => self::RECENT_DAYS,
            self::MAY_SIGN_IN => $this->maySignInNowQuery()->count(),
            self::SIGNED_IN_RECENTLY => $this->signedInRecentlyQuery()->count(),
            self::NEVER_SIGNED_IN => $this->neverSignedInQuery()->count(),
            self::EMAIL_ATTENTION => array_sum($attention['emailProblems']),
            ...$attention,
            self::FAMILIES_CONFIRMED => $this->familiesConfirmedQuery()->count(),
            self::FAMILY_EMAILS_NOT_MOVED => $moves['noted'],
            'familyEmailMoves' => $moves + ['total' => array_sum($moves)],
        ];
    }

    /**
     * The "Email needs attention" counts: people per problem, the shared
     * addresses behind them, and the unconfirmed people by kind.
     *
     * @return array{emailProblems: array<string, int>, sharedAddresses: int, unconfirmedKinds: array<string, int>}
     */
    public function emailAttentionCounts(): array
    {
        $problems = [];
        foreach (self::EMAIL_PROBLEMS as $problem) {
            $problems[$problem->value] = $this->emailAttentionQuery($problem)->count();
        }
        $kinds = [];
        foreach ([self::UNCONFIRMED_MOVED, self::UNCONFIRMED_REPLACEMENT] as $kind) {
            $kinds[$kind] = $this->emailAttentionQuery(SignInReason::EMAIL_UNCONFIRMED, $kind)->count();
        }

        return [
            'emailProblems' => $problems,
            'sharedAddresses' => count($this->sharedEmails()),
            'unconfirmedKinds' => $kinds,
        ];
    }

    public function countMaySignInNow(): int
    {
        return $this->maySignInNowQuery()->count();
    }

    public function countNeverSignedIn(): int
    {
        return $this->neverSignedInQuery()->count();
    }

    /**
     * One page of a list.
     *
     * @return array{total: int, filtered: int, rows: list<array<string, mixed>>}
     *
     * @throws InvalidArgumentException for an unknown list or problem
     */
    public function page(string $list, int $start = 0, int $length = self::DEFAULT_PAGE_SIZE, string $search = '', string $problem = '', string $kind = ''): array
    {
        $start = max(0, $start);
        $length = max(1, min(self::MAX_PAGE_SIZE, $length));
        $search = trim($search);

        return match ($list) {
            self::MAY_SIGN_IN => $this->peoplePage($this->maySignInNowQuery(), $start, $length, $search),
            self::NEVER_SIGNED_IN => $this->peoplePage($this->neverSignedInQuery(), $start, $length, $search),
            self::SIGNED_IN_RECENTLY => $this->peoplePage($this->signedInRecentlyQuery(), $start, $length, $search, true),
            self::EMAIL_ATTENTION => $this->emailAttentionPage($this->problemFilter($problem), $this->kindFilter($kind), $start, $length, $search),
            self::FAMILIES_CONFIRMED => $this->familiesConfirmedPage($start, $length, $search),
            self::FAMILY_EMAILS_NOT_MOVED => $this->familyEmailsNotMovedPage($start, $length, $search),
            default => throw new InvalidArgumentException(gettext('There is no such list.')),
        };
    }

    public static function familyEmailReasonLabel(?string $reason): string
    {
        return match ($reason) {
            'both_have_email' => gettext('The head of household and spouse both have their own email'),
            'no_adult' => gettext('The family has no living, active head of household or spouse'),
            'other_person' => gettext('Another person already uses this address'),
            default => gettext('Not moved'),
        };
    }

    private function problemFilter(string $problem): ?SignInReason
    {
        if ($problem === '') {
            return null;
        }
        $reason = SignInReason::tryFrom($problem);
        if (!in_array($reason, self::EMAIL_PROBLEMS, true)) {
            throw new InvalidArgumentException(gettext('There is no such email problem.'));
        }

        return $reason;
    }

    private function kindFilter(string $kind): ?string
    {
        if ($kind === '') {
            return null;
        }
        if (!in_array($kind, [self::UNCONFIRMED_MOVED, self::UNCONFIRMED_REPLACEMENT], true)) {
            throw new InvalidArgumentException(gettext('There is no such kind of unconfirmed email.'));
        }

        return $kind;
    }

    /** The member rule and the blocks, with each person's sign-in row joined. */
    private function memberRuleQuery(): PersonQuery
    {
        return $this->rule->maySignInQuery()->joinPersonSignin(self::SIGNIN, Criteria::LEFT_JOIN);
    }

    private function maySignInNowQuery(): PersonQuery
    {
        $query = $this->memberRuleQuery()
            ->where("(Person.Email IS NOT NULL AND TRIM(Person.Email) <> '')")
            ->where('(signin.UnconfirmedEmail IS NULL OR LOWER(TRIM(signin.UnconfirmedEmail)) <> ' . self::NORMALIZED_EMAIL . ')');
        $shared = $this->sharedEmails();
        if ($shared !== []) {
            $query->where(self::NORMALIZED_EMAIL . ' NOT IN ?', $shared);
        }

        return $query;
    }

    private function neverSignedInQuery(): PersonQuery
    {
        return $this->maySignInNowQuery()->where('signin.LastSignIn IS NULL');
    }

    private function signedInRecentlyQuery(): PersonQuery
    {
        return PersonQuery::create()
            ->joinPersonSignin(self::SIGNIN, Criteria::INNER_JOIN)
            ->joinFamily(null, Criteria::LEFT_JOIN)
            ->where('signin.LastSignIn >= ?', $this->cutoff()->format('Y-m-d H:i:s'));
    }

    /**
     * The people the member rule lets in whose email stops them, in the order
     * the email rule checks: no email, shared, then unconfirmed. `$kind`
     * narrows the unconfirmed ones to UNCONFIRMED_MOVED or UNCONFIRMED_REPLACEMENT.
     */
    private function emailAttentionQuery(?SignInReason $problem = null, ?string $kind = null): PersonQuery
    {
        $query = $this->memberRuleQuery();
        $shared = $this->sharedEmails();
        $noEmail = "(Person.Email IS NULL OR TRIM(Person.Email) = '')";
        $unconfirmed = 'LOWER(TRIM(signin.UnconfirmedEmail)) = ' . self::NORMALIZED_EMAIL;

        if ($problem === SignInReason::NO_EMAIL) {
            return $query->where($noEmail);
        }
        if ($problem === SignInReason::SHARED_EMAIL) {
            return $shared === [] ? $query->where('1 = 0') : $query->where(self::NORMALIZED_EMAIL . ' IN ?', $shared);
        }
        if ($problem === SignInReason::EMAIL_UNCONFIRMED) {
            $query->where($unconfirmed);
            if ($kind === self::UNCONFIRMED_MOVED) {
                $query->where('signin.UnconfirmedSource = ?', self::UNCONFIRMED_MOVED);
            } elseif ($kind === self::UNCONFIRMED_REPLACEMENT) {
                $query->where('(signin.UnconfirmedSource IS NULL OR signin.UnconfirmedSource <> ?)', self::UNCONFIRMED_MOVED);
            }

            return $shared === [] ? $query : $query->where(self::NORMALIZED_EMAIL . ' NOT IN ?', $shared);
        }

        $query->condition('noEmail', $noEmail)->condition('unconfirmed', $unconfirmed);
        $conditions = ['noEmail', 'unconfirmed'];
        if ($shared !== []) {
            $query->condition('shared', self::NORMALIZED_EMAIL . ' IN ?', $shared);
            $conditions[] = 'shared';
        }

        return $query->where($conditions, Criteria::LOGICAL_OR);
    }

    /**
     * Personal emails, trimmed and in lower case, that two or more people who
     * may sign in share. Neither of them can use it (A2).
     *
     * @return string[]
     */
    public function sharedEmails(): array
    {
        if ($this->sharedEmails !== null) {
            return $this->sharedEmails;
        }

        $counts = [];
        $emails = $this->rule->maySignInQuery()
            ->where("(Person.Email IS NOT NULL AND TRIM(Person.Email) <> '')")
            ->select(['Email'])
            ->find();
        foreach ($emails as $email) {
            $normalized = MemberSignInRule::normalizeEmail((string) $email);
            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
        }

        return $this->sharedEmails = array_keys(array_filter($counts, static fn (int $count): bool => $count > 1));
    }

    private function searchPeople(PersonQuery $query, string $search): PersonQuery
    {
        if ($search === '') {
            return $query;
        }
        $like = '%' . addcslashes($search, '%_\\') . '%';

        return $query->where(
            "(Person.FirstName LIKE ? OR Person.LastName LIKE ? OR Person.Email LIKE ? OR CONCAT(Person.FirstName, ' ', Person.LastName) LIKE ?)",
            [$like, $like, $like, $like],
        );
    }

    /**
     * @return array{total: int, filtered: int, rows: list<array<string, mixed>>}
     */
    private function peoplePage(PersonQuery $query, int $start, int $length, string $search, bool $newestFirst = false): array
    {
        $total = (clone $query)->count();
        $query = $this->searchPeople($query, $search);
        $filtered = $search === '' ? $total : (clone $query)->count();

        $query->withColumn('Family.Name', 'FamilyName')
            ->withColumn('signin.LastSignIn', 'SignInLast')
            ->withColumn('signin.LastMethod', 'SignInMethod')
            ->withColumn('signin.LastActivity', 'SignInActivity')
            ->withColumn('signin.UnconfirmedEmail', 'SignInUnconfirmedEmail')
            ->withColumn('signin.UnconfirmedSource', 'SignInUnconfirmedSource')
            ->withColumn('signin.UnconfirmedAt', 'SignInUnconfirmedAt');
        if ($newestFirst) {
            $query->orderBy('SignInLast', Criteria::DESC);
        }
        $people = $query->orderByLastName()->orderByFirstName()->orderById()->offset($start)->limit($length)->find();

        $rows = [];
        foreach ($people as $person) {
            $rows[] = $this->personRow($person);
        }

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $rows];
    }

    /**
     * @return array{total: int, filtered: int, rows: list<array<string, mixed>>}
     */
    private function emailAttentionPage(?SignInReason $problem, ?string $kind, int $start, int $length, string $search): array
    {
        if ($problem !== SignInReason::EMAIL_UNCONFIRMED) {
            $kind = null;
        }
        $page = $this->peoplePage($this->emailAttentionQuery($problem, $kind), $start, $length, $search);
        $shared = array_flip($this->sharedEmails());

        $sharedOnPage = [];
        $unconfirmedOnPage = [];
        foreach ($page['rows'] as &$row) {
            $email = MemberSignInRule::normalizeEmail((string) $row['email']);
            $reason = match (true) {
                $email === '' => SignInReason::NO_EMAIL,
                isset($shared[$email]) => SignInReason::SHARED_EMAIL,
                default => SignInReason::EMAIL_UNCONFIRMED,
            };
            $row['problem'] = $reason->value;
            $row['problemLabel'] = $reason->label();
            $row['sharedWith'] = [];
            $row['canConfirmEmail'] = false;
            $row['confirmEmailRefusal'] = null;
            if ($reason === SignInReason::EMAIL_UNCONFIRMED) {
                $unconfirmedOnPage[] = $row['personId'];
            }
            if ($reason === SignInReason::SHARED_EMAIL) {
                $sharedOnPage[$email] = true;
            }
        }
        unset($row);

        if ($unconfirmedOnPage !== []) {
            $this->addConfirmRefusals($page['rows'], $unconfirmedOnPage);
        }

        if ($sharedOnPage !== []) {
            $holders = [];
            $people = $this->rule->maySignInQuery()
                ->where(self::NORMALIZED_EMAIL . ' IN ?', array_keys($sharedOnPage))
                ->orderByLastName()
                ->orderByFirstName()
                ->find();
            foreach ($people as $person) {
                $holders[MemberSignInRule::normalizeEmail((string) $person->getEmail())][] = ['personId' => (int) $person->getId(), 'name' => $person->getFullName()];
            }
            foreach ($page['rows'] as &$row) {
                if ($row['problem'] === SignInReason::SHARED_EMAIL->value) {
                    $email = MemberSignInRule::normalizeEmail((string) $row['email']);
                    $row['sharedWith'] = array_values(array_filter(
                        $holders[$email] ?? [],
                        static fn (array $holder): bool => $holder['personId'] !== $row['personId'],
                    ));
                }
            }
            unset($row);
        }

        return $page;
    }

    /**
     * Whether staff with this page may confirm each unconfirmed address here,
     * by the rules of member sign-in design §9.
     *
     * @param list<array<string, mixed>> $rows
     * @param int[]                       $personIds
     */
    private function addConfirmRefusals(array &$rows, array $personIds): void
    {
        $people = [];
        foreach (PersonQuery::create()->filterById($personIds, Criteria::IN)->find() as $person) {
            $people[(int) $person->getId()] = $person;
        }
        $states = [];
        foreach (PersonSigninQuery::create()->filterByPersonId($personIds, Criteria::IN)->find() as $state) {
            $states[(int) $state->getPersonId()] = $state;
        }
        $policy = new SignInEmailPolicy();

        foreach ($rows as &$row) {
            $person = $people[$row['personId']] ?? null;
            if ($row['problem'] !== SignInReason::EMAIL_UNCONFIRMED->value || !$person instanceof Person) {
                continue;
            }
            $refusal = $policy->confirmRefusal($person, $states[$row['personId']] ?? null, $this->byAdministrator);
            $row['canConfirmEmail'] = $refusal === null;
            $row['confirmEmailRefusal'] = $refusal;
        }
        unset($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function personRow(Person $person): array
    {
        $method = SignInMethod::tryFrom((string) $person->getVirtualColumn('SignInMethod'));
        $unconfirmedEmail = trim((string) $person->getVirtualColumn('SignInUnconfirmedEmail'));
        $unconfirmedSource = $unconfirmedEmail === '' ? null : $person->getVirtualColumn('SignInUnconfirmedSource');

        return [
            'personId' => (int) $person->getId(),
            'name' => $person->getFullName(),
            'familyId' => $person->getFamId() === null || (int) $person->getFamId() === 0 ? null : (int) $person->getFamId(),
            'familyName' => (string) $person->getVirtualColumn('FamilyName'),
            'email' => (string) $person->getEmail(),
            'lastSignIn' => self::moment($person->getVirtualColumn('SignInLast')),
            'lastSignInDisplay' => self::display($person->getVirtualColumn('SignInLast')),
            'lastMethod' => $method?->value,
            'lastMethodLabel' => $method?->label(),
            'lastActivity' => self::moment($person->getVirtualColumn('SignInActivity')),
            'lastActivityDisplay' => self::display($person->getVirtualColumn('SignInActivity')),
            'unconfirmedEmail' => $unconfirmedEmail === '' ? null : $unconfirmedEmail,
            'unconfirmedSource' => $unconfirmedSource,
            'unconfirmedSourceLabel' => $unconfirmedEmail === '' ? null : SignInEmailPolicy::sourceLabel($unconfirmedSource),
            'unconfirmedAt' => $unconfirmedEmail === '' ? null : self::moment($person->getVirtualColumn('SignInUnconfirmedAt')),
        ];
    }

    /**
     * A member's answer to "are these details still right?", from the portal
     * or the emailed link: a family note of type `verify` entered by
     * SELF_VERIFY. Staff verifying a family write a note entered by
     * themselves, which is not listed.
     */
    private function familiesConfirmedQuery(): NoteQuery
    {
        return NoteQuery::create()
            ->filterByType(PortalSelfService::VERIFY_NOTE_TYPE)
            ->filterByEnteredBy(Person::SELF_VERIFY)
            ->filterByDateEntered(['min' => $this->cutoff()->format('Y-m-d H:i:s')])
            ->joinFamily(null, Criteria::INNER_JOIN);
    }

    /**
     * @return array{total: int, filtered: int, rows: list<array<string, mixed>>}
     */
    private function familiesConfirmedPage(int $start, int $length, string $search): array
    {
        $query = $this->familiesConfirmedQuery();
        $total = (clone $query)->count();
        if ($search !== '') {
            $query->where('Family.Name LIKE ?', '%' . addcslashes($search, '%_\\') . '%');
        }
        $filtered = $search === '' ? $total : (clone $query)->count();

        $notes = $query->withColumn('Family.Name', 'FamilyName')
            ->orderByDateEntered(Criteria::DESC)
            ->orderById(Criteria::DESC)
            ->offset($start)
            ->limit($length)
            ->find();

        $confirmers = $this->portalConfirmations(array_map(static fn (Note $note): int => (int) $note->getId(), iterator_to_array($notes)));
        $names = [];
        $personIds = array_values(array_unique(array_column($confirmers, 'personId')));
        if ($personIds !== []) {
            foreach (PersonQuery::create()->filterById($personIds, Criteria::IN)->find() as $person) {
                $names[(int) $person->getId()] = $person->getFullName();
            }
        }

        $rows = [];
        foreach ($notes as $note) {
            $confirmation = $confirmers[(int) $note->getId()] ?? null;
            $result = $confirmation['result'] ?? (in_array((string) $note->getText(), ['No Changes', gettext('No Changes')], true)
                ? PortalSelfService::CONFIRM_NO_CHANGE
                : PortalSelfService::CONFIRM_CHANGE_NEEDED);
            $method = $confirmation === null ? null : SignInMethod::tryFrom((string) $confirmation['method']);
            $rows[] = [
                'noteId' => (int) $note->getId(),
                'familyId' => (int) $note->getFamId(),
                'familyName' => (string) $note->getVirtualColumn('FamilyName'),
                'confirmedAt' => self::moment($note->getDateEntered()),
                'confirmedAtDisplay' => self::display($note->getDateEntered()),
                'personId' => $confirmation['personId'] ?? null,
                'personName' => $confirmation === null ? null : ($names[$confirmation['personId']] ?? null),
                'method' => $method?->value,
                'methodLabel' => $method?->label(),
                'via' => $confirmation === null ? 'email_link' : 'portal',
                'viaLabel' => $confirmation === null ? gettext('Emailed verification link') : gettext('Member Portal'),
                'result' => $result,
                'resultLabel' => $result === PortalSelfService::CONFIRM_NO_CHANGE ? gettext('No changes') : gettext('Changes needed'),
            ];
        }

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $rows];
    }

    /**
     * Who confirmed in the portal, from each FAMILY_CONFIRMED audit row's
     * `note=` detail, by note id.
     *
     * @param int[] $noteIds
     *
     * @return array<int, array{personId: int, method: string|null, result: string|null}>
     */
    private function portalConfirmations(array $noteIds): array
    {
        if ($noteIds === []) {
            return [];
        }

        $wanted = array_flip($noteIds);
        $found = [];
        $rows = PortalSigninAuditQuery::create()
            ->filterByEvent(SignInAuditEvent::FAMILY_CONFIRMED->value)
            ->filterByTime(['min' => $this->cutoff()->modify('-1 day')->format('Y-m-d H:i:s')])
            ->filterByPersonId(null, Criteria::ISNOTNULL)
            ->find();
        foreach ($rows as $row) {
            $detail = self::parseDetail((string) $row->getDetail());
            $noteId = (int) ($detail['note'] ?? 0);
            if ($noteId > 0 && isset($wanted[$noteId])) {
                $found[$noteId] = [
                    'personId' => (int) $row->getPersonId(),
                    'method' => $row->getMethod(),
                    'result' => $detail['result'] ?? null,
                ];
            }
        }

        return $found;
    }

    /**
     * @return array{total: int, filtered: int, rows: list<array<string, mixed>>}
     */
    private function familyEmailsNotMovedPage(int $start, int $length, string $search): array
    {
        $query = FamilyEmailMoveQuery::create()
            ->filterByOutcome('noted')
            ->joinFamily(null, Criteria::LEFT_JOIN)
            ->joinPerson(null, Criteria::LEFT_JOIN);
        $total = (clone $query)->count();
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where('(Family.Name LIKE ? OR FamilyEmailMove.Email LIKE ?)', [$like, $like]);
        }
        $filtered = $search === '' ? $total : (clone $query)->count();

        $moves = $query->withColumn('Family.Name', 'FamilyName')
            ->withColumn('Person.FirstName', 'HolderFirstName')
            ->withColumn('Person.LastName', 'HolderLastName')
            ->orderBy('FamilyName')
            ->orderById()
            ->offset($start)
            ->limit($length)
            ->find();

        $rows = [];
        foreach ($moves as $move) {
            $holderId = $move->getPersonId() === null ? null : (int) $move->getPersonId();
            $rows[] = [
                'familyId' => (int) $move->getFamilyId(),
                'familyName' => (string) $move->getVirtualColumn('FamilyName'),
                'email' => (string) $move->getEmail(),
                'reason' => $move->getReason(),
                'reasonLabel' => self::familyEmailReasonLabel($move->getReason()),
                'holderId' => $holderId,
                'holderName' => $holderId === null ? null : trim($move->getVirtualColumn('HolderFirstName') . ' ' . $move->getVirtualColumn('HolderLastName')),
                'date' => self::moment($move->getDateMoved()),
                'dateDisplay' => self::display($move->getDateMoved()),
            ];
        }

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $rows];
    }

    /**
     * @return array<string, string>
     */
    public static function parseDetail(string $detail): array
    {
        $parts = [];
        foreach (explode(';', $detail) as $pair) {
            $pair = trim($pair);
            $equals = strpos($pair, '=');
            if ($equals > 0) {
                $parts[substr($pair, 0, $equals)] = substr($pair, $equals + 1);
            }
        }

        return $parts;
    }

    private function cutoff(): DateTimeImmutable
    {
        return (new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone()))->modify('-' . self::RECENT_DAYS . ' days');
    }

    private static function moment(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function display(mixed $value): string
    {
        $moment = self::moment($value);

        return $moment === null ? '' : DateTimeUtils::formatDate($moment, true);
    }
}
