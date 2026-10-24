<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSignin;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The member rule and the email rule (member sign-in design §4), set on
 * Admin → Member Portal → Sign-in. The member rule applies only to the ways in
 * whose `SignInMethod::appliesMemberRule()` is true; staff signing in at the
 * staff page and Login as User skip it.
 *
 * Age: a person qualifies from the day they reach the minimum age. With only a
 * birth year they qualify from the January after the year they reach it. With
 * no birth year only a head of household or spouse qualifies. A minimum age of
 * 0 means there is no age requirement.
 */
final class MemberSignInRule
{
    /**
     * @param int[] $familyRoleIds       family roles that may sign in; 0 is "no family role"
     * @param int[] $classificationIds   classifications that may sign in; 0 is Unassigned
     * @param int[] $headOrSpouseRoleIds the church's head-of-household and spouse roles
     */
    public function __construct(
        private readonly array $familyRoleIds,
        private readonly array $classificationIds,
        private readonly int $minimumAge,
        private readonly array $headOrSpouseRoleIds,
        private readonly DateTimeImmutable $today,
    ) {
    }

    public static function fromSettings(): self
    {
        return new self(
            self::idList(SystemConfig::getValue('sMemberSignInFamilyRoles')),
            self::idList(SystemConfig::getValue('sMemberSignInClassifications')),
            SystemConfig::getIntValue('iMemberSignInMinAge'),
            array_values(array_unique(array_merge(
                self::idList(SystemConfig::getValue('sDirRoleHead')),
                self::idList(SystemConfig::getValue('sDirRoleSpouse')),
            ))),
            new DateTimeImmutable('today', DateTimeUtils::getConfiguredTimezone()),
        );
    }

