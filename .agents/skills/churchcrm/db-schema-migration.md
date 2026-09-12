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

### MySQL-Compatible Conditional Column Drops <!-- learned: 2026-07-27 -->

`ALTER TABLE ... DROP COLUMN IF EXISTS` is a **MariaDB-only extension** — MySQL (any version including 9.x) rejects it with `SQLSTATE[42000] error 1064`. This breaks upgrade paths on MySQL silently passing on MariaDB.

**Wrong (MariaDB-only):**
```sql
ALTER TABLE my_table DROP COLUMN IF EXISTS old_col;
```

**Correct (MySQL 8.0+ and MariaDB 10.2+):**
```sql
-- When version-gating GUARANTEES the column exists: just plain DROP COLUMN
ALTER TABLE my_table DROP COLUMN old_col;

-- When the column might not exist (use information_schema guard):
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

Also note: `DROP TABLE IF EXISTS` and `DROP INDEX IF EXISTS` work fine on MySQL. Only `DROP COLUMN IF EXISTS` in ALTER TABLE is MariaDB-only.

Similarly, `@var:=expr` assignments inside DML (UPDATE SET, SELECT) are deprecated since MySQL 8.0.22 and may cause warnings; prefer `ROW_NUMBER() OVER (ORDER BY ...)` for row numbering in migrations.

### Use utf8mb4 for All User-Content Tables <!-- learned: 2026-04-29 -->

MySQL `utf8` / `utf8mb3` is 3-byte max and silently fails on emoji and other 4-byte Unicode (`SQLSTATE[22007] Incorrect string value`). Any table that stores user-generated text must use `utf8mb4_unicode_ci`.

```sql
-- ✅ New tables
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ✅ Upgrading existing tables
ALTER TABLE `note_nte` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### The Schema Has No Foreign Keys Yet — New Tables Are the First <!-- learned: 2026-09-12 -->

`SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()` returns **0** on a stock install: `orm/schema.xml` declares `<foreign-key>` elements, but `Install.sql` and `cypress/data/seed.sql` contain no `FOREIGN KEY` clause at all, so nothing is enforced at the database level. Consequences when you add a table that *does* declare them (the `volunteer_*` tables in #9705 are the first):

- The constraints are real, so `ON DELETE CASCADE` / `SET NULL` / `RESTRICT` actually fire, and an orphan insert is rejected with `ER_NO_REFERENCED_ROW_2`. That is worth having, but it is new behaviour for this codebase — do not assume existing code is prepared for a `RESTRICT`.
- `<foreign-key onDelete="cascade|setnull|restrict">` is supported by Propel and was previously unused anywhere in `schema.xml`.
- **The clauses must be written into `Install.sql` and `seed.sql` by hand**, exactly as in the upgrade script. Mirroring only the columns leaves a fresh install and the Cypress database without the constraints the upgrade path has, and no tooling compares the three files.
- MariaDB needs an index on every foreign-key column; declare one explicitly (or make the column the leftmost part of a composite key) or the server invents an auto-named one that breaks an `_idx` / `_uidx` naming check.
- Order matters inside one script: create the parent table before anything references it.

### `npm run build:orm` Works Again <!-- learned: 2026-09-12 -->

`package.json` now runs `cd src/ && composer run orm-gen`; the old broken `./vendor/bin/propel build --config-dir=propel` invocation is gone (#9722). Copy the config first — `orm/propel.php` is gitignored and absent from a fresh checkout:

```bash
cp orm/propel.php.dist orm/propel.php
npm run build:orm
npm run build:php:validate:orm
```

`src/ChurchCRM/model/ChurchCRM/Base/` and `Map/` are gitignored, so nothing from the regeneration is committed — only the hand-written subclasses. Note that `scripts/validate-orm-base-classes.js` passes silently when `Base/` is absent, so run `composer install` before trusting it.
