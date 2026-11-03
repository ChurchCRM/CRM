<?php

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\Person;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$born = static function (int $year, int $month, int $day): Person {
    $person = new Person();
    $person->setBirthYear($year);
    $person->setBirthMonth($month);
    $person->setBirthDay($day);

    return $person;
};

Assert::same('00:00:00', $born(1990, 5, 1)->getBirthDate()->format('H:i:s'), 'a birth date starts at midnight');

// Forty years back keeps the month and day valid even on 29 February.
$today = new DateTimeImmutable('today');
$birthdayToday = $born((int) $today->format('Y') - 40, (int) $today->format('n'), (int) $today->format('j'));
Assert::same(40, $birthdayToday->getNumericAge(), 'on the birthday the new age counts');
Assert::same('40 years old', $birthdayToday->getAge(), 'on the birthday the new age is shown');

$tomorrow = $today->modify('+1 day');
$birthdayTomorrow = $born((int) $tomorrow->format('Y') - 40, (int) $tomorrow->format('n'), (int) $tomorrow->format('j'));
Assert::same(39, $birthdayTomorrow->getNumericAge(), 'the day before the birthday the old age still counts');
Assert::same('39 years old', $birthdayTomorrow->getAge());

$baby = $born(2026, 1, 15);
Assert::same('3 months old', $baby->getAge('2026-04-15'), 'a month birthday counts on the day');
Assert::same('2 months old', $baby->getAge('2026-04-14'));

$adult = $born(1990, 5, 1);
Assert::same('36 years old', $adult->getAge('2026-05-01'), 'the birthday calendar shows the new age on the birthday');
Assert::same('35 years old', $adult->getAge('2026-04-30'));

$hidden = $born(1990, 5, 1);
$hidden->setFlags(1);
Assert::null($hidden->getNumericAge(), 'a hidden age is not counted');
Assert::same('', $hidden->getAge('2026-05-01'), 'a hidden age is not shown');

$noYear = new Person();
$noYear->setBirthMonth(5);
$noYear->setBirthDay(1);
Assert::null($noYear->getNumericAge(), 'without a birth year there is no age');
Assert::null($noYear->getAge());

$infant = $born((int) $today->format('Y'), 1, 1);
Assert::same(0, $infant->getNumericAge() ?? -1, 'under a year old counts as 0, not as unknown');

$unborn = $born((int) $today->format('Y') + 4, (int) $today->format('n'), (int) $today->format('j'));
Assert::null($unborn->getNumericAge(), 'a birth year in the future has no age');
Assert::same('', $unborn->getAge(), 'a birth year in the future shows no age');
