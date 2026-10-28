# Resuming database upgrades

`src/mysql/upgrade.json` lists the files for each version step in execution order:

```json
{
  "path": "/mysql/upgrade/7.8.1-email-queue.sql",
  "retryable": true
}
```

`retryable` means the entire file is idempotent: executing it again from the start,
including after any of its statements, produces the same result. It does not mean
execution can resume at a statement offset. Missing flags and legacy string entries
default to `false`. SQL and PHP files use the same metadata; the extension selects
how the file runs. Existing files are explicitly marked `false` until verified.

The upgrader saves `started` before executing a file and `complete` after success.
It skips completed files, restarts interrupted retryable files, and refuses to run
an interrupted unsafe file again. The error names the file and asks the administrator
to restore a backup or repair that migration. The database version advances only
after all files in the step complete. A database-scoped advisory lock prevents two
upgrade requests from executing the same files concurrently.

Checkpoints live in `config_cfg`: `dbUpgrade.` followed by the SHA-1 of
`targetVersion:path`. This fits the existing 50-character key. Configuration loading
preserves this namespace. Keep checkpoints when repairing an installation; deleting
a `started` record makes the upgrader treat an unsafe file as new.

DDL commits independently of a checkpoint. A request can die after a statement
commits and before its file is marked complete. Guard columns and indexes using
`information_schema` plus prepared DDL, and preserve allocations before inserting
rows. The 7.8.1 grade seed keeps its list allocation in
`dbUpgrade.7.8.1.gradeList`, including when existing lists occupy ID 6.

The `pre-7.8.1` step replaces the original SQL file with six retryable SQL files,
followed by the existing non-retryable PHP migration. Databases on 7.8.0 use this
step; databases already on 7.8.1 or later skip it. The original SQL remains as the
comparison reference for verification.

Run the integration checks against a disposable server with Composer dependencies
and generated ORM models available:

```sh
UPGRADE_TEST_DSN='mysql:host=127.0.0.1;port=3306' \
UPGRADE_TEST_USER=root UPGRADE_TEST_PASSWORD=secret \
php scripts/test-database-upgrades.php --boundaries
```

The checks create and drop randomly named `upgrade_test_*` schemas. Set
`UPGRADE_TEST_BASELINE` to the 7.8.0 release's `Install.sql` to test the released
schema; otherwise the checks reconstruct the migration's prerequisites from the
current install schema. `--sql-only` runs schema, data, and statement-boundary checks
without Composer. Comparisons exclude migration bookkeeping, version-history data,
and the version table's auto-increment counter. Run on both MySQL and MariaDB.
