<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PersonQuery as BasePersonQuery;
use ChurchCRM\Utils\AnnualDate;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Skeleton subclass for performing query and update operations on the 'person_per' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class PersonQuery extends BasePersonQuery
{
    public function filterByActive(): self
    {
        return $this->filterByDateDeactivated(null, Criteria::ISNULL);
    }

    /**
     * Only living persons (per_DateDeceased IS NULL).
     */
    public function filterByLiving(): self
    {
        return $this->filterByDateDeceased(null, Criteria::ISNULL);
    }

    /**
     * Only deceased persons (per_DateDeceased IS NOT NULL).
     */
    public function filterByDeceased(): self
    {
        return $this->filterByDateDeceased(null, Criteria::ISNOTNULL);
    }
    private int $birthdayConditionCount = 0;

    /**
     * People whose birthday is observed on $date. A 29 February birthday is also
     * selected on the day AnnualDate observes it in a non-leap year.
     */
    public function filterByBirthdayObservedOn(\DateTimeInterface $date): self
    {
        return $this->filterByBirthdayMonthDays(AnnualDate::recordedMonthDaysObservedOn($date));
    }

    /** People whose birthday is observed within $days before or after today (default: today in the configured timezone). */
    public function filterByBirthdayObservedWithin(int $days, ?\DateTimeInterface $today = null): self
    {
        [$from, $to] = AnnualDate::windowAroundToday($days, $today);

        return $this->filterByBirthdayMonthDays(AnnualDate::recordedMonthDaysObservedBetween($from, $to));
    }

    /** @param list<array{0: int, 1: int}> $pairs recorded [month, day] pairs */
    private function filterByBirthdayMonthDays(array $pairs): self
    {
        $daysByMonth = [];
        foreach ($pairs as [$month, $day]) {
            $daysByMonth[$month][] = $day;
        }

        $names = [];
        foreach ($daysByMonth as $month => $days) {
            $id = ++$this->birthdayConditionCount;
            $this->condition("birthMonth$id", 'Person.BirthMonth = ?', $month);
            $this->condition("birthDays$id", 'Person.BirthDay IN ?', $days);
            $this->combine(["birthMonth$id", "birthDays$id"], 'and', "birthObserved$id");
            $names[] = "birthObserved$id";
        }

        return $this->where($names, 'or');
    }
}
