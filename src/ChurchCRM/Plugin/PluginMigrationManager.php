<?php

namespace ChurchCRM\Plugin;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Map\PluginMigrationTableMap;
use ChurchCRM\model\ChurchCRM\PluginMigration;
use ChurchCRM\model\ChurchCRM\PluginMigrationQuery;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

/** Core-only forward DDL execution. All ledger/application data access uses Propel. */
final class PluginMigrationManager
{
    /** @param array<string, mixed>|null $entry Verified registry grant, not plugin-supplied data. */
    public static function validateApproval(PluginMetadata $plugin, ?array $entry): void
    {
        if ($plugin->getMigrations() === null) {
            return;
        }
        if ($entry === null || ($entry['id'] ?? null) !== $plugin->getId()
            || ($entry['version'] ?? null) !== $plugin->getVersion()
            || !in_array('db.migrate', $entry['permissions'] ?? [], true)
            || ($entry['risk'] ?? '') !== 'high') {
            throw new PluginMigrationException('Migrations require an approved matching release with db.migrate and high risk. Unverified migration packages are not supported.');
        }
    }

    /** Explicit Admin enable only, before loading any plugin PHP. */
    public static function migrate(PluginMetadata $plugin): void
    {
        if ($plugin->getMigrations() === null && !self::hasHistory($plugin->getId())) {
            return;
        }
        if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
            throw new PluginMigrationException('Only an administrator may apply plugin migrations.');
        }
        self::withLock($plugin->getId(), static function () use ($plugin): void {
            $migrations = PluginMigrationManifest::read($plugin);
            self::assertApprovedResources($plugin, $migrations);
            self::applyPending($plugin, $migrations);
        });
    }

    /**
     * Core lifecycle operations share this lock with the migration runner.
     * MySQL/MariaDB named locks are connection-scoped and reference-counted,
     * so enable can hold it through boot while migrate also protects direct calls.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     * @internal
     */
    public static function withLock(string $pluginId, callable $operation): mixed
    {
        $connection = Propel::getWriteConnection(PluginMigrationTableMap::DATABASE_NAME);
        if ($connection->inTransaction() || $connection->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new PluginMigrationException('Migrations require MySQL/MariaDB outside an application transaction.');
        }
        // Named locks survive DDL implicit commits. Include the database to
        // isolate multiple ChurchCRM installations sharing one server.
        $database = $connection->query('SELECT DATABASE()')->fetchColumn();
        $lockName = 'ccrm:pm:' . substr(hash('sha256', $database . ':' . $pluginId), 0, 55);
        $lock = $connection->prepare('SELECT GET_LOCK(?, 0)');
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new PluginMigrationException('Another request is changing this plugin. Wait for it to finish and try again.');
        }
        try {
            return $operation();
        } finally {
            $release = $connection->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }

    /** @param list<array{id: string, file: string, checksum: string, sql: string}> $migrations */
    private static function applyPending(PluginMetadata $plugin, array $migrations): void
    {
        $connection = Propel::getWriteConnection(PluginMigrationTableMap::DATABASE_NAME);
        try {
            $applied = self::checkHistory($plugin, $migrations, $connection);
            foreach (array_slice($migrations, $applied) as $migration) {
                // Persist intent before DDL. A crash must not make an executed
                // statement look unattempted. save() commits its own transaction.
                $record = new PluginMigration();
                $record->setPluginId($plugin->getId());
                $record->setMigrationId($migration['id']);
                $record->setChecksum($migration['checksum']);
                $record->setStartedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                $record->save($connection);
                try {
                    // Execute exactly the buffered SQL bytes checked and hashed.
                    $connection->exec($migration['sql']);
                    $record->setAppliedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                    $record->save($connection);
                } catch (\Throwable $e) {
                    throw new PluginMigrationException(
                        "Migration {$migration['id']} did not complete. Restore the pre-migration database backup and contact the plugin maintainer; automatic replay is disabled.",
                        0,
                        $e
                    );
                }
            }
        } catch (PluginMigrationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new PluginMigrationException('Cannot access the plugin migration ledger. Complete the core database upgrade and check database connectivity before enabling.', 0, $e);
        }
    }

    /** Read-only on ordinary requests and restarts; never runs pending DDL. */
    public static function assertCurrent(PluginMetadata $plugin): void
    {
        if ($plugin->getMigrations() === null && !self::hasHistory($plugin->getId())) {
            return;
        }
        $migrations = PluginMigrationManifest::read($plugin);
        $applied = self::checkHistory($plugin, $migrations, Propel::getWriteConnection(PluginMigrationTableMap::DATABASE_NAME));
        self::assertApprovedResources($plugin, $migrations);
        if ($applied !== count($migrations)) {
            throw new PluginMigrationException('Plugin migrations are pending. An administrator must enable the plugin to apply them.');
        }
    }

    public static function hasHistory(string $pluginId): bool
    {
        try {
            return PluginMigrationQuery::create()->filterByPluginId($pluginId)->count() > 0;
        } catch (\Throwable $e) {
            // Existing plugins still boot while the core DB upgrade is pending.
            // Do not hide connection failures or errors on a declared migration.
            $cause = $e;
            while ($cause->getPrevious() !== null) {
                $cause = $cause->getPrevious();
            }
            if ((string) $cause->getCode() === '42S02') {
                return false;
            }
            throw $e;
        }
    }

    /** @param list<array{id: string, file: string, checksum: string, sql: string}> $migrations */
    private static function assertApprovedResources(PluginMetadata $plugin, array $migrations): void
    {
        if ($plugin->getMigrations() === null) {
            return;
        }
        $installed = PluginManager::getPluginMetadata($plugin->getId());
        if ($installed === null || realpath($installed->getPath()) !== realpath($plugin->getPath())) {
            throw new PluginMigrationException('Migrations must use the discovered plugin installation, not resources from another directory.');
        }
        self::validateApproval($plugin, ApprovedPluginRegistry::find($plugin->getId()));
        if (!PluginManager::getVerificationStatus($plugin->getId())['verified']) {
            throw new PluginMigrationException('Migrations require verified installation provenance. Reinstall the approved release.');
        }
        $provenance = json_decode((string) SystemConfig::getValue('plugin.' . $plugin->getId() . '.provenance'), true);
        if (!is_array($provenance) || ($provenance['version'] ?? null) !== $plugin->getVersion()
            || !hash_equals((string) ($provenance['migrationFingerprint'] ?? ''), PluginMigrationManifest::fingerprint($migrations))) {
            throw new PluginMigrationException('Migration resources differ from the verified installation. Reinstall the approved release.');
        }
    }

    /**
     * History must remain an unchanged prefix, even after uninstall/reinstall.
     * @param list<array{id: string, file: string, checksum: string, sql: string}> $migrations
     */
    private static function checkHistory(PluginMetadata $plugin, array $migrations, ConnectionInterface $connection): int
    {
        try {
            $history = PluginMigrationQuery::create()->filterByPluginId($plugin->getId())->orderByMigrationId()
                ->setFormatter(ModelCriteria::FORMAT_ON_DEMAND)->find($connection);
        } catch (\Throwable $e) {
            throw new PluginMigrationException('Cannot read the plugin migration ledger. Complete the core database upgrade and check database connectivity.', 0, $e);
        }
        $count = 0;
        foreach ($history as $record) {
            $migration = $migrations[$count] ?? null;
            if ($migration === null || $migration['id'] !== $record->getMigrationId()) {
                throw new PluginMigrationException('Migration history was removed, reordered, or downgraded. Restore a compatible approved release.');
            }
            if (!hash_equals($record->getChecksum(), $migration['checksum'])) {
                throw new PluginMigrationException("Migration {$migration['id']} checksum has changed. Restore the original migration bytes.");
            }
            if ($record->getAppliedAt() === null) {
                throw new PluginMigrationException("Migration {$migration['id']} was interrupted or failed. Restore the pre-migration database backup and contact the maintainer; automatic replay is disabled.");
            }
            $count++;
        }

        return $count;
    }
}
