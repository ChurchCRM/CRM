# Migration maintenance and recovery

This is the proposed conservative recovery policy for the development migration
API, subject to maintainer agreement. It is not an automatic repair mechanism.

## Read the durable state correctly

The core ledger is `plugin_migration_pmg`; configuration is not migration history.

| Durable state after reconnection | What is known | Automatic behavior |
|---|---|---|
| No row for the next ID | Core has not committed an attempt | Validate approval/history/resources, then attempt |
| StartedAt set, AppliedAt NULL, before DDL | Intent committed, SQL might not have started | Block replay |
| StartedAt set, AppliedAt NULL, possible partial effects | Effects cannot be inferred from the ledger | Block replay |
| StartedAt set, AppliedAt NULL, DDL completed | Completion was not committed | Block replay |
| Both timestamps set | DDL returned successfully and completion committed | Do not run this resource again |

The three unresolved rows above have the **same physical representation**.
Only a test barrier or external forensic evidence distinguishes their phases;
the service does not guess. A transaction rollback cannot undo MySQL/MariaDB
DDL. The guarantee is no silent automatic replay of uncertain attempts, not an
atomic exactly-once transaction joining DDL to its ledger update.

The implementation commits intent in
[PluginMigrationManager::applyPending](../../src/ChurchCRM/Plugin/PluginMigrationManager.php),
then executes the same buffered SQL whose checksum was validated, then saves
AppliedAt. `checkHistory()` rejects a NULL completion before further execution.
`withLock()` uses connection-scoped GET_LOCK/RELEASE_LOCK with a hash of the
selected database and plugin ID; that lock survives DDL implicit commits and
is released on connection death. It is not a global maintenance barrier.

Each resource has one statement, so there is no intra-resource statement gap.
After a completed resource and before the next, a restart can run only the next
never-started resource. After completion but before activation, re-enable checks
history and activates without replaying completed SQL.

## Minimum consistent recovery unit

For this policy, keep these together at one quiescent point:

- The **whole ChurchCRM database**, including every plugin table, core data,
  schema objects, successful migration rows and unresolved attempt rows.
- Matching ChurchCRM and plugin packages, manifests, immutable migration bytes,
  generated models, locked dependency versions and installed version metadata.
- Configuration, approved installation provenance, encryption keys and relevant
  external assets such as retained documents or uploaded images.

A database dump alone does not back up package files or external assets. Core's
backup library includes all database tables, but that is not evidence that every
installation's complete recovery unit has been captured. Inventory and test it.

## Maintenance procedure

1. Identify the exact approved release and pending resources. Check core ledger
   installation, approval, capabilities and checksums. A validation failure with
   **no newly committed attempt** needs correction of that preflight condition,
   not a destructive database restore. Preserve any older unresolved attempt.
2. Enter a maintenance window. Block browser/API writes at the ingress, stop cron
   and workers, pause donation integrations and webhook consumers with a known
   replay/reconciliation plan, and drain requests already in progress. Stop
   external schedulers too. Retain only a controlled Admin path for migration.
3. Capture and verify the consistent recovery unit above. Keep all writers
   stopped from backup until migration validation or recovery is complete.
   Rehearse restoring into an isolated empty database before relying on it.
4. Install the approved package and enable as Admin. Validate schema readiness,
   plugin model operations, application health and retained records. If successful,
   resume writers and reconcile paused integrations without duplicating gifts.
5. For an unresolved attempt, keep the plugin unavailable and writers stopped.
   Preserve the failed database, package versions and logs for investigation.
   Consult the maintainer; do not delete the attempt, mark it applied, edit SQL,
   or assume IF NOT EXISTS proves that the retained schema is correct.
6. If using the proposed restore policy, restore the entire pre-migration unit
   into an **empty schema** (or an isolated replacement database, followed by a
   controlled switch). Importing over existing tables can leave post-backup
   tables behind. ChurchCRM's normal restore path uses `SQLUtils::dropAllTables`
   followed by `SQLUtils::sqlImport`; these operations destroy the target state.
   Verify the target and retain the failed snapshot before invoking them.
7. Restore matching code, configuration and assets; restart PHP/workers to clear
   loaded classes and metadata. Confirm unrelated core records as well as plugin
   records, schema and ledger. Apply a corrected, approved release as appropriate,
   then validate and resume writers. Do not resume an incompatible package.

If unrelated writes occurred after backup, a whole-database restore loses them.
This is an operational incident requiring explicit reconciliation; a plugin lock
does not prevent that loss. The downtime and full recovery-unit requirement are
maintainer decisions. A future targeted reconciliation API is a separate design.

## Executable evidence and limits

[failure-recovery.php](../../tests/plugin-migrations/failure-recovery.php) runs
real subprocesses against a disposable database. The parent terminates workers
at before-DDL, after-DDL/before-success, and after-success/before-activation
barriers in a test-only connection wrapper. The production service has no fault
injection configuration. Other connections cannot enable, disable or uninstall
while the worker holds the lock, including after DDL; other plugin/database lock
names remain independent. Reconnection verifies committed attempt/completion
state and refuses uncertain replay.

The restore test imports the full core Install.sql through the real importer,
uses ChurchCRM's locked Mysqldump dependency for a complete disposable database
dump, interrupts an ALTER, and restores through the real core empty-schema/import
helpers. It verifies the fixture entry, an unrelated `sChurchName` core record,
ledger, removed post-backup column/table, matching package and subsequent upgrade.
Deliberate newer core/plugin writes disappear after restore, demonstrating the
maintenance requirement. No dump, credentials or fixture data is published.

This tests process death, not database-server power loss, replication failover or
storage durability settings. It does not prove capture/recovery of arbitrary
external assets or payment providers. Linux engine-specific results belong in
the [implementation handoff](migration-implementation-handoff.md); a configured
workflow is not an executed result.
