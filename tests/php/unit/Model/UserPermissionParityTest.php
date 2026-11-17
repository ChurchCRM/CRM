<?php

declare(strict_types=1);

/**
 * Fails when a V1 permission answer changes.
 */

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Map\VolunteerScopeTableMap;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserConfig;
use ChurchCRM\Portal\Auth\PortalMemberUser;
use Propel\Runtime\Adapter\Pdo\SqliteAdapter;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

// usr_EditSelf and isEditSelf*() are gone. These are the switches that exist, apart from Admin.
const SWITCHES = [
    'AddRecords',
    'EditRecords',
    'DeleteRecords',
    'ManageGroups',
    'Finance',
    'ManageFundraisers',
    'ManageMinistries',
    'ManageMyMinistries',
    'Notes',
    'AddEvent',
    'Email',
];

// Includes bManageMinistries and bManageMyMinistries, which isEnabledSecurity() does not read from the columns.
const SECURITY_NAMES = [
    'bAll',
    'bAdmin',
    'bAddRecords',
    'bEditRecords',
    'bDeleteRecords',
    'bManageGroups',
    'bFinance',
    'bNotes',
    'bAddEvent',
    'bEmailMailto',
    'bManageMinistries',
    'bManageMyMinistries',
];

const OTHER_PERSON_ID = 1;

$snapshotPath = __DIR__ . '/fixtures/v1-permission-answers.json';
$actual = recordFixtures();

if (getenv('UPDATE_PARITY_SNAPSHOT') === '1') {
    $directory = dirname($snapshotPath);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    file_put_contents(
        $snapshotPath,
        json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
    );
}

Assert::true(is_file($snapshotPath), 'Committed snapshot is missing: ' . $snapshotPath);
$expected = json_decode((string) file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
Assert::same(array_column($expected, 'name'), array_column($actual, 'name'), 'fixture names');
foreach ($actual as $index => $fixture) {
    Assert::same(normalize($expected[$index]), normalize($fixture), $fixture['name']);
}

/**
 * @param array<mixed> $value
 * @return array<mixed>
 */
function normalize(array $value): array
{
    foreach ($value as $key => $item) {
        if (is_array($item)) {
            $value[$key] = normalize($item);
        }
    }
    ksort($value);

    return $value;
}

/**
 * @return list<array<string, mixed>>
 */
function recordFixtures(): array
{
    prepareScopeStore();

    $on = static fn (bool $finance = true, bool $fundraiser = true, bool $events = true, string $volunteer = 'v2'): array => [
        'bEnabledFinance' => $finance,
        'bEnabledFundraiser' => $fundraiser,
        'bEnabledEvents' => $events,
        'sVolunteerVersion' => $volunteer,
        'bPortalShowVolunteer' => false,
    ];

    $specs = [
        spec('zero', false, [], $on()),
        spec('admin', true, [], $on()),
    ];
    foreach (SWITCHES as $switch) {
        $specs[] = spec('switch-' . $switch, false, [$switch], $on());
    }
    $count = count(SWITCHES);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $specs[] = spec('pair-' . SWITCHES[$i] . '+' . SWITCHES[$j], false, [SWITCHES[$i], SWITCHES[$j]], $on());
        }
    }
    $specs[] = spec('portal', false, SWITCHES, $on(), 'portal');
    $specs[] = spec('coordinator-with-ministry-scope', false, ['ManageMyMinistries'], $on(), 'staff', [
        ['type' => 'ministry', 'id' => 42],
    ]);
    $specs[] = spec('coordinator-without-scope', false, ['ManageMyMinistries'], $on());
    $specs[] = spec('module-off-finance', true, [], $on(finance: false));
    $specs[] = spec('module-off-fundraiser', true, [], $on(fundraiser: false));
    $specs[] = spec('module-off-events', true, [], $on(events: false));
    $specs[] = spec('module-off-volunteer-v1', true, [], $on(volunteer: 'v1'));

    $recorded = [];
    foreach ($specs as $index => $spec) {
        $recorded[] = record($spec, 1000 + $index);
    }

    return $recorded;
}

/**
 * @param list<string> $switches
 * @param array<string, bool|string> $modules
 * @param list<array{type: string, id: int}> $scopes
 * @return array<string, mixed>
 */
function spec(
    string $name,
    bool $admin,
    array $switches,
    array $modules,
    string $principal = 'staff',
    array $scopes = [],
): array {
    return [
        'name' => $name,
        'principal' => $principal,
        'admin' => $admin,
        'switches' => $switches,
        'modules' => $modules,
        'scopes' => $scopes,
    ];
}

/**
 * @param array<string, mixed> $spec
 * @return array<string, mixed>
 */
