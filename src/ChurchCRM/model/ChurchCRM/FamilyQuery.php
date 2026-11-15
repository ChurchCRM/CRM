<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\FamilyQuery as BaseFamilyQuery;
use ChurchCRM\Utils\AnnualDate;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Skeleton subclass for performing query and update operations on the 'family_fam' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class FamilyQuery extends BaseFamilyQuery
{
    public function filterByActive(): self
    {
        return $this->filterByDateDeactivated(null, Criteria::ISNULL);
    }
    private int $weddingConditionCount = 0;

    /** Families whose wedding date has this recorded month and day, any year. */
    public function filterByWeddingMonthDay(int $month, int $day): self
    {
        return $this->filterByWeddingMonthDays([[$month, $day]]);
    }

    /**
     * Families whose wedding anniversary is observed on $date. A 29 February
     * wedding is also selected on the day AnnualDate observes it in a non-leap year.
     */
    public function filterByWeddingAnniversaryObservedOn(\DateTimeInterface $date): self
    {
        return $this->filterByWeddingMonthDays(AnnualDate::recordedMonthDaysObservedOn($date));
    }

    /** Families whose wedding anniversary is observed within $days before or after today (default: today in the configured timezone). */
    public function filterByWeddingAnniversaryObservedWithin(int $days, ?\DateTimeInterface $today = null): self
    {
        [$from, $to] = AnnualDate::windowAroundToday($days, $today);

        return $this->filterByWeddingMonthDays(AnnualDate::recordedMonthDaysObservedBetween($from, $to));
    }

    /** @param list<array{0: int, 1: int}> $pairs recorded [month, day] pairs */
    private function filterByWeddingMonthDays(array $pairs): self
    {
        $names = [];
        foreach ($pairs as [$month, $day]) {
            $name = 'weddingObserved' . ++$this->weddingConditionCount;
            $this->condition($name, 'Family.Weddingdate LIKE ?', sprintf('%%-%02d-%02d', $month, $day));
            $names[] = $name;
        }

        return $this->where($names, 'or');
    }
}
