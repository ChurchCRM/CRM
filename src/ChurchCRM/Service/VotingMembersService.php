<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Classification;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\FiscalYearUtils;
use DateTimeImmutable;
use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;

class VotingMembersService
{
    public static function defaults(): array
    {
        return ['classList' => [], 'AsOf' => DateTimeUtils::getTodayDate(), 'MinimumAge' => '0',
            'MembershipMonths' => '0', 'RequireDonationYears' => '0', 'FYID' => (string) FiscalYearUtils::getCurrentFiscalYearId()];
    }

    public static function criteria(array $input): array
    {
        $input += self::defaults();
        $integer = static function (string $key, int $min, int $max) use ($input): int {
            $value = $input[$key];
            if (!is_scalar($value) || !preg_match('/^\d+$/D', (string) $value) || (int) $value < $min || (int) $value > $max) {
                throw new InvalidArgumentException(gettext('Enter whole numbers within the displayed limits.'));
            }

            return (int) $value;
        };
        $asOf = is_string($input['AsOf']) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $input['AsOf'])
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $input['AsOf']) : false;
        if (!$asOf || $asOf->format('Y-m-d') !== $input['AsOf'] || $asOf->format('Y') < '1900' || $asOf->format('Y') > '2200') {
            throw new InvalidArgumentException(gettext('Enter a valid review date between 1900 and 2200.'));
        }
        $options = [];
        foreach (Classification::getAll() as $option) {
            $options[(int) $option->getOptionId()] = (string) $option->getOptionName();
        }
        $classes = $input['classList'];
        if (!is_array($classes) || $classes === []) {
            throw new InvalidArgumentException(gettext('Choose at least one classification to review.'));
        }
        $ids = [];
        foreach ($classes as $id) {
            if (!is_scalar($id) || !ctype_digit((string) $id) || !isset($options[(int) $id])) {
                throw new InvalidArgumentException(gettext('Choose an existing classification.'));
            }
            $ids[] = (int) $id;
        }

        return ['classIds' => array_values(array_unique($ids)), 'classNames' => array_values(array_intersect_key($options, array_flip($ids))),
            'asOf' => $asOf, 'minimumAge' => $integer('MinimumAge', 0, 120),
            'membershipMonths' => $integer('MembershipMonths', 0, 1200),
            'donationYears' => $integer('RequireDonationYears', 0, 100), 'fiscalYearId' => $integer('FYID', 1, 204)];
    }

    public static function report(array $criteria): array
    {
        $query = PersonQuery::create()->filterByClsId($criteria['classIds'], Criteria::IN)
            ->filterByDateDeceased(null, Criteria::ISNULL)->filterByDateDeactivated(null, Criteria::ISNULL)
            ->filterByNeedsReview(false)->leftJoinWithFamily()->orderByLastName()->orderByFirstName()->orderById();
        $people = (new ClassificationService())->excludeInactive($query)->find();
        $givers = [];
        $givingRange = '';
        if ($criteria['donationYears'] > 0) {
            $first = FiscalYearUtils::getFiscalYearDatesById($criteria['fiscalYearId'] - $criteria['donationYears'] + 1);
            $last = FiscalYearUtils::getFiscalYearDatesById($criteria['fiscalYearId']);
            $end = min($last['endDate'], $criteria['asOf']->format('Y-m-d'));
            $givingRange = DateTimeUtils::formatDate($first['startDate']) . ' – ' . DateTimeUtils::formatDate($end);
            if ($end >= $first['startDate']) {
                $ids = [];
                foreach ($people as $person) {
                    if ($person->getFamId()) {
                        $ids[] = (int) $person->getFamId();
                    }
                }
                if ($ids !== []) {
                    $givers = array_fill_keys(PledgeQuery::create()->filterByFamId(array_unique($ids), Criteria::IN)
                        ->filterByPledgeOrPayment('Payment')->filterByAmount(0, Criteria::GREATER_THAN)
                        ->filterByDate($first['startDate'], Criteria::GREATER_EQUAL)
                        ->filterByDate((new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d'), Criteria::LESS_THAN)
                        ->select('FamId')->distinct()->find()->toArray(), true);
                }
            }
        }
        $rows = [];
        $unknown = 0;
        $asOf = $criteria['asOf'];
        foreach ($people as $person) {
            $family = $person->getFamily();
            if ($family !== null && (!$family->isActive() || $family->getNeedsReview())) {
                continue;
            }
            if ($criteria['donationYears'] > 0 && !isset($givers[(int) $person->getFamId()])) {
                continue;
            }
            if ($criteria['minimumAge'] > 0) {
                $date = sprintf('%04d-%02d-%02d', $person->getBirthYear(), $person->getBirthMonth(), $person->getBirthDay());
                $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$birth || $birth->format('Y-m-d') !== $date || (int) $person->getBirthYear() < 1) {
                    $unknown++;
                    continue;
                }
                if ($birth > $asOf || $birth->diff($asOf)->y < $criteria['minimumAge']) {
                    continue;
                }
            }
            if ($criteria['membershipMonths'] > 0) {
                $joined = $person->getMembershipDate();
                if ($joined === null) {
                    $unknown++;
                    continue;
                }
                $duration = $joined->diff($asOf);
                if ($joined > $asOf || $duration->y * 12 + $duration->m < $criteria['membershipMonths']) {
                    continue;
                }
            }
            $portrait = ReportPhoto::forPerson($person);
            $rows[] = ['name' => $person->getFullName(), 'family' => $family ? (string) $family->getName() : '',
                'photo' => $portrait['photo'],
                'initials' => $portrait['initials']];
        }
        $rules = [sprintf(gettext('Classifications: %s'), implode(', ', $criteria['classNames'])),
            sprintf(gettext('As of: %s'), DateTimeUtils::formatDate($asOf->format('Y-m-d'))),
            gettext('Deceased people, inactive people and classifications, and inactive or pending-review families and people are excluded.')];
        if ($criteria['minimumAge'] > 0) {
            $rules[] = sprintf(gettext('Minimum age: %d'), $criteria['minimumAge']);
        }
        if ($criteria['membershipMonths'] > 0) {
            $rules[] = sprintf(gettext('Minimum membership duration: %d months'), $criteria['membershipMonths']);
        }
        $rules[] = $criteria['donationYears'] > 0
            ? sprintf(gettext('At least one positive family payment in the selected %d fiscal years, through the review date: %s'), $criteria['donationYears'], $givingRange)
            : gettext('No donation requirement.');

        return ['people' => $rows, 'count' => count($rows), 'unknown' => $unknown, 'rules' => $rules];
    }
}
