<?php

namespace ChurchCRM\Service;

use Propel\Runtime\Connection\ConnectionWrapper;

/** Keeps the raw pre-migration journal isolated because Propel tables may not be upgrade-ready yet. */
final class UpgradeProgressRepository
{
    public function __construct(private ConnectionWrapper $connection)
    {
    }

    public function ensureTable(): void
    {
        $this->connection->exec("CREATE TABLE IF NOT EXISTS database_upgrade_progress (
            target_version VARCHAR(50) NOT NULL,
            script_path VARCHAR(255) NOT NULL,
            status VARCHAR(10) NOT NULL,
            started_at DATETIME NOT NULL,
            PRIMARY KEY (target_version, script_path)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function find(string $targetVersion, string $scriptPath): ?array
    {
        $statement = $this->connection->prepare('SELECT status, started_at FROM database_upgrade_progress WHERE target_version = ? AND script_path = ?');
        $statement->execute([$targetVersion, $scriptPath]);
        $progress = $statement->fetch(\PDO::FETCH_ASSOC);

        return $progress === false ? null : $progress;
    }

    public function start(string $targetVersion, string $scriptPath, string $startedAt): void
    {
        $statement = $this->connection->prepare('INSERT INTO database_upgrade_progress (target_version, script_path, status, started_at) VALUES (?, ?, ?, ?)');
        $statement->execute([$targetVersion, $scriptPath, 'started', $startedAt]);
    }

    public function setStatus(string $targetVersion, string $scriptPath, string $status, ?string $expectedStatus = null): bool
    {
        $sql = 'UPDATE database_upgrade_progress SET status = ? WHERE target_version = ? AND script_path = ?';
        $parameters = [$status, $targetVersion, $scriptPath];
        if ($expectedStatus !== null) {
            $sql .= ' AND status = ?';
            $parameters[] = $expectedStatus;
        }
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return $statement->rowCount() > 0;
    }

    public function earliestStart(string $targetVersion): ?string
    {
        $statement = $this->connection->prepare('SELECT MIN(started_at) FROM database_upgrade_progress WHERE target_version = ?');
        $statement->execute([$targetVersion]);
        $startedAt = $statement->fetchColumn();

        return $startedAt === false || $startedAt === null ? null : (string) $startedAt;
    }
}
