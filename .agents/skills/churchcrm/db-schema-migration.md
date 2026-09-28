---
title: "DB Schema Migration"
intent: "Safe procedures for database schema changes and versioning"
tags: ["database","migration","schema","backup"]
prereqs: ["[[database-operations]]","[[development-workflows]]"]
complexity: "advanced"
---

# Database Schema Migration

Guidelines:
- Avoid unnecessary schema changes when moving routes; prefer new tables for group metadata if needed.
- Use Perpl ORM migrations (`orm/schema.xml` and propel config) and generate migration SQL via tooling.

Process:
1. Create migration script and review with DB team.
2. Run migration on staging and smoke-test application.
3. Backup production DB before applying changes; have rollback scripts ready.
4. Apply migrations during maintenance windows; monitor for errors.

Backward compatibility:
- Add new nullable columns or new tables initially; populate via background jobs; make fields non-null in a step-change.

Rollback strategy:
- Have explicit down-migration scripts; snapshot DB; monitor replication lag.

### Adding a New Version with DB Changes <!-- learned: 2026-04-27 -->

When a new release ships with a DB migration SQL file, use a **two-block pattern** in `upgrade.json`:

1. **Rename `current` → `pre-X.Y.Z`** where X.Y.Z is the version being released. Set `dbVersion` to the intermediate stepping-stone version (the version just before the new release):

```json
"pre-7.3.1": {
  "versions": ["7.2.0", "7.2.1", "7.2.2", "7.2.3"],
  "scripts": [],
  "dbVersion": "7.3.0"
}
```

2. **Create a new `current` block** that runs the SQL file and sets `dbVersion` to the new release:

```json
"current": {
  "versions": ["7.3.0"],
  "scripts": ["/mysql/upgrade/7.3.1-cleanup.sql"],
  "dbVersion": "7.3.1"
}
```

**Why this works:** `UpgradeService` calls `VersionUtils::getDBVersion()` fresh on every iteration (live DB query). So a 7.2.x install first matches `pre-7.3.1` (no scripts → DB set to 7.3.0), then on the next iteration matches `current` (runs the SQL → DB set to 7.3.1). Installs already on 7.3.0 skip `pre-7.3.1` entirely and go straight to `current`.

Name SQL files after the target version: `7.3.1-<description>.sql`. Use full paths: `/mysql/upgrade/7.3.1-cleanup.sql`.

### When to Add to "current" vs Create a New Version Block <!-- learned: 2026-08-08 -->

**`current` block rules:**

- `current` represents the **ongoing development version** currently in development
- Multiple cleanup/fix scripts in the same release go into the same `scripts` array — **do not bump the version**, keep all scripts targeting the same `dbVersion`:
  ```json
  "current": {
    "versions": ["7.5.1"],
    "scripts": [
      "/mysql/upgrade/7.6.0-remove-orphaned-query-parameters.sql",
      "/mysql/upgrade/7.6.0-remove-legacy-custom-search-query.sql"
    ],
    "dbVersion": "7.6.0"
  }
  ```
- **Add to the existing `current` block** when:
  - You're adding a cleanup/fix script to the version currently in development
  - The script targets the same `dbVersion` the `current` block is building toward
  - Keep `dbVersion` unchanged — all scripts for a release target the same version
  - Name the script file to match that version (e.g., `7.6.0-description.sql`)

- **Create a new `pre-X.Y.Z` block** only when:
  - Shipping a new release — moving `current` to `pre-X.Y.Z` and creating a new `current` for the next version
  - Do NOT create intermediate blocks — the upgrade path is: old version → `pre-X.Y.Z` → new `current`

**Example flow:**
1. Developing 7.6.0: add multiple cleanup scripts to `current`, all target `dbVersion: 7.6.0`
2. Release 7.6.0 ships → rename old `current` to `pre-7.5.1` with `dbVersion: 7.6.0`, create new `current` for 7.7.0
3. Developing 7.7.0: add cleanup scripts to the new `current`, all target `dbVersion: 7.7.0`

### Removing a Dead Table: Full Checklist <!-- learned: 2026-04-27 -->

When removing an unused DB table, touch **all four locations** — missing any leaves dead schema or broken installs:

| File | Action |
|------|--------|
| `src/mysql/install/Install.sql` | Delete the `CREATE TABLE` block (new installs must not create it) |
| `cypress/data/seed.sql` | Delete the `DROP/CREATE TABLE` + data block (Cypress resets must not recreate it) |
| `orm/schema.xml` | Delete the `<table>` element (or its commented-out wrapper if already disabled) |
| `src/mysql/upgrade/X.Y.Z-cleanup.sql` | Add `DROP TABLE IF EXISTS` (existing installs need the cleanup on upgrade) |

Register the cleanup script in `upgrade.json` `current` block using the two-block pattern above. Use `DROP TABLE IF EXISTS` so the script is idempotent on fresh installs.