function record(array $spec, int $personId): array
{
    applyModules($spec['modules']);
    replaceScopes($personId, $spec['scopes']);

    $user = $spec['principal'] === 'portal'
        ? PortalMemberUser::forPerson($personId)
        : (new User())->setPersonId($personId);

    $user->setAdmin($spec['admin']);
    $on = array_flip($spec['switches']);
    $user->setAddRecords(isset($on['AddRecords']));
    $user->setEditRecords(isset($on['EditRecords']));
    $user->setDeleteRecords(isset($on['DeleteRecords']));
    $user->setManageGroups(isset($on['ManageGroups']));
    $user->setFinance(isset($on['Finance']));
    $user->setManageFundraisers(isset($on['ManageFundraisers']));
    $user->setManageMinistries(isset($on['ManageMinistries']));
    $user->setManageMyMinistries(isset($on['ManageMyMinistries']));
    $user->setNotes(isset($on['Notes']));
    $user->addUserConfig(permissionRow('bAddEvent', isset($on['AddEvent'])));
    $user->addUserConfig(permissionRow('bEmailMailto', isset($on['Email'])));

    $security = [];
    foreach (SECURITY_NAMES as $name) {
        $security[$name] = $user->isEnabledSecurity($name);
    }

    $spec['personId'] = $personId;
    $spec['answers'] = [
        'isAdmin' => $user->isAdmin(),
        'isAddRecordsEnabled' => $user->isAddRecordsEnabled(),
        'isEditRecordsEnabled' => $user->isEditRecordsEnabled(),
        'isDeleteRecordsEnabled' => $user->isDeleteRecordsEnabled(),
        'isManageGroupsEnabled' => $user->isManageGroupsEnabled(),
        'isFinanceEnabled' => $user->isFinanceEnabled(),
        'isManageFundraisersEnabled' => $user->isManageFundraisersEnabled(),
        'isManageMinistriesEnabled' => $user->isManageMinistriesEnabled(),
        'isManageMyMinistriesEnabled' => $user->isManageMyMinistriesEnabled(),
        'isNotesEnabled' => $user->isNotesEnabled(),
        'canReadNotes' => $user->canReadNotes(),
        'isAddEventEnabled' => $user->isAddEventEnabled(),
        'isAddEvent' => $user->isAddEvent(),
        'isEmailEnabled' => $user->isEmailEnabled(),
        'canViewEvents' => $user->canViewEvents(),
        'canManageEvents' => $user->canManageEvents(),
        'canWriteEvents' => $user->canWriteEvents(),
        'isEventsEnabled' => User::isEventsEnabled(),
        'isVolunteerV1Enabled' => User::isVolunteerV1Enabled(),
        'isVolunteerV2Enabled' => User::isVolunteerV2Enabled(),
        'isVolunteerCoordinatorEnabled' => $user->isVolunteerCoordinatorEnabled(),
        'isVolunteerTeamLeaderEnabled' => $user->isVolunteerTeamLeaderEnabled(),
        'canReadFamily' => $user->canReadFamily(OTHER_PERSON_ID),
        'canReadPerson' => $user->canReadPerson(OTHER_PERSON_ID),
        'canReadAttendanceSelf' => $user->canReadAttendance($personId),
        'canReadAttendanceOther' => $user->canReadAttendance(OTHER_PERSON_ID),
        'canReadPrivateNotes' => $user->canReadPrivateNotes(),
        'canWriteNoteOnFamily' => $user->canWriteNoteOnFamily(),
        'isEnabledSecurity' => $security,
    ];

    return $spec;
}

function permissionRow(string $name, bool $granted): UserConfig
{
    $row = new UserConfig();
    $row->setName($name);
    $row->setPermission($granted ? 'TRUE' : 'FALSE');

    return $row;
}

/**
 * @param array<string, bool|string> $modules
 */
function applyModules(array $modules): void
{
    $configs = [];
    foreach ($modules as $name => $value) {
        $stored = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        $bool = is_bool($value) ? $value : false;
        $configs[$name] = new class($stored, $bool) {
            public function __construct(private string $stored, private bool $bool)
            {
            }

            public function getValue(): string
            {
                return $this->stored;
            }

            public function getBooleanValue(): bool
            {
                return $this->bool;
            }
        };
    }

    (new ReflectionProperty(SystemConfig::class, 'configs'))->setValue(null, $configs);
}

function prepareScopeStore(): void
{
    $container = Propel::getServiceContainer();
    $container->setAdapterClass('default', SqliteAdapter::class);
    $manager = new ConnectionManagerSingle('default');
    $manager->setConfiguration([
        'dsn' => 'sqlite::memory:',
        'user' => '',
        'password' => '',
    ]);
    $container->setConnectionManager($manager);
    $container->setDefaultDatasource('default');
    $container->initDatabaseMaps([
        'default' => [VolunteerScopeTableMap::class],
    ]);

    Propel::getConnection('default')->exec(
        'CREATE TABLE volunteer_scope_vscp (
            vscp_ID INTEGER PRIMARY KEY AUTOINCREMENT,
            vscp_per_ID INTEGER NOT NULL,
            vscp_ScopeType TEXT NOT NULL,
            vscp_ScopeId INTEGER NOT NULL,
            vscp_GrantedDate TEXT NOT NULL,
            vscp_GrantedBy_per_ID INTEGER
        )',
    );
}

/**
 * @param list<array{type: string, id: int}> $scopes
 */
function replaceScopes(int $personId, array $scopes): void
{
    $connection = Propel::getConnection('default');
    $delete = $connection->prepare('DELETE FROM volunteer_scope_vscp WHERE vscp_per_ID = :person');
    $delete->execute([':person' => $personId]);

    $insert = $connection->prepare(
        'INSERT INTO volunteer_scope_vscp
            (vscp_per_ID, vscp_ScopeType, vscp_ScopeId, vscp_GrantedDate)
         VALUES (:person, :type, :scope, :granted)',
    );
    foreach ($scopes as $scope) {
        $insert->execute([
            ':person' => $personId,
            ':type' => $scope['type'],
            ':scope' => $scope['id'],
            ':granted' => '2026-01-01 00:00:00',
        ]);
    }
}
