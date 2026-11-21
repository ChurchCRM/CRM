<?php

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Service\DirectoryReportPreferences;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

/**
 * In-memory User stand-in so preference pack/load can be tested without the DB.
 * getSetting() returns null for never-stored keys (same contract as User).
 */
final class DirectoryReportPreferencesMemoryUser extends User
{
    /** @var array<string, string> */
    private array $settings = [];

    public function __construct()
    {
    }

    public function setSetting($name, $value): void
    {
        $this->settings[(string) $name] = (string) $value;
    }

    public function getSetting($name)
    {
        $key = (string) $name;
        if (!array_key_exists($key, $this->settings)) {
            return null;
        }

        return (object) ['name' => $key, 'value' => $this->settings[$key]];
    }

    public function getSettingValue($name)
    {
        return $this->settings[(string) $name] ?? '';
    }

    /** @return array<string, string> */
    public function allSettings(): array
    {
        return $this->settings;
    }
}

$prefs = new DirectoryReportPreferences();
$user = new DirectoryReportPreferencesMemoryUser();

// More IDs than fit in one VARCHAR(50) CSV (each "NN," is 3 chars → 40 ids need multiple chunks).
$manyIds = range(0, 39);

$prefs->save($user, [
    'classifications' => $manyIds,
    'groups' => [],
    'excludeInactive' => true,
    'headRoles' => [1],
    'spouseRoles' => [2],
    'show' => [
        'address' => true,
        'mailing' => false,
        'wedding' => true,
        'birthday' => true,
        'familyPhone' => true,
        'familyWork' => true,
        'familyCell' => true,
        'personalPhone' => true,
        'personalWork' => true,
        'personalCell' => true,
        'personalEmail' => true,
        'personalWorkEmail' => true,
        'photo' => true,
    ],
    'customFieldOrders' => [],
]);

Assert::same('1', $user->getSettingValue(DirectoryReportPreferences::SAVED));
Assert::true(ctype_digit($user->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS)));
Assert::true(
    (int) $user->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS) > 1,
    'overflowing classification lists must use more than one chunk'
);

foreach ($user->allSettings() as $name => $value) {
    if (str_starts_with($name, DirectoryReportPreferences::CLASSIFICATIONS . '.')) {
        Assert::true(strlen($value) <= 50, $name . ' exceeds VARCHAR(50)');
    }
}

$loaded = $prefs->load($user);
Assert::notNull($loaded);
Assert::same($manyIds, $loaded['classifications'], 'every classification id must round-trip without truncation');
Assert::same([1], $loaded['headRoles']);
Assert::same([2], $loaded['spouseRoles']);
Assert::true($loaded['excludeInactive']);
Assert::true($loaded['show']['address']);
Assert::false($loaded['show']['mailing']);

// Regression test for #11587: long classification and group lists
// must not be silently truncated after 40 chunks.
$longListUser = new DirectoryReportPreferencesMemoryUser();
$longIds = range(0, 999);

$prefs->save($longListUser, [
    'classifications' => $longIds,
    'groups' => $longIds,
    'excludeInactive' => false,
    'headRoles' => [],
    'spouseRoles' => [],
    'show' => [],
    'customFieldOrders' => [],
]);

$longListLoaded = $prefs->load($longListUser);
Assert::notNull($longListLoaded);
Assert::same(
    $longIds,
    $longListLoaded['classifications'],
    'long classification lists must not be truncated'
);
Assert::same(
    $longIds,
    $longListLoaded['groups'],
    'long group lists must not be truncated'
);

$prefs->clear($user);
Assert::null($prefs->load($user));
Assert::same('', $user->getSettingValue(DirectoryReportPreferences::SAVED));
Assert::same('', $user->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS));
Assert::same('', $user->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS . '.1'));

// Attacker-controlled chunk count via user-settings API must not drive unbounded loops
// or invent rows for indexes that were never stored (maintainer hard block on #11571).
$forged = new DirectoryReportPreferencesMemoryUser();
$forged->setSetting(DirectoryReportPreferences::SAVED, '1');
$forged->setSetting(DirectoryReportPreferences::CLASSIFICATIONS, '999999999');
$forged->setSetting(DirectoryReportPreferences::CLASSIFICATIONS . '.1', '10,11');
$forged->setSetting(DirectoryReportPreferences::EXCLUDE_INACTIVE, '0');
$forged->setSetting(DirectoryReportPreferences::SHOW, str_repeat('0', count(DirectoryReportPreferences::SHOW_KEYS)));

$forgedLoaded = $prefs->load($forged);
Assert::notNull($forgedLoaded);
Assert::same([10, 11], $forgedLoaded['classifications'], 'load must stop at the first missing chunk');

$prefs->clear($forged);
Assert::same('', $forged->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS));
Assert::same('', $forged->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS . '.1'));
Assert::null(
    $forged->getSetting(DirectoryReportPreferences::CLASSIFICATIONS . '.2'),
    'clear must not invent empty chunk keys for a forged count'
);
$forgedChunkKeys = array_filter(
    array_keys($forged->allSettings()),
    static fn (string $name): bool => str_starts_with($name, DirectoryReportPreferences::CLASSIFICATIONS . '.')
);
Assert::true(
    count($forgedChunkKeys) <= 1,
    'clear against a forged count must not invent numbered chunk keys'
);

$resave = new DirectoryReportPreferencesMemoryUser();
$resave->setSetting(DirectoryReportPreferences::CLASSIFICATIONS, '999999999');
$prefs->save($resave, [
    'classifications' => [7],
    'groups' => [],
    'excludeInactive' => false,
    'headRoles' => [],
    'spouseRoles' => [],
    'show' => [],
    'customFieldOrders' => [],
]);
Assert::same('1', $resave->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS));
Assert::same('7', $resave->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS . '.1'));
Assert::null(
    $resave->getSetting(DirectoryReportPreferences::CLASSIFICATIONS . '.2'),
    'save must not invent empty chunk keys up to a forged previous count'
);
Assert::true(
    (int) $resave->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS) <= DirectoryReportPreferences::MAX_CHUNKS
);
