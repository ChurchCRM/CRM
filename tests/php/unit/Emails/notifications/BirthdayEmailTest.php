<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\notifications\BirthdayEmail;
use ChurchCRM\model\ChurchCRM\Person;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

SystemURLs::init('', ['https://crm.example.org'], APP_ROOT);
SystemConfig::init();

$bodyFor = static function (Person $person): string {
    $person->setFirstName('Ann');
    $person->setLastName('Lee');

    return (new BirthdayEmail(['ann@example.org'], $person))->getTokens()['body'];
};

$today = new DateTimeImmutable('today');
$bornToday = static function (?int $year) use ($today): Person {
    $person = new Person();
    $person->setBirthYear($year);
    $person->setBirthMonth((int) $today->format('n'));
    $person->setBirthDay((int) $today->format('j'));

    return $person;
};

Assert::contains('Wishing you a wonderful 40 year birthday!', $bodyFor($bornToday((int) $today->format('Y') - 40)), 'the greeting names the new age');

$hidden = $bornToday((int) $today->format('Y') - 40);
$hidden->setFlags(1);
Assert::contains('Wishing you a wonderful birthday!', $bodyFor($hidden), 'a hidden age stays out of the greeting');

Assert::contains('Wishing you a wonderful birthday!', $bodyFor($bornToday(null)), 'without a birth year the greeting has no age');

Assert::contains('Wishing you a wonderful birthday!', $bodyFor($bornToday((int) $today->format('Y') + 4)), 'a birth year in the future keeps the age out of the greeting');
