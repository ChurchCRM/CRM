<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);
SystemConfig::init();

const HEAD = 1;
const SPOUSE = 2;
const CHILD = 3;

function person(string $name, int $role, int $birthYear, string $email = ''): Person
{
    return (new Person())->setFirstName($name)->setFmrId($role)->setBirthYear($birthYear)->setEmail($email);
}

/** @param Person[] $people */
function family(array $people, bool $selfRegistered = false): Family
{
    $family = new Family();
    if ($selfRegistered) {
        $family->setEnteredBy(Person::SELF_REGISTER);
    }
    foreach ($people as $person) {
        $family->addPerson($person);
    }

    return $family;
}

$cases = [];

$cases['the oldest head of household'] = [family([
    person('Younger head', HEAD, 1970, 'younger@example.com'),
    person('Older head', HEAD, 1960, 'older@example.com'),
    person('Spouse', SPOUSE, 1950, 'spouse@example.com'),
]), 'Older head', ['Younger head', 'Older head', 'Spouse']];

$cases['a spouse when the head has no email'] = [family([
    person('Head', HEAD, 1960),
    person('Spouse', SPOUSE, 1962, 'spouse@example.com'),
    person('Child', CHILD, 1990, 'child@example.com'),
]), 'Spouse', ['Spouse']];

$cases['a spouse when the head is inactive'] = [family([
    person('Inactive head', HEAD, 1960, 'inactive@example.com')->setDateDeactivated('2026-01-01'),
    person('Spouse', SPOUSE, 1962, 'spouse@example.com'),
]), 'Spouse', ['Spouse']];

$cases['nobody when the only adult is deceased'] = [family([
    person('Deceased head', HEAD, 1930, 'deceased@example.com')->setDateDeceased('2020-01-01'),
    person('Child', CHILD, 1990, 'child@example.com'),
]), null, []];

$cases['a spouse when the head awaits review'] = [family([
    person('Proposed head', HEAD, 1950, 'proposed@example.com')->setNeedsReview(true),
    person('Spouse', SPOUSE, 1962, 'spouse@example.com'),
]), 'Spouse', ['Spouse']];

$cases['the head of a family that registered itself, though it awaits review'] = [family([
    person('Registered head', HEAD, 1960, 'registered@example.com')->setNeedsReview(true),
    person('Registered spouse', SPOUSE, 1962, 'spouse@example.com')->setNeedsReview(true),
], true), 'Registered head', ['Registered head', 'Registered spouse']];

foreach ($cases as $label => [$family, $expectedRecipient, $expectedAdults]) {
    Assert::same($expectedRecipient, $family->getEmailRecipient()?->getFirstName(), $label . ': recipient');
    Assert::same(
        $expectedAdults,
        array_map(fn (Person $person): string => (string) $person->getFirstName(), $family->getAdultsWithEmail()),
        $label . ': adults with email'
    );
}
