<?php

namespace ChurchCRM\Portal\Permissions\Rules;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalRule;
use ChurchCRM\Portal\Permissions\PortalRuleGrant;
use ChurchCRM\Portal\Permissions\PortalScope;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;

/**
 * Adult of the family (member sign-in design §4 "Teenagers", §8.3): a head of
 * household or spouse, by the church's `sDirRoleHead` and `sDirRoleSpouse`, who
 * is 18 or older.
 *
 * Age is counted the way the member rule counts it: from the birthday itself;
 * with only a birth year, from the January after the 18th birthday's year; with
 * no birth year, a head of household or spouse counts as an adult.
 */
final class FamilyAdultRule implements PortalRule
{
    public const KEY = 'core.family-adult';
    public const ADULT_AGE = 18;

    public function getKey(): string
    {
        return self::KEY;
    }

    public function grantsFor(Person $person): iterable
    {
        $family = $person->getFamily();
        if (!$family instanceof Family || !self::isAdult($person)) {
            return;
        }

        $scope = PortalScope::family((int) $family->getId());
        $label = sprintf(gettext('Adult of the %s family'), (string) $family->getName());
        foreach ([
            CorePortalCapabilities::FAMILY_CONTACTS_VIEW,
            CorePortalCapabilities::FAMILY_EDIT,
            CorePortalCapabilities::FAMILY_CONFIRM,
        ] as $capability) {
            yield new PortalRuleGrant($capability, $scope, $label);
        }
    }

    public static function isAdult(Person $person, ?DateTimeImmutable $today = null): bool
    {
        if (!in_array((int) $person->getFmrId(), self::headAndSpouseRoleIds(), true)) {
            return false;
        }

        $birthYear = (int) $person->getBirthYear();
        if ($birthYear <= 0) {
            return true;
        }

        $today ??= new DateTimeImmutable('today', DateTimeUtils::getConfiguredTimezone());
        $cutoffYear = (int) $today->format('Y') - self::ADULT_AGE;
        if ($birthYear !== $cutoffYear) {
            return $birthYear < $cutoffYear;
        }

        $month = (int) $person->getBirthMonth();
        $day = (int) $person->getBirthDay();
        if ($month <= 0 || $day <= 0) {
            return false;
        }

        return [$month, $day] <= [(int) $today->format('n'), (int) $today->format('j')];
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
