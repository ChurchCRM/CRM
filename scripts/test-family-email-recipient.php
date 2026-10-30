<?php

/**
 * scripts/test-family-email-recipient.php — who receives mail for the whole
 * family (member sign-in design §10).
 *
 * Usage:
 *   php scripts/test-family-email-recipient.php
 *   npm run test:php
 *
 * Family::getEmailRecipient() picks the oldest head of household who can
 * receive family email, else the oldest such spouse, and
 * Family::getAdultsWithEmail() lists the heads and spouses who can. Both leave
 * out the deceased, the inactive, people with no email of their own and,
 * unless the whole family registered itself, people awaiting review. The
 * family and its people are built in memory, so no database is needed.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 *   2  — vendor autoloader missing
 */

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing {$autoload} — run `composer install` in src/ first.\n");
    exit(2);
}
require $autoload;

SystemURLs::init('', [], dirname(__DIR__) . '/src');
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

$inactive = person('Inactive head', HEAD, 1960, 'inactive@example.com')->setDateDeactivated('2026-01-01');
$cases['a spouse when the head is inactive'] = [family([
    $inactive,
    person('Spouse', SPOUSE, 1962, 'spouse@example.com'),
]), 'Spouse', ['Spouse']];

$deceased = person('Deceased head', HEAD, 1930, 'deceased@example.com')->setDateDeceased('2020-01-01');
$cases['nobody when the only adult is deceased'] = [family([
    $deceased,
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

$passed = 0;
$failed = 0;
foreach ($cases as $label => [$family, $expectedRecipient, $expectedAdults]) {
    $recipient = $family->getEmailRecipient()?->getFirstName();
    $adults = array_map(fn (Person $person): string => (string) $person->getFirstName(), $family->getAdultsWithEmail());
    $n = $passed + $failed + 1;
    if ($recipient === $expectedRecipient && $adults === $expectedAdults) {
        $passed++;
        echo "ok {$n} - {$label}\n";
        continue;
    }
    $failed++;
    echo "not ok {$n} - {$label}  # recipient " . var_export($recipient, true) . ', adults ' . implode(', ', $adults) . "\n";
}

$total = $passed + $failed;
echo "1..{$total}\n";
echo "# {$total} cases, {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
