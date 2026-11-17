<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Person;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', ['https://crm.example.org'], APP_ROOT);
SystemConfig::init();
(new ReflectionProperty(SystemConfig::getConfigItem('sTimeZone'), 'value'))
    ->setValue(SystemConfig::getConfigItem('sTimeZone'), date_default_timezone_get());

$born = static function (?int $year, ?int $month, ?int $day): Person {
    $person = new Person();
    $person->setBirthYear($year);
    $person->setBirthMonth($month);
    $person->setBirthDay($day);

    return $person;
};

$may1 = new DateTimeImmutable('2026-05-01');

Assert::true($born(1990, 5, 1)->isBirthdayToday($may1), 'the birthday matches on month and day');
Assert::true($born(null, 5, 1)->isBirthdayToday($may1), 'a birthday without a year still matches');
Assert::false($born(1990, 5, 2)->isBirthdayToday($may1), 'the day before the birthday does not match');
Assert::false($born(1990, 6, 1)->isBirthdayToday($may1), 'the same day in another month does not match');
Assert::false($born(1990, null, null)->isBirthdayToday($may1), 'no birthday never matches');
Assert::false($born(1990, 5, null)->isBirthdayToday($may1), 'a birthday without a day never matches');

$leapDay = $born(2000, 2, 29);
Assert::true($leapDay->isBirthdayToday(new DateTimeImmutable('2028-02-29')), 'a leap day birthday matches in a leap year');
Assert::false($leapDay->isBirthdayToday(new DateTimeImmutable('2027-02-28')), 'by default a leap day birthday is not observed on 28 February');
Assert::true($leapDay->isBirthdayToday(new DateTimeImmutable('2027-03-01')), 'by default a leap day birthday is observed on 1 March in a non-leap year');

$today = new DateTimeImmutable('today');
Assert::true($born(1980, (int) $today->format('n'), (int) $today->format('j'))->isBirthdayToday(), 'without a date it checks today');
$tomorrow = $today->modify('+1 day');
Assert::false($born(1980, (int) $tomorrow->format('n'), (int) $tomorrow->format('j'))->isBirthdayToday());
