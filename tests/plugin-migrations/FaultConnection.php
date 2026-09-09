<?php

declare(strict_types=1);

use Propel\Runtime\Connection\ConnectionWrapper;

/** Real database connection with a pipe barrier at a selected crash boundary. */
final class MigrationFaultConnection extends ConnectionWrapper
{
    private bool $ddlCompleted = false;

    public function exec($statement): int
    {
        $ddl = preg_match('/^\s*(?:CREATE|ALTER) TABLE plugin_migration_example__/i', $statement) === 1;
        if ($ddl && MIGRATION_TEST_FAULT_PHASE === 'before-ddl') {
            $this->pause();
        }
        $result = parent::exec($statement);
        if ($ddl) {
            $this->ddlCompleted = true;
            if (MIGRATION_TEST_FAULT_PHASE === 'after-ddl') {
                $this->pause();
            }
        }
        return $result;
    }

    public function commit(): bool
    {
        $result = parent::commit();
        if ($this->ddlCompleted && MIGRATION_TEST_FAULT_PHASE === 'after-success' && !$this->inTransaction()) {
            $this->pause();
        }
        return $result;
    }

    private function pause(): void
    {
        echo 'barrier=' . MIGRATION_TEST_FAULT_PHASE . "\n";
        fflush(STDOUT);
        // Parent kills this process without releasing the barrier. Timeout
        // prevents stranding a worker if its parent fails.
        stream_set_timeout(STDIN, 30);
        fgets(STDIN);
        exit(90);
    }
}
