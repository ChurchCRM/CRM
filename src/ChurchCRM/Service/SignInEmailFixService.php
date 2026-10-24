<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\EmailChangeChannel;
use ChurchCRM\Portal\Auth\EmailChangeRefusedException;
use ChurchCRM\Portal\Auth\MemberSignInRule;
use ChurchCRM\Portal\Auth\SignInEmailPolicy;
use ChurchCRM\Utils\LoggerUtils;
use DateTime;
use Propel\Runtime\ActiveQuery\Criteria;
use Throwable;

/**
 * The office's release-day tools on "Email needs attention" (member sign-in
 * design §4, §9): Fix shared emails, and Confirm selected. Every change goes
 * through SignInEmailPolicy, so it follows the rules, and is audited, as the
 * same change on the person's page.
 */
final class SignInEmailFixService
{
    public const FIX = 'fix';
    public const BY_HAND = 'by_hand';
    public const BY_HAND_FAMILIES = 'families';
    public const BY_HAND_NO_ADULT = 'no_adult';

    /** The most rows one request may apply or confirm. */
    public const MAX_ROWS = 500;

    private readonly MemberSignInRule $rule;
    private readonly SignInEmailPolicy $policy;

    public function __construct(?MemberSignInRule $rule = null, ?SignInEmailPolicy $policy = null)
    {
        $this->rule = $rule ?? MemberSignInRule::fromSettings();
        $this->policy = $policy ?? new SignInEmailPolicy();
    }

    /**
     * Every shared address that stops sign-in, and what Fix shared emails
     * would do with it. When everyone sharing it is in one family, the head of
     * household among them keeps it, else the spouse, the oldest first (the
     * order of §10.1), and the rest lose it. Other addresses are fixed by
     * hand. A row in which anyone has a staff account or given portal grants
     * needs an administrator (§9).
     *
     * @return list<array<string, mixed>>
     */
    public function sharedEmailPlan(User $actor): array
    {
        $shared = (new MemberSignInListService($this->rule))->sharedEmails();
        if ($shared === []) {
            return [];
        }

        $groups = [];
        $people = $this->rule->maySignInQuery()
            ->where('LOWER(TRIM(Person.Email)) IN ?', $shared)
            ->withColumn('Family.Name', 'FamilyName')
            ->orderById()
            ->find();
        foreach ($people as $person) {
            $groups[MemberSignInRule::normalizeEmail((string) $person->getEmail())][] = $person;
        }
        $personIds = array_map(static fn (Person $person): int => (int) $person->getId(), iterator_to_array($people));
        $needAdministrator = array_flip($this->policy->peopleNeedingAdministrator($personIds));
        $signedIn = [];
        foreach (PersonSigninQuery::create()->filterByPersonId($personIds, Criteria::IN)->filterByProvenAt(null, Criteria::ISNOTNULL)->find() as $state) {
            $signedIn[(int) $state->getPersonId()] = true;
        }
        $roleNames = $this->familyRoleNames();

        $rows = [];
        foreach ($groups as $address => $sharers) {
            if (count($sharers) < 2) {
                continue;
            }
            $describe = static fn (Person $person): array => [
                'personId' => (int) $person->getId(),
                'name' => $person->getFullName(),
                'familyId' => self::familyId($person),
                'familyName' => self::familyId($person) === null ? null : (string) $person->getVirtualColumn('FamilyName'),
                'role' => $roleNames[(int) $person->getFmrId()] ?? gettext('No family role'),
                'signedIn' => isset($signedIn[(int) $person->getId()]),
            ];
            $rows[] = $this->planRow($address, $sharers, $describe, $actor, array_any(
                $sharers,
                static fn (Person $person): bool => isset($needAdministrator[(int) $person->getId()]),
            ));
        }

        usort($rows, static fn (array $a, array $b): int => [$a['status'] === self::FIX ? 0 : 1, (string) $a['familyName'], $a['address']]
            <=> [$b['status'] === self::FIX ? 0 : 1, (string) $b['familyName'], $b['address']]);

        return $rows;
    }

