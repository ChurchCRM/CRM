<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\TaxYearUtils;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);

/** A stored config row, as SystemConfig reads it. */
final class TaxYearConfigRow
{
    public function __construct(private readonly string $name, private readonly ?string $value)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}

$startOn = static function (string $month, string $day): void {
    SystemConfig::init([new TaxYearConfigRow('iTaxYearStartMonth', $month), new TaxYearConfigRow('iTaxYearStartDay', $day)], false);
};

// January 1: the tax year is the calendar year.
$startOn('1', '1');
Assert::same(2018, TaxYearUtils::endYearForDate('2018-03-04'));
Assert::same(2017, TaxYearUtils::endYearForDate('2017-12-31'));
Assert::same(['startDate' => '2018-01-01', 'endDate' => '2018-12-31', 'label' => '2018'], TaxYearUtils::datesForEndYear(2018));
Assert::same('2018', TaxYearUtils::yearLabel(2018));

// July 1: the year ends in the calendar year that holds the June 30 it ends on.
$startOn('7', '1');
Assert::same(2018, TaxYearUtils::endYearForDate('2018-03-04'));
Assert::same(2017, TaxYearUtils::endYearForDate('2017-06-11'));
Assert::same(2018, TaxYearUtils::endYearForDate('2017-07-01'));
Assert::same(['startDate' => '2017-07-01', 'endDate' => '2018-06-30', 'label' => '2017/18'], TaxYearUtils::datesForEndYear(2018));

Assert::same('2017/18 (July 01 2017 through June 30 2018)', TaxYearUtils::yearLabel(2018));
Assert::same('July 01 2017 through June 30 2018', TaxYearUtils::periodText(2018));

// 6 April: a tax year that starts part-way through a month.
$startOn('4', '6');
Assert::same(2018, TaxYearUtils::endYearForDate('2018-04-05'));
Assert::same(2019, TaxYearUtils::endYearForDate('2018-04-06'));
Assert::same(['startDate' => '2018-04-06', 'endDate' => '2019-04-05', 'label' => '2018/19'], TaxYearUtils::datesForEndYear(2019));

// A day outside 1-28 or a month outside 1-12 falls back to the first.
$startOn('4', '99');
Assert::same(1, TaxYearUtils::startDay());
$startOn('13', '1');
Assert::same(1, TaxYearUtils::startMonth());
