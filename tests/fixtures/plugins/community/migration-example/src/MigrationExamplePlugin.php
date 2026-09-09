<?php

namespace ChurchCRM\Plugins\MigrationExample;

use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Plugins\MigrationExample\Model\EntryQuery;
use ChurchCRM\Plugins\MigrationExample\Model\Map\EntryTableMap;
use Propel\Runtime\Propel;

class MigrationExamplePlugin extends AbstractPlugin
{
    public static int $boots = 0;
    public static int $deactivations = 0;
    public static int $uninstalls = 0;
    public static int $jobs = 0;

    public function getId(): string
    {
        return 'migration-example';
    }

    public function getName(): string
    {
        return 'Migration Example';
    }

    public function getDescription(): string
    {
        return 'Migration integration test fixture';
    }

    public function boot(): void
    {
        Propel::getServiceContainer()->initDatabaseMaps(['default' => [EntryTableMap::class]]);
        // Fails if core boots plugin code before applying its required schema.
        EntryQuery::create()->count();
        self::$boots++;
        HookManager::addAction(Hooks::CRON_RUN, static function (): void {
            self::$jobs++;
        });
        HookManager::addFilter('migration-example.filter', static fn (string $value): string => $value . ':fixture');
    }

    public function deactivate(): void
    {
        self::$deactivations++;
    }

    public function uninstall(): void
    {
        // Deliberate destructive legacy callback: core must NEVER call it for
        // a migration plugin. The integration test verifies records survive.
        self::$uninstalls++;
        EntryQuery::create()->deleteAll();
    }
}
