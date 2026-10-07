<?php

/**
 * scripts/test-volunteer-class-link-memo.php — the per-request memo of
 * VolunteerClassLinkService::findLinkedTeam() and the model hooks that clear it (#10363, #10362).
 *
 * Usage:
 *   php scripts/test-volunteer-class-link-memo.php
 *   npm run test:php
 *
 * A request that links, unlinks or deletes must not read the team it remembered before the
 * change. No API request looks the link up again after changing it, so Cypress cannot see a
 * stale memo; this harness primes the memo and calls each hook on an unsaved model instead.
 * It needs `composer install` in src/ but no database: a lookup that reached one would fail.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 *   2  — vendor autoloader missing
 */

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\Volunteer\Service\VolunteerClassLinkService;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing {$autoload} — run `composer install` in src/ first.\n");
    exit(2);
}
require $autoload;

$passed = 0;
$failed = 0;

$check = function (bool $ok, string $label, string $detail = '') use (&$passed, &$failed): void {
    $n = $passed + $failed + 1;
    if ($ok) {
        $passed++;
        echo "ok {$n} - {$label}\n";
        return;
    }
    $failed++;
    echo "not ok {$n} - {$label}" . ($detail !== '' ? "  # {$detail}" : '') . "\n";
};

$memo = new ReflectionProperty(VolunteerClassLinkService::class, 'linkedTeamByGroup');
$linkedTeam = (new VolunteerTeam())->setName('Linked team');

$prime = function () use ($memo, $linkedTeam): void {
    $memo->setValue(null, [41 => $linkedTeam, 42 => null]);
};

$lookup = static function (int $groupId): mixed {
    try {
        return VolunteerClassLinkService::findLinkedTeam($groupId);
    } catch (\Throwable $e) {
        return 'queried the database: ' . $e::class;
    }
};

$prime();
$found = $lookup(41);
$check($found === $linkedTeam, 'a remembered team is answered from the memo', is_string($found) ? $found : '');
$found = $lookup(42);
$check($found === null, 'a remembered "no team" is answered from the memo too', is_string($found) ? $found : '');

$hooks = [
    'saving a team (link, unlink, move)'      => static fn () => (new VolunteerTeam())->postSave(),
    'deleting a team'                         => static fn () => (new VolunteerTeam())->postDelete(),
    'deleting a ministry (its teams cascade)' => static fn () => (new VolunteerMinistry())->postDelete(),
    'deleting a group (the link is set null)' => static fn () => (new Group())->postDelete(),
];

foreach ($hooks as $label => $hook) {
    $prime();
    $hook();
    $check(
        $memo->getValue() === [],
        "{$label} forgets every remembered link",
        'memo still holds group ids ' . implode(', ', array_keys($memo->getValue()))
    );
}

$total = $passed + $failed;
echo "1..{$total}\n";
echo "# {$total} cases, {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
