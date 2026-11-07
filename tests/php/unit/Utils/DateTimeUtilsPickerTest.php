<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\DateTimeUtils;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);

final class PickerRow
{
    public function __construct(private readonly string $name, private readonly string $value)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function delete(): void
    {
    }
}

$withFormat = static function (string $format): void {
    SystemConfig::init([new PickerRow('sDatePickerFormat', $format)], false);
};

foreach (['m/d/Y' => '03/22/2014', 'd/m/Y' => '22/03/2014', 'Y-m-d' => '2014-03-22'] as $format => $typed) {
    $withFormat($format);
    Assert::same('2014-03-22', DateTimeUtils::normalizeDate($typed), "$format: normalizeDate reads $typed");
    Assert::same('2014-03-22', DateTimeUtils::parseAndValidate($typed, 'past'), "$format: parseAndValidate reads $typed");
    Assert::same('2014-03-22', DateTimeUtils::parseDatePicker($typed, true), "$format: strict parse reads $typed");
    Assert::same($typed, DateTimeUtils::formatForDatePicker('2014-03-22'), "$format: formatForDatePicker round-trips");
}

$withFormat('m/d/Y');
Assert::same('', DateTimeUtils::normalizeDate(''), 'empty input stays empty');
Assert::same('', DateTimeUtils::normalizeDate('not a date'), 'unparseable input is empty, never 1970');
Assert::same('', DateTimeUtils::normalizeDate('22/03/2014'), 'a slash date in another layout is rejected, not guessed');
Assert::false(DateTimeUtils::parseAndValidate('22/03/2014', 'past'), 'parseAndValidate rejects a slash date in another layout');
Assert::same('2014-03-22', DateTimeUtils::normalizeDate('2014-03-22'), 'ISO is accepted under any picker format');
Assert::same('', DateTimeUtils::normalizeDate('2024-02-30'), 'an impossible ISO date is rejected, not rolled forward');
Assert::true(DateTimeUtils::isCompleteDateFormat('d/m/Y'), 'day, month and year make a complete format');
Assert::false(DateTimeUtils::isCompleteDateFormat('Y-m'), 'a format without a day is incomplete');
Assert::false(DateTimeUtils::isCompleteDateFormat('Y-m-\\d'), 'an escaped d is a literal, not a day');
$withFormat('Y-m');
Assert::same('2014-03-22', DateTimeUtils::normalizeDate('2014-03-22'), 'an incomplete saved format falls back to Y-m-d');
Assert::same('', DateTimeUtils::normalizeDate('2014-03'), 'a year-month value is never turned into the first of the month');
Assert::same('2014-03-22', DateTimeUtils::normalizeDate('March 22, 2014'), 'a textual date is accepted');
Assert::same('', DateTimeUtils::normalizeDate('3/4/14'), 'a two-digit year is not read as year 0014');
Assert::same('', DateTimeUtils::normalizeDate('02/30/2014'), 'an overflow date is empty, not rolled into March');
Assert::false(DateTimeUtils::parseAndValidate('02/30/2014', 'past'), 'parseAndValidate rejects an overflow date');
Assert::same('2014-03-04', DateTimeUtils::parseFullDate('3/4/2014'), 'parseFullDate reads a full date');
Assert::null(DateTimeUtils::parseFullDate('3/4'), 'parseFullDate rejects a date without a year');
Assert::null(DateTimeUtils::parseFullDate('2020'), 'parseFullDate rejects a bare year');
Assert::null(DateTimeUtils::parseDatePicker('3/4/2014', true), 'strict parse rejects unpadded input');
Assert::null(DateTimeUtils::parseDatePicker('02/30/2014', true), 'strict parse rejects an overflow date');

$withFormat('m/d/Y');
Assert::same(['month' => 3, 'day' => 4, 'year' => 2014], DateTimeUtils::parsePartialDate('3/4/2014'), 'import: month first under m/d/Y');
$withFormat('d/m/Y');
Assert::same(['month' => 3, 'day' => 4, 'year' => 2014], DateTimeUtils::parsePartialDate('4/3/2014'), 'import: day first under d/m/Y');
Assert::same(['month' => 3, 'day' => 4, 'year' => null], DateTimeUtils::parsePartialDate('4/3'), 'import: day first without a year');
Assert::same(['month' => 3, 'day' => 4, 'year' => 2014], DateTimeUtils::parsePartialDate('2014-03-04'), 'import: ISO is unchanged');
