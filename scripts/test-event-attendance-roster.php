<?php

/**
 * scripts/test-event-attendance-roster.php — person lookups of the event view roster (#10540).
 *
 * Usage:
 *   php scripts/test-event-attendance-roster.php
 *   npm run test:php
 *
 * The roster loads attendees, check-in actors and check-out actors with one person
 * query, however many attendees the event has. Before the change the view ran one
 * PersonQuery::findOneById() per attendee plus one per check-in and check-out actor
 * (up to 3N queries: 3 for 1 attendee, 1500 for 500). The harness counts calls of the
 * person loader and checks that the count stays at 1 for 1, 50 and 500 attendees, and
 * that missing attendees are skipped, missing actors give an empty name and a repeated
 * actor is loaded once. No database is needed.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 *   2  — vendor autoloader missing
 */

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\EventAttend;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Service\EventAttendanceRosterService;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing {$autoload} — run `composer install` in src/ first.\n");
    exit(2);
}
require $autoload;

$passed = 0;
$failed = 0;
$n = 0;
$check = function (bool $ok, string $label, string $detail = '') use (&$passed, &$failed, &$n): void {
    $n++;
    $ok ? $passed++ : $failed++;
    echo ($ok ? 'ok' : 'not ok') . " {$n} - {$label}" . (!$ok && $detail !== '' ? "  # {$detail}" : '') . "\n";
};

$person = function (int $id): Person {
    $p = new Person();
    $p->setId($id);
    $p->setFirstName('First' . $id);
    $p->setLastName('Last' . $id);
    $p->setFamId($id * 10);

    return $p;
};

$attend = function (int $personId, ?int $checkin = null, ?int $checkout = null): EventAttend {
    $a = new EventAttend();
    $a->setPersonId($personId);
    $a->setCheckinId($checkin);
    $a->setCheckoutId($checkout);

    return $a;
};

// $existing: person ids the fake database holds. Returns [roster, loader calls, ids asked].
$run = function (array $attendances, array $existing): array {
    $calls = 0;
    $asked = [];
    $loader = function (array $ids) use (&$calls, &$asked, $existing) {
        $calls++;
        $asked = $ids;
        $out = [];
        foreach ($ids as $id) {
            if (in_array($id, $existing, true)) {
                $p = new Person();
                $p->setId($id);
                $p->setFirstName('First' . $id);
                $p->setLastName('Last' . $id);
                $p->setFamId($id * 10);
                $out[] = $p;
            }
        }

        return $out;
    };

    return [EventAttendanceRosterService::build($attendances, $loader), $calls, $asked];
};

[$roster, $calls] = $run([], []);
$check($roster === [] && $calls === 0, 'empty event: no roster and no person query');

[$roster, $calls] = $run([$attend(1, 2, 3)], [1, 2, 3]);
$check($calls === 1, 'one attendee with both actors: one person query', "calls={$calls}");
$check(
    count($roster) === 1
    && $roster[0]['personId'] === 1
    && $roster[0]['fullName'] === $person(1)->getFullName()
    && $roster[0]['familyId'] === 10
    && $roster[0]['checkinBy'] === $person(2)->getFullName()
    && $roster[0]['checkoutBy'] === $person(3)->getFullName()
    && $roster[0]['checkinDate'] === null
    && $roster[0]['checkoutDate'] === null
    && $roster[0]['isCheckedOut'] === false,
    'attendee and actor fields are unchanged'
);

[$roster, $calls] = $run([$attend(1), $attend(99), $attend(2)], [1, 2]);
$check(array_column($roster, 'personId') === [1, 2], 'missing attendee is skipped, order kept');

[$roster] = $run([$attend(1, 98, 97)], [1]);
$check($roster[0]['checkinBy'] === '' && $roster[0]['checkoutBy'] === '', 'missing actors give an empty name');

[$roster] = $run([$attend(1, null, null)], [1]);
$check($roster[0]['checkinBy'] === '' && $roster[0]['checkoutBy'] === '', 'no actor ids give an empty name');

[$roster, $calls, $asked] = $run([$attend(1, 5, 5), $attend(2, 5, 6), $attend(3, 6, 5), $attend(1, 5, 5)], [1, 2, 3, 5, 6]);
sort($asked);
$check($asked === [1, 2, 3, 5, 6], 'repeated attendees and actors are requested once', implode(',', $asked));
$check($calls === 1 && count($roster) === 4, 'repeated actors: one person query');
$check($roster[2]['checkinBy'] === $person(6)->getFullName() && $roster[2]['checkoutBy'] === $person(5)->getFullName(), 'repeated actors resolve to the right names');

foreach ([1, 50, 500] as $size) {
    $attendances = [];
    $existing = [];
    for ($i = 1; $i <= $size; $i++) {
        $attendances[] = $attend($i, 1000 + ($i % 7), 2000 + ($i % 5));
        $existing[] = $i;
    }
    array_push($existing, ...range(1000, 1006), ...range(2000, 2004));
    [$roster, $calls] = $run($attendances, $existing);
    $check($calls === 1 && count($roster) === $size, "{$size} attendees: 1 person query (was up to " . (3 * $size) . ')', "calls={$calls}");
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
