<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

SystemURLs::init('', [], APP_ROOT);
$propertiesFor = static function (array $seed): array {
    $rows = [];
    foreach ($seed as $name => $value) {
        $rows[] = new class($name, $value) {
            public bool $deleted = false;

            public function __construct(private string $name, private string $value) {}
            public function getName(): string { return $this->name; }
            public function getValue(): string { return $this->value; }
            public function delete(): void { $this->deleted = true; }
        };
    }
    SystemConfig::init($rows, true);
    foreach ($rows as $row) {
        Assert::false($row->deleted);
        Assert::same($row->getValue(), SystemConfig::getValue($row->getName()));
    }

    return TelemetryService::baseProperties();
};

Assert::same('softaculous', $propertiesFor(['sInstallSource' => 'softaculous'])['install_source']);
Assert::same('some-other-installer', $propertiesFor(['sInstallSource' => 'some-other-installer'])['install_source']);
Assert::same('softaculous', $propertiesFor(['sInstallSource' => '  softaculous  '])['install_source']);

Assert::false(array_key_exists('install_source', $propertiesFor(['sInstallSource' => ''])));
Assert::false(array_key_exists('install_source', $propertiesFor(['sInstallSource' => '   '])));
Assert::false(array_key_exists('install_source', $propertiesFor([])));
Assert::same('', SystemConfig::getValue('sInstallSource'));
