<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Service\UserPermissionsService;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$setting = static fn (string $value) => new class($value) {
    public function __construct(private string $value) {}
    public function getValue(): string { return $this->value; }
    public function getBooleanValue(): bool { return $this->value === '1'; }
};
(new \ReflectionProperty(SystemConfig::class, 'configs'))->setValue(null, [
    'bEnabledFinance' => $setting('0'),
    'bEnabledFundraiser' => $setting('1'),
    'bEnabledEvents' => $setting('0'),
    'sVolunteerVersion' => $setting('v1'),
]);

$rows = static function (User $user): array {
    $byLabel = [];
    foreach ((new UserPermissionsService())->getEffectivePermissions($user) as $group) {
        foreach ($group['rows'] as $row) {
            $byLabel[$row['label']] = $row;
        }
    }

    return $byLabel;
};

$admin = $rows((new User())->setAdmin(true));
Assert::true($admin['Manage fundraisers']['granted']);
Assert::null($admin['Manage fundraisers']['reason']);
Assert::false($admin['Manage donations and finance']['granted'], 'a module that is off is off for an administrator too');
Assert::same('Finance is turned off for this church', $admin['Manage donations and finance']['reason']);
Assert::true($admin['View events']['granted'], 'administrators see events while the module is off, as ViewEventsRoleAuthMiddleware allows');
Assert::true($admin['Add and edit events']['granted']);
Assert::same('Volunteer Management is turned off for this church', $admin['Manage all ministries']['reason']);
Assert::true($admin['Email people and groups']['granted']);

$member = $rows((new User())->setAdmin(false)->setEditSelf(true)->setAddRecords(true)->setManageFundraisers(true));
Assert::false($member['Add people and families']['granted'], 'a self-service account holds no staff permission, whatever its flags say');
Assert::same('This account uses the Member Portal only', $member['Add people and families']['reason']);
Assert::same('This account uses the Member Portal only', $member['Manage fundraisers']['reason']);
Assert::same('Finance is turned off for this church', $member['Manage donations and finance']['reason']);
Assert::same('Events are turned off for this church', $member['Add and edit events']['reason']);

foreach ([$admin, $member] as $user) {
    foreach ($user as $label => $row) {
        Assert::same($row['granted'], $row['reason'] === null, $label . ': a denied row always says why');
    }
}
