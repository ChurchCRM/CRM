<?php

declare(strict_types=1);

use ChurchCRM\Authorization\BuiltinRoles;
use ChurchCRM\Authorization\Decision;
use ChurchCRM\Authorization\EffectiveGrant;
use ChurchCRM\Authorization\PermissionDefinition;
use ChurchCRM\Authorization\PermissionRegistry;
use ChurchCRM\Authorization\Scope;
use ChurchCRM\Portal\Permissions\Decision as PortalDecision;
use ChurchCRM\Portal\Permissions\EffectiveGrant as PortalEffectiveGrant;
use ChurchCRM\Portal\Permissions\PortalScope;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

Assert::same('ordinary', PermissionDefinition::ORDINARY);
Assert::same('sensitive', PermissionDefinition::SENSITIVE);
Assert::same('all', BuiltinRoles::SCOPE_ALL);
Assert::same('my-ministries', BuiltinRoles::SCOPE_MY_MINISTRIES);

$catalog = [
    ['people.create', null, 'ordinary'],
    ['families.create', null, 'ordinary'],
    ['cart.to-family', null, 'ordinary'],
    ['people.update', null, 'ordinary'],
    ['families.update', null, 'ordinary'],
    ['people.properties.assign', null, 'ordinary'],
    ['whycame.update', null, 'ordinary'],
    ['people.verify', null, 'ordinary'],
    ['groups.member-properties.update', null, 'ordinary'],
    ['people.delete', null, 'ordinary'],
    ['families.delete', null, 'ordinary'],
    ['groups.manage', null, 'ordinary'],
    ['groups.properties.manage', null, 'ordinary'],
    ['sundayschool.manage', null, 'ordinary'],
    ['finance.deposits.manage', 'bEnabledFinance', 'sensitive'],
    ['finance.pledges.manage', 'bEnabledFinance', 'sensitive'],
    ['finance.reports.read', 'bEnabledFinance', 'sensitive'],
    ['fundraisers.manage', 'bEnabledFundraiser', 'ordinary'],
    ['ministries.manage', 'sVolunteerVersion', 'ordinary'],
    ['notes.read', null, 'ordinary'],
    ['notes.create', null, 'ordinary'],
    ['events.create', 'bEnabledEvents', 'ordinary'],
    ['calendars.manage', 'bEnabledEvents', 'ordinary'],
    ['email.mailto', null, 'ordinary'],
    ['system.admin', null, 'ordinary'],
    ['roles.assign', null, 'ordinary'],
    ['export.people', null, 'sensitive'],
    ['communication.log.read', null, 'sensitive'],
    ['notes.private.read', null, 'sensitive'],
];

$keys = [];
foreach ($catalog as [$key, $module, $sensitivity]) {
    $definition = PermissionRegistry::get($key);
    Assert::type(PermissionDefinition::class, $definition, $key);
    Assert::same($key, $definition->key, $key);
    Assert::same($module, $definition->module, $key);
    Assert::same($sensitivity, $definition->sensitivity, $key);
    Assert::same('7.9.0', $definition->since, $key);
    $keys[] = $key;
}

Assert::same($keys, PermissionRegistry::keys());
Assert::null(PermissionRegistry::get('not.a.permission'));
Assert::null(PermissionRegistry::get('People.Create'));

// V1 predicate => the built-in role and the registry keys that stand in for it.
$v1 = [
    'isAddRecordsEnabled' => ['people.add', ['people.create', 'families.create', 'cart.to-family']],
    'isEditRecordsEnabled' => ['people.edit', ['people.update', 'families.update', 'people.properties.assign', 'whycame.update', 'people.verify', 'groups.member-properties.update']],
    'isDeleteRecordsEnabled' => ['people.delete', ['people.delete', 'families.delete']],
    'isManageGroupsEnabled' => ['groups.manage', ['groups.manage', 'groups.properties.manage', 'sundayschool.manage']],
    'isFinanceEnabled' => ['finance', ['finance.deposits.manage', 'finance.pledges.manage', 'finance.reports.read']],
    'isManageFundraisersEnabled' => ['fundraisers.manage', ['fundraisers.manage']],
    'isManageMinistriesEnabled' => ['ministries.manage-all', ['ministries.manage']],
    'isManageMyMinistriesEnabled' => ['ministries.manage-mine', ['ministries.manage']],
    'isNotesEnabled' => ['notes', ['notes.read', 'notes.create']],
    'isAddEventEnabled' => ['events.add', ['events.create', 'calendars.manage']],
    'isEmailEnabled' => ['email', ['email.mailto']],
    'isAdmin' => ['administrator', ['system.admin', 'roles.assign']],
];

foreach ($v1 as $predicate => [$role, $permissions]) {
    foreach ($permissions as $permission) {
        Assert::notNull(PermissionRegistry::get($permission), $predicate . ' ' . $permission);
    }
    Assert::same($permissions, array_column(BuiltinRoles::grants($role), 'permission'), $predicate);
}

Assert::same(
    [['permission' => 'ministries.manage', 'scope' => 'my-ministries']],
    BuiltinRoles::grants('ministries.manage-mine'),
);

foreach (BuiltinRoles::all() as $role => $grants) {
    if ($role === 'ministries.manage-mine') {
        continue;
    }
    foreach ($grants as $grant) {
        Assert::same('all', $grant['scope'], $role . ' ' . $grant['permission']);
    }
}

Assert::same(PermissionRegistry::keys(), array_column(BuiltinRoles::grants('legacy-administrator'), 'permission'));
Assert::notContains(
    'finance.deposits.manage',
    array_column(BuiltinRoles::grants('administrator'), 'permission'),
);

$scope = new PortalScope(Scope::TEAM, 8);
Assert::same(Scope::TEAM, $scope->type);
Assert::same(8, $scope->id);
Assert::true($scope->equals(PortalScope::team(8)));
Assert::true(Scope::team(8)->equals($scope));
Assert::type(Scope::class, PortalScope::none());

$grant = new PortalEffectiveGrant('portal.family.view', PortalScope::family(4), EffectiveGrant::SOURCE_GIVEN, 'Given');
Assert::type(EffectiveGrant::class, $grant);
$decision = PortalDecision::allow($grant);
Assert::type(Decision::class, $decision);
Assert::type(PortalDecision::class, $decision);
Assert::true($decision->allowed);
Assert::same(Decision::GRANTED, $decision->reason);
Assert::false(PortalDecision::deny(Decision::NO_GRANT)->allowed);
