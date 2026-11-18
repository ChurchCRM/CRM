<?php

declare(strict_types=1);

use ChurchCRM\Authorization\AccessPrincipal;
use ChurchCRM\Authorization\AuthorizationService;
use ChurchCRM\Authorization\Decision;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$service = new AuthorizationService();
$modulesOn = [
    'bEnabledFinance' => true,
    'bEnabledFundraiser' => true,
    'bEnabledEvents' => true,
    'sVolunteerVersion' => 'v2',
];

$staff = static fn (array $roles = [], bool $owner = false, array $modules = null, array $ministries = []): AccessPrincipal => new AccessPrincipal(
    false,
    $owner,
    $roles,
    $ministries,
    $modules ?? $modulesOn,
);

$answer = static fn (AccessPrincipal $principal, string $permission): string => $service->decide($principal, $permission)->reason;

Assert::same(Decision::STAFF_BOUNDARY, $answer(new AccessPrincipal(true, false, ['administrator'], [], $modulesOn), 'system.admin'));
Assert::false($service->canReadDirectory(new AccessPrincipal(true, false, [], [], $modulesOn)));
Assert::true($service->canReadDirectory($staff()));

Assert::same(Decision::NO_GRANT, $answer($staff(), 'people.delete'));
Assert::same(Decision::GRANTED, $answer($staff(['people.edit']), 'people.update'));
Assert::same(Decision::NO_GRANT, $answer($staff(['people.edit']), 'people.delete'));

Assert::same(Decision::GRANTED, $answer($staff(['groups.manage']), 'groups.manage'));
Assert::same(Decision::NO_GRANT, $answer($staff(['groups.manage']), 'finance.deposits.manage'));

Assert::same(
    Decision::FEATURE_OFF,
    $answer($staff(['finance'], false, ['bEnabledFinance' => false, 'sVolunteerVersion' => 'v2']), 'finance.deposits.manage'),
);
Assert::same(
    Decision::FEATURE_OFF,
    $answer($staff([], true, ['bEnabledFinance' => false, 'sVolunteerVersion' => 'v2']), 'finance.deposits.manage'),
);
Assert::same(Decision::GRANTED, $answer($staff([], true, ['bEnabledFinance' => false]), 'system.admin'));

Assert::same(Decision::SCOPE_REQUIRED, $answer($staff(['ministries.manage-mine']), 'ministries.manage'));
Assert::same(Decision::GRANTED, $answer($staff(['ministries.manage-mine'], false, null, [12]), 'ministries.manage'));
Assert::same(
    Decision::FEATURE_OFF,
    $answer($staff(['ministries.manage-all'], false, ['sVolunteerVersion' => 'v1'], [12]), 'ministries.manage'),
);

Assert::same(Decision::UNKNOWN_CAPABILITY, $answer($staff(['legacy-administrator']), 'not.a.permission'));
Assert::same(Decision::GRANTED, $answer($staff(['legacy-administrator']), 'export.people'));
Assert::same(Decision::NO_GRANT, $answer($staff(['administrator']), 'export.people'));