### Keep Install.sql in Sync with Every Migration <!-- learned: 2026-04-29 -->

`src/mysql/install/Install.sql` is the canonical schema for **new installs**. Whenever you add an upgrade SQL that alters a table (charset, new column, dropped column, index change), you **must also apply the same change in Install.sql** so fresh installations are identical to upgraded ones.

**Always update Install.sql. Ask the user before editing seed.sql** — it contains Cypress test data that may need regeneration.

Checklist for any `ALTER TABLE` migration:

| File | Action |
|------|--------|
| `src/mysql/upgrade/X.Y.Z-<desc>.sql` | The `ALTER TABLE` statement |
| `src/mysql/install/Install.sql` | Apply the same change to the `CREATE TABLE` block — required |
| `cypress/data/seed.sql` | Update the `CREATE TABLE` block — **ask user first** |
| `orm/schema.xml` | Update column/table attributes if Propel schema tracks this |

### MySQL-Compatible Conditional Column Changes <!-- learned: 2026-07-27, corrected/expanded: 2026-09-16 -->

**Column-level `IF EXISTS` / `IF NOT EXISTS` is a MariaDB-only extension on
`ADD COLUMN`, `DROP COLUMN`, `CHANGE COLUMN`, and `MODIFY COLUMN` alike** —
not just `DROP COLUMN` as an earlier version of this note said. MySQL (any
version including 9.x) rejects all four with `SQLSTATE[42000] error 1064`.
This breaks upgrade paths on MySQL while silently passing on MariaDB, since
`docker/docker-compose.mysql.yaml`'s nightly matrix is the only CI job that
catches it — the default MariaDB-based test/CI jobs never will.

**Confirmed the hard way:** `src/mysql/upgrade/7.7.0-2fa-grace-period.sql`
shipped with `ADD COLUMN IF NOT EXISTS` (#5169) and broke every nightly
"Build, Test and Package" MySQL-matrix run (`churchcrm-mysql`, `v6-mysql`,
both PHP 8.4/8.5) from 2026-09-10 onward — the upgrade script threw a 1064,
`UpgradeService::upgradeDatabaseVersion()` caught it, and the app was
permanently parked on `/external/system/db-upgrade`. Fixed by removing the
guard (the version-gated runner already guarantees the column doesn't exist
yet — see below) and adding `scripts/validate-mysql-upgrade-syntax.js`,
which now runs in CI (`.github/workflows/code-quality.yml`) and in
`.githooks/pre-commit` (`--staged` mode) to catch this class of mistake
before it ships again.

**Wrong (MariaDB-only, all four forms):**
```sql
ALTER TABLE my_table ADD COLUMN IF NOT EXISTS new_col INT;
ALTER TABLE my_table DROP COLUMN IF EXISTS old_col;
ALTER TABLE my_table CHANGE COLUMN IF EXISTS old_col new_col INT;
ALTER TABLE my_table MODIFY COLUMN IF EXISTS some_col INT;
```

**Correct (MySQL 8.0+ and MariaDB 10.2+):**
```sql
-- When version-gating GUARANTEES the column's existence state: just plain DDL,
-- no guard. mysql/upgrade.json gates every script by its exact starting
-- dbVersion, so a script that ADDs a column can assume it doesn't exist yet,
-- and a script that DROPs one can assume it still does.
ALTER TABLE my_table ADD COLUMN new_col INT;
ALTER TABLE my_table DROP COLUMN old_col;

-- When the column might not exist (e.g. reachable via more than one upgrade
-- path) — use an information_schema guard instead of IF EXISTS:
SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='my_table' AND COLUMN_NAME='old_col')>0,
    'ALTER TABLE `my_table` DROP COLUMN `old_col`',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;
```

Also note: `CREATE TABLE IF NOT EXISTS`, `DROP TABLE IF EXISTS`,
`ADD INDEX IF NOT EXISTS`, and `DROP INDEX IF EXISTS` all work fine on both
MySQL and MariaDB (confirmed: `src/mysql/upgrade/7.6.2-add-missing-fk-indexes.sql`
uses `ADD INDEX IF NOT EXISTS` and passes the MySQL nightly matrix). Only the
**column-level** variant is MariaDB-only.

Similarly, `@var:=expr` assignments inside DML (UPDATE SET, SELECT) are deprecated since MySQL 8.0.22 and may cause warnings; prefer `ROW_NUMBER() OVER (ORDER BY ...)` for row numbering in migrations.

### Use utf8mb4 for All User-Content Tables <!-- learned: 2026-04-29 -->

MySQL `utf8` / `utf8mb3` is 3-byte max and silently fails on emoji and other 4-byte Unicode (`SQLSTATE[22007] Incorrect string value`). Any table that stores user-generated text must use `utf8mb4_unicode_ci`.

```sql
-- ✅ New tables
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ✅ Upgrading existing tables
ALTER TABLE `note_nte` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
