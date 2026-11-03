<?php

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\VolunteerMinistry;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\Volunteer\Service\VolunteerClassLinkService;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

$memo = new ReflectionProperty(VolunteerClassLinkService::class, 'linkedTeamByGroup');
$linkedTeam = (new VolunteerTeam())->setName('Linked team');

$prime = function () use ($memo, $linkedTeam): void {
    $memo->setValue(null, [41 => $linkedTeam, 42 => null]);
};

$prime();
Assert::same($linkedTeam, VolunteerClassLinkService::findLinkedTeam(41), 'Remembered team');
Assert::null(VolunteerClassLinkService::findLinkedTeam(42), 'Remembered absence');

$hooks = [
    'saving a team (link, unlink, move)'      => static fn () => (new VolunteerTeam())->postSave(),
    'deleting a team'                         => static fn () => (new VolunteerTeam())->postDelete(),
    'deleting a ministry (its teams cascade)' => static fn () => (new VolunteerMinistry())->postDelete(),
    'deleting a group (the link is set null)' => static fn () => (new Group())->postDelete(),
];

foreach ($hooks as $label => $hook) {
    $prime();
    $hook();
    Assert::same([], $memo->getValue(), $label . ' clears the request memo');
}