    /**
     * @return int[]
     */
    public static function idList(?string $value): array
    {
        $ids = [];
        foreach (explode(',', (string) $value) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $ids[] = (int) $part;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** The member rule alone; the blocks for everyone are PortalSignInRules'. */
    public function check(Person $person): ?SignInReason
    {
        $roleId = (int) $person->getFmrId();
        if (!in_array($roleId, $this->familyRoleIds, true)) {
            return SignInReason::FAMILY_ROLE;
        }
        if (!in_array((int) $person->getClsId(), $this->classificationIds, true)) {
            return SignInReason::CLASSIFICATION;
        }
        if ($this->minimumAge <= 0) {
            return null;
        }

        $birthYear = (int) $person->getBirthYear();
        if ($birthYear <= 0) {
            return in_array($roleId, $this->headOrSpouseRoleIds, true) ? null : SignInReason::AGE_UNKNOWN;
        }

        return $this->reachedMinimumAge($birthYear, (int) $person->getBirthMonth(), (int) $person->getBirthDay())
            ? null
            : SignInReason::UNDER_MINIMUM_AGE;
    }

    /**
     * Narrows an unaliased PersonQuery to the people the member rule lets in.
     */
    public function filter(PersonQuery $query): PersonQuery
    {
        $query->filterByFmrId($this->familyRoleIds, Criteria::IN)
            ->filterByClsId($this->classificationIds, Criteria::IN);

        if ($this->minimumAge <= 0) {
            return $query;
        }

        [$year, $month, $day] = $this->cutoff();
        $headOrSpouse = $this->headOrSpouseRoleIds === [] ? 'NULL' : implode(',', $this->headOrSpouseRoleIds);

        // Every value inlined here is an int computed on the server.
        return $query->where(sprintf(
            '((Person.BirthYear > 0 AND (Person.BirthYear < %1$d OR (Person.BirthYear = %1$d AND Person.BirthMonth > 0 AND Person.BirthDay > 0'
            . ' AND (Person.BirthMonth < %2$d OR (Person.BirthMonth = %2$d AND Person.BirthDay <= %3$d)))))'
            . ' OR ((Person.BirthYear IS NULL OR Person.BirthYear <= 0) AND Person.FmrId IN (%4$s)))',
            $year,
            $month,
            $day,
            $headOrSpouse,
        ));
    }

    /**
     * Everyone who may sign in now under the blocks for everyone and the
     * member rule, whatever their email. The admin lists add the email checks.
     */
    public function maySignInQuery(): PersonQuery
    {
        return $this->filter(PortalSignInRules::filterNotBlocked(PersonQuery::create()));
    }

    /**
     * The email rule: who, if anyone, may sign in with the address typed on the
     * sign-in page. Only the personal email matches, compared trimmed and in
     * lower case. When two people who would otherwise qualify share it, nobody
     * may use it. An unconfirmed address (§9) can't be used yet.
     */
    public function findSignInCandidate(string $typedEmail): SignInCandidate
    {
        $email = self::normalizeEmail($typedEmail);
        if ($email === '') {
            return SignInCandidate::refused($email, SignInReason::NO_MATCH);
        }

        $people = PersonQuery::create()
            ->where('LOWER(TRIM(Person.Email)) = ?', $email)
            ->orderById()
            ->find();
        if ($people->isEmpty()) {
            return SignInCandidate::refused($email, SignInReason::NO_MATCH);
        }

        $matchedIds = array_map(static fn (Person $person): int => (int) $person->getId(), iterator_to_array($people));
        $states = [];
        foreach (PersonSigninQuery::create()->filterByPersonId($matchedIds, Criteria::IN)->find() as $state) {
            $states[(int) $state->getPersonId()] = $state;
        }

        $qualifying = [];
        $firstRefusal = null;
        foreach ($people as $person) {
            $reason = PortalSignInRules::refusalReason($person, $states[(int) $person->getId()] ?? null, SignInMethod::EMAIL_CODE, $this);
            if ($reason === null) {
                $qualifying[] = $person;
            } else {
                $firstRefusal ??= [$person, $reason];
            }
        }

        if (count($qualifying) > 1) {
            return SignInCandidate::refused($email, SignInReason::SHARED_EMAIL, null, $matchedIds);
        }
        if ($qualifying === []) {
            return SignInCandidate::refused($email, $firstRefusal[1], $firstRefusal[0], $matchedIds);
        }

        $person = $qualifying[0];
        $state = $states[(int) $person->getId()] ?? null;
        if ($state instanceof PersonSignin && self::normalizeEmail((string) $state->getUnconfirmedEmail()) === $email) {
            return SignInCandidate::refused($email, SignInReason::EMAIL_UNCONFIRMED, $person, $matchedIds);
        }

        return SignInCandidate::allowed($email, $person);
    }

    /**
     * Whether this person may sign in with their own email now, and why not:
     * the blocks for everyone, the member rule, then the email rule.
     */
    public function statusFor(Person $person, ?PersonSignin $state = null): ?SignInReason
    {
        $state ??= PersonSigninQuery::create()->findPk($person->getId());
        $reason = PortalSignInRules::refusalReason($person, $state, SignInMethod::EMAIL_CODE, $this);
        if ($reason !== null) {
            return $reason;
        }

        $email = self::normalizeEmail((string) $person->getEmail());
        if ($email === '') {
            return SignInReason::NO_EMAIL;
        }

        return $this->findSignInCandidate($email)->refusal;
    }

    private function reachedMinimumAge(int $year, int $month, int $day): bool
    {
        [$cutoffYear, $cutoffMonth, $cutoffDay] = $this->cutoff();
        if ($year !== $cutoffYear) {
            return $year < $cutoffYear;
        }
        if ($month <= 0 || $day <= 0) {
            return false;
        }

        return $month < $cutoffMonth || ($month === $cutoffMonth && $day <= $cutoffDay);
    }

    /**
     * The latest birth date that has reached the minimum age today.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function cutoff(): array
    {
        $year = (int) $this->today->format('Y') - $this->minimumAge;
        $month = (int) $this->today->format('n');
        $day = (int) $this->today->format('j');
        if ($month === 2 && $day === 29 && !checkdate(2, 29, $year)) {
            $day = 28;
        }

        return [$year, $month, $day];
    }
}
