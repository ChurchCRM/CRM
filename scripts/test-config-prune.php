<?php

/**
 * scripts/test-config-prune.php — which stored config rows SystemConfig::init()
 * deletes.
 *
 * Usage:
 *   php scripts/test-config-prune.php
 *   npm run test:php
 *
 * A row no ConfigItem declares is deleted only when init() is asked to prune.
 * Bootstrapper asks only once the database is current, so an upgrade script
 * can still read a retired key such as bPortalShowCalendar, even after an
 * earlier upgrade script (7.8.1.php) has called init() itself. plugin.* rows
 * are never deleted. No database is needed: the rows are stand-ins.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 *   2  — vendor autoloader missing
 */

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing {$autoload} — run `composer install` in src/ first.\n");
    exit(2);
}
require $autoload;

SystemURLs::init('', [], __DIR__ . '/../src');

$passed = 0;
$failed = 0;

$check = function (bool $ok, string $label) use (&$passed, &$failed): void {
    $n = $passed + $failed + 1;
    if ($ok) {
        $passed++;
        echo "ok {$n} - {$label}\n";
        return;
    }
    $failed++;
    echo "not ok {$n} - {$label}\n";
};

/** A stored config row, as SystemConfig reads it. */
final class StoredConfigRow
{
    public bool $deleted = false;

    public function __construct(private readonly string $name, private readonly ?string $value)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function delete(): void
    {
        $this->deleted = true;
    }
}

$rows = static fn (): array => [
    'declared' => new StoredConfigRow('sChurchName', 'Grace Church'),
    'retired' => new StoredConfigRow('bRetiredByAnUpgrade', '0'),
    'plugin' => new StoredConfigRow('plugin.example.enabled', '1'),
];

$kept = $rows();
SystemConfig::init(array_values($kept), false);
$check(!$kept['retired']->deleted, 'database not current: a retired key\'s row is kept for the upgrade');
$check(!$kept['declared']->deleted && SystemConfig::getValue('sChurchName') === 'Grace Church', 'database not current: a declared key is read');

$upgradeScript = $rows();
SystemConfig::init(array_values($upgradeScript));
$check(!$upgradeScript['retired']->deleted, 'an upgrade script\'s own init() keeps a retired key\'s row');

$pruned = $rows();
SystemConfig::init(array_values($pruned), true);
$check($pruned['retired']->deleted, 'database current: a retired key\'s row is deleted');
$check(!$pruned['declared']->deleted, 'database current: a declared key\'s row stays');
$check(!$pruned['plugin']->deleted, 'database current: a plugin row stays');

$total = $passed + $failed;
echo "1..{$total}\n";
echo "# {$total} cases, {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