    /**
     * Applies the chosen rows of the plan. Each row names the address, its
     * keeper and the people who lose it, as the preview showed them; a row
     * whose sharers changed since then is skipped, and one the actor may not
     * apply is refused. The people who lose it have their personal email
     * cleared through the staff email-change rule (§9).
     *
     * @param list<array{email: string, keeperId: int, loserIds: int[]}> $requested
     *
     * @return array{changed: list<array<string, mixed>>, skipped: list<array<string, mixed>>, refused: list<array<string, mixed>>, peopleChanged: int}
     */
    public function fixSharedEmails(array $requested, User $actor): array
    {
        $plan = [];
        foreach ($this->sharedEmailPlan($actor) as $row) {
            $plan[$row['address']] = $row;
        }

        $result = ['changed' => [], 'skipped' => [], 'refused' => [], 'peopleChanged' => 0];
        $seen = [];
        foreach ($requested as $wanted) {
            $address = MemberSignInRule::normalizeEmail($wanted['email']);
            if (isset($seen[$address])) {
                continue;
            }
            $seen[$address] = true;
            $row = $plan[$address] ?? null;
            $outcome = ['email' => $wanted['email']];

            if ($row === null) {
                $result['skipped'][] = $outcome + ['reason' => gettext('This address is no longer shared by people who may sign in.')];
                continue;
            }
            $outcome['familyName'] = $row['familyName'];
            if (!$row['canApply']) {
                $result['refused'][] = $outcome + ['reason' => $row['refusal']];
                continue;
            }
            $loserIds = array_column($row['losers'], 'personId');
            $wantedLosers = array_map('intval', $wanted['loserIds']);
            sort($loserIds);
            sort($wantedLosers);
            if ((int) $wanted['keeperId'] !== $row['keeper']['personId'] || $loserIds !== $wantedLosers) {
                $result['skipped'][] = $outcome + ['reason' => gettext('The people sharing this address have changed. Open Fix shared emails again.')];
                continue;
            }

            try {
                $this->clearEmails($loserIds, $actor);
            } catch (Throwable $e) {
                LoggerUtils::getAppLogger()->error('Fix shared emails could not change an address', ['exception' => $e]);
                $result['refused'][] = $outcome + ['reason' => gettext('This address could not be changed. Open Fix shared emails again to see where it stands.')];
                continue;
            }
            $result['changed'][] = $outcome + ['keeper' => $row['keeper'], 'losers' => $row['losers']];
            $result['peopleChanged'] += count($loserIds);
        }

        return $result;
    }

