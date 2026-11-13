<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugins\Confession\ConfessionPlugin;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

final class ConfessionConfigRow
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

final class TestConfessionPlugin extends ConfessionPlugin
{
    public function __construct(public array $config = [])
    {
        parent::__construct();
    }

    protected function getConfigValue(string $key): string
    {
        return $this->config[$key] ?? '';
    }

    protected function setConfigValue(string $key, string $value): void
    {
        $this->config[$key] = $value;
    }
}

$legacyFatherConfig = new ConfessionConfigRow('iPersonConfessionFatherCustomField', '51');
$legacyDateConfig = new ConfessionConfigRow('iPersonConfessionDateCustomField', '52');
SystemConfig::init([
    $legacyFatherConfig,
    $legacyDateConfig,
    new ConfessionConfigRow('sDateFormatLong', 'm/d/Y'),
]);

$plugin = new TestConfessionPlugin(['fatherFieldId' => '81']);
$plugin->boot();
Assert::same('81', $plugin->config['fatherFieldId'], 'existing plugin values are not overwritten');
Assert::same('52', $plugin->config['dateFieldId'], 'the legacy date field is migrated');
Assert::same('1', $plugin->config['legacySettingsMigrated'], 'migration is recorded');
Assert::true($plugin->isConfigured(), 'both field settings make the plugin configured');
Assert::false((new TestConfessionPlugin(['fatherFieldId' => '0', 'dateFieldId' => '52']))->isConfigured());

$newPlugin = new TestConfessionPlugin();
$newPlugin->boot();
Assert::same('51', $newPlugin->config['fatherFieldId'], 'the legacy confessor field is migrated');
Assert::same('52', $newPlugin->config['dateFieldId'], 'the legacy date field is migrated');
Assert::true(!$legacyFatherConfig->deleted && !$legacyDateConfig->deleted, 'legacy config rows are preserved');

$datePlugin = new TestConfessionPlugin(['dateFieldId' => '6']);
$dateField = new class {
    public function getId(): int
    {
        return 6;
    }

    public function getTypeId(): int
    {
        return 2;
    }

    public function getName(): string
    {
        return '<b>Last confession</b>';
    }

    public function getSpecial(): ?string
    {
        return null;
    }
};

$content = $datePlugin->addPersonViewContent('', new Person(), [$dateField], [6 => '2025-03-05']);
Assert::contains('id="person-confession"', $content);
Assert::contains('&lt;b&gt;Last confession&lt;/b&gt;', $content);
Assert::notContains('<b>Last confession</b>', $content);
Assert::same([], $datePlugin->filterAdditionalInformationFields([$dateField]), 'configured confession fields are grouped separately');
Assert::same(
    'existing',
    $datePlugin->addPersonViewContent('existing', new Person(), [], [6 => '2025-03-05']),
    'fields not made visible by core are not displayed'
);

HookManager::reset();
