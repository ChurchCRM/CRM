<?php

namespace ChurchCRM\Portal\Permissions\Rules;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Portal\Permissions\BulkPortalRule;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalRuleGrant;
use ChurchCRM\Portal\Permissions\PortalScope;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Adult of the family (member sign-in design §4 "Teenagers", §8.3): a head of
 * household or spouse, by the church's `sDirRoleHead` and `sDirRoleSpouse`, who
 * is 18 or older.
 *
 * Age is counted the way the member rule counts it: from the birthday itself;
 * with only a birth year, from the January after the 18th birthday's year; with
 * no birth year, a head of household or spouse counts as an adult.
 *
 * Like `Family::getAdults()`, someone proposed for an existing family counts
 * only once staff approve them; a family that self-registered keeps its adults.
 */
final class FamilyAdultRule implements BulkPortalRule
{
    public const KEY = 'core.family-adult';
    public const ADULT_AGE = 18;

    private const CAPABILITIES = [
        CorePortalCapabilities::FAMILY_CONTACTS_VIEW,
        CorePortalCapabilities::FAMILY_EDIT,
        CorePortalCapabilities::FAMILY_CONFIRM,
    ];

    public function getKey(): string
    {
        return self::KEY;
    }

    public function grantsFor(Person $person): iterable
    {
        $family = $person->getFamily();
        if (!$family instanceof Family || !self::isAdult($person, null, $family)) {
            return;
        }

        yield from self::grants((int) $family->getId(), (string) $family->getName());
    }

    public function getSourceName(): string
    {
        return gettext('Adult of the family');
    }

    public function getCapabilityKeys(): array
    {
        return self::CAPABILITIES;
    }

    public function grantsForPeople(array $personIds): iterable
    {
        $roleIds = self::headAndSpouseRoleIds();
        if ($roleIds === []) {
            return;
        }
        $today = new DateTimeImmutable('today', DateTimeUtils::getConfiguredTimezone());

        foreach (array_chunk($personIds, 1000) as $chunk) {
            $rows = PersonQuery::create()
                ->filterById($chunk, Criteria::IN)
                ->filterByFmrId($roleIds, Criteria::IN)
                ->joinFamily(null, Criteria::INNER_JOIN)
                ->withColumn('Family.Name', 'FamilyName')
                ->withColumn('Family.EnteredBy', 'FamilyEnteredBy')
                ->select(['Id', 'FamId', 'FmrId', 'BirthYear', 'BirthMonth', 'BirthDay', 'NeedsReview', 'FamilyName', 'FamilyEnteredBy'])
                ->find();
            foreach ($rows as $row) {
                if (Family::proposedMemberAwaitsReview((bool) $row['NeedsReview'], $row['FamilyEnteredBy'] === null ? null : (int) $row['FamilyEnteredBy'])) {
                    continue;
                }
                if (self::isAdultBy((int) $row['FmrId'], (int) $row['BirthYear'], (int) $row['BirthMonth'], (int) $row['BirthDay'], $today)) {
                    foreach (self::grants((int) $row['FamId'], (string) $row['FamilyName']) as $grant) {
                        yield (int) $row['Id'] => $grant;
                    }
                }
            }
        }
    }

    public static function isAdult(Person $person, ?DateTimeImmutable $today = null, ?Family $family = null): bool
    {
        $family ??= $person->getFamily();
        if ($family instanceof Family && Family::proposedMemberAwaitsReview((bool) $person->getNeedsReview(), $family->getEnteredBy())) {
            return false;
        }

        return self::isAdultBy(
            (int) $person->getFmrId(),
            (int) $person->getBirthYear(),
            (int) $person->getBirthMonth(),
            (int) $person->getBirthDay(),
            $today,
        );
    }

    private static function isAdultBy(int $roleId, int $birthYear, int $month, int $day, ?DateTimeImmutable $today = null): bool
    {
        if (!in_array($roleId, self::headAndSpouseRoleIds(), true)) {
            return false;
        }

        if ($birthYear <= 0) {
            return true;
        }

        $today ??= new DateTimeImmutable('today', DateTimeUtils::getConfiguredTimezone());
        $cutoffYear = (int) $today->format('Y') - self::ADULT_AGE;
        if ($birthYear !== $cutoffYear) {
            return $birthYear < $cutoffYear;
        }

        if ($month <= 0 || $day <= 0) {
            return false;
        }

        return [$month, $day] <= [(int) $today->format('n'), (int) $today->format('j')];
    }

    /**
     * @return list<PortalRuleGrant>
     */
    private static function grants(int $familyId, string $familyName): array
    {
        $scope = PortalScope::family($familyId);
        $label = sprintf(gettext('Adult of the %s family'), $familyName);

        return array_map(static fn (string $capability): PortalRuleGrant => new PortalRuleGrant($capability, $scope, $label), self::CAPABILITIES);
    }

    /**
     * @return int[]
     */
    private static function headAndSpouseRoleIds(): array
    {
        $ids = [];
        foreach (['sDirRoleHead', 'sDirRoleSpouse'] as $setting) {
            foreach (explode(',', (string) SystemConfig::getValue($setting)) as $part) {
                $part = trim($part);
                if ($part !== '' && ctype_digit($part)) {
                    $ids[] = (int) $part;
                }
            }
        }

        return $ids;
    }
}