    /**
     * Confirm selected: each unconfirmed address through the same check and
     * audit as the person page's Confirm, recorded as a bulk confirmation.
     *
     * @param list<array{personId: int, email: string}> $requested
     *
     * @return array{confirmed: list<array<string, mixed>>, skipped: list<array<string, mixed>>, refused: list<array<string, mixed>>}
     */
    public function confirmEmails(array $requested, User $actor): array
    {
        $byId = [];
        foreach ($requested as $wanted) {
            $byId[(int) $wanted['personId']] ??= (string) $wanted['email'];
        }
        $people = [];
        if ($byId !== []) {
            foreach (PersonQuery::create()->filterById(array_keys($byId), Criteria::IN)->find() as $person) {
                $people[(int) $person->getId()] = $person;
            }
        }

        $result = ['confirmed' => [], 'skipped' => [], 'refused' => []];
        foreach ($byId as $personId => $email) {
            $person = $people[$personId] ?? null;
            $outcome = ['personId' => $personId, 'name' => $person?->getFullName(), 'email' => $email];
            if (!$person instanceof Person) {
                $result['skipped'][] = $outcome + ['reason' => gettext('This person no longer exists.')];
                continue;
            }
            try {
                $this->policy->confirmByStaff($person, $email, $actor, SignInEmailPolicy::CONFIRMED_IN_BULK);
                $result['confirmed'][] = $outcome;
            } catch (EmailChangeRefusedException $e) {
                $result[$e->getStatus() === 403 ? 'refused' : 'skipped'][] = $outcome + ['reason' => $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * @param Person[]                   $sharers
     * @param callable(Person): array<string, mixed> $describe
     *
     * @return array<string, mixed>
     */
    private function planRow(string $address, array $sharers, callable $describe, User $actor, bool $needsAdministrator): array
    {
        $familyIds = array_unique(array_map(static fn (Person $person): int => self::familyId($person) ?? 0, $sharers));
        $keeper = null;
        $reason = null;
        if (count($familyIds) !== 1 || in_array(0, $familyIds, true)) {
            $reason = self::BY_HAND_FAMILIES;
        } else {
            $keeper = $this->keeper($sharers);
            $reason = $keeper === null ? self::BY_HAND_NO_ADULT : null;
        }

        $first = $keeper ?? $sharers[0];
        $refusal = match (true) {
            $reason !== null => self::byHandLabel($reason),
            $needsAdministrator && !$actor->isAdmin() => gettext('Only an administrator can fix this address: someone sharing it has a staff account or portal permissions.'),
            default => null,
        };

        return [
            'address' => $address,
            'email' => trim((string) $first->getEmail()),
            'status' => $reason === null ? self::FIX : self::BY_HAND,
            'reason' => $reason,
            'reasonLabel' => $reason === null ? null : self::byHandLabel($reason),
            'familyId' => $reason === self::BY_HAND_FAMILIES ? null : self::familyId($first),
            'familyName' => $reason === self::BY_HAND_FAMILIES ? null : (string) $first->getVirtualColumn('FamilyName'),
            'keeper' => $keeper === null ? null : $describe($keeper),
            'losers' => $keeper === null ? [] : array_values(array_map($describe, array_filter($sharers, static fn (Person $person): bool => $person !== $keeper))),
            'people' => array_map($describe, $sharers),
            'needsAdministrator' => $needsAdministrator,
            'canApply' => $refusal === null,
            'refusal' => $refusal,
        ];
    }

    /**
     * The head of household among the sharers, else the spouse; the oldest
     * when there is more than one.
     *
     * @param Person[] $sharers
     */
    private function keeper(array $sharers): ?Person
    {
        foreach (['sDirRoleHead', 'sDirRoleSpouse'] as $roleSetting) {
            $roleIds = MemberSignInRule::idList(SystemConfig::getValue($roleSetting));
            $candidates = array_values(array_filter($sharers, static fn (Person $person): bool => in_array((int) $person->getFmrId(), $roleIds, true)));
            if ($candidates !== []) {
                usort($candidates, static fn (Person $a, Person $b): int => Family::ageSortKey($a) <=> Family::ageSortKey($b));

                return $candidates[0];
            }
        }

        return null;
    }

    /**
     * @param int[] $personIds
     */
    private function clearEmails(array $personIds, User $actor): void
    {
        foreach (PersonQuery::create()->filterById($personIds, Criteria::IN)->find() as $person) {
            $oldEmail = $person->getEmail();
            if ($this->policy->changeRefusal($person, null, $actor) !== null) {
                throw new EmailChangeRefusedException('Refused while clearing a shared email', 403);
            }
            $person->setEmail(null)
                ->setDateLastEdited(new DateTime())
                ->setEditedBy((int) $actor->getPersonId())
                ->save();
            $this->policy->recordChange($person, $oldEmail, EmailChangeChannel::SHARED_EMAIL_FIX, $actor);
        }
    }

    public static function byHandLabel(string $reason): string
    {
        return $reason === self::BY_HAND_FAMILIES
            ? gettext('The people sharing it are in different families, or not in a family')
            : gettext('No head of household or spouse shares it');
    }

    /**
     * @return array<int, string>
     */
    private function familyRoleNames(): array
    {
        $names = [];
        foreach (ListOptionQuery::create()->filterById(2)->find() as $option) {
            $names[(int) $option->getOptionId()] = (string) $option->getOptionName();
        }

        return $names;
    }

    private static function familyId(Person $person): ?int
    {
        $familyId = (int) $person->getFamId();

        return $familyId > 0 ? $familyId : null;
    }
}
