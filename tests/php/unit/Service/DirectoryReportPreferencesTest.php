<?php

declare(strict_types=1);

use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Service\DirectoryReportPreferences;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

/**
 * In-memory User stand-in so preference pack/load can be tested without the DB.
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

$prefs->clear($user);
Assert::null($prefs->load($user));
Assert::same('', $user->getSettingValue(DirectoryReportPreferences::SAVED));
Assert::same('', $user->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS));
Assert::same('', $user->getSettingValue(DirectoryReportPreferences::CLASSIFICATIONS . '.1'));
