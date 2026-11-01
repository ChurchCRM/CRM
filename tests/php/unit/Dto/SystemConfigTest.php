<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);

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
Assert::true(!$kept['retired']->deleted, 'database not current: a retired key\'s row is kept for the upgrade');
Assert::true(!$kept['declared']->deleted && SystemConfig::getValue('sChurchName') === 'Grace Church', 'database not current: a declared key is read');

$upgradeScript = $rows();
SystemConfig::init(array_values($upgradeScript));
Assert::true(!$upgradeScript['retired']->deleted, 'an upgrade script\'s own init() keeps a retired key\'s row');

$pruned = $rows();
SystemConfig::init(array_values($pruned), true);
Assert::true($pruned['retired']->deleted, 'database current: a retired key\'s row is deleted');
Assert::true(!$pruned['declared']->deleted, 'database current: a declared key\'s row stays');
Assert::true(!$pruned['plugin']->deleted, 'database current: a plugin row stays');
