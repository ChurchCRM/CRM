---
title: "DB Schema Migration"
intent: "Safe procedures for database schema changes and versioning"
tags: ["database","migration","schema","backup"]
prereqs: ["[[database-operations]]","[[development-workflows]]"]
complexity: "advanced"
---

# Database Schema Migration

Schema source: `orm/schema.xml` (Perpl ORM). Upgrade scripts: `src/mysql/upgrade/`, registered in `src/mysql/upgrade.json`.

Process:
1. Write the upgrade script and update `orm/schema.xml` and `Install.sql`. `seed.sql` is test data only and holds no schema
2. Run the migration on a staging copy and smoke-test the app
3. Back up the production DB before applying; keep a rollback script and a snapshot
4. Apply in a maintenance window and watch for errors

Backward compatibility: add nullable columns or new tables first, populate them (background job if large), then tighten to non-null in a later step.

## Registering a Script in upgrade.json

`UpgradeService` reads `VersionUtils::getDBVersion()` fresh on every iteration. An install walks the blocks in order: each block whose `versions` list contains the current DB version runs its `scripts` and sets the DB version to its `dbVersion`.

`current` is the version in development (`package.json` `version` and the `current` block's `dbVersion` are the reference). Add every script for that release to its `scripts` array, with the file named for the `dbVersion`:

```json
"current": {
  "versions": ["7.5.1"],
  "scripts": [
    "/mysql/upgrade/7.6.0-remove-orphaned-query-parameters.sql",
    "/mysql/upgrade/7.6.0-remove-custom-search-query.sql"
  ],
  "dbVersion": "7.6.0"
}
```

At release, the shipped `current` becomes `pre-X.Y.Z` (no scripts, `dbVersion` set to the version just before the release) and a new `current` carries the SQL:

```json
"pre-7.3.1": {
  "versions": ["7.2.0", "7.2.1", "7.2.2", "7.2.3"],
  "scripts": [],
  "dbVersion": "7.3.0"
},
"current": {
  "versions": ["7.3.0"],
  "scripts": ["/mysql/upgrade/7.3.1-cleanup.sql"],
  "dbVersion": "7.3.1"
}
```

A 7.2.x install matches `pre-7.3.1` (DB set to 7.3.0), then `current` (runs the SQL, DB set to 7.3.1). An install on 7.3.0 goes straight to `current`. Use full paths: `/mysql/upgrade/7.3.1-<description>.sql`.

## Files to Change Together

| File | Change |
|------|--------|
| `src/mysql/upgrade/X.Y.Z-<desc>.sql` | The `ALTER` / `CREATE` / `DROP` |
| `src/mysql/install/Install.sql` | Same change in the `CREATE TABLE` block, so fresh installs match upgraded ones |
| `cypress/data/seed.sql` | Test data only; the test database gets its schema from `Install.sql`. Touch it only when specs read the new value, or to remove values of a dropped column or table |
| `orm/schema.xml` | Column/table attributes |

### Removing a table

Delete its `CREATE TABLE` from `Install.sql`, its `DELETE FROM` and `INSERT` from `seed.sql`, its `<table>` from `schema.xml`, and add `DROP TABLE IF EXISTS` to the upgrade script (idempotent on fresh installs).

### Removing a column

Drop it from `schema.xml`, from the `CREATE TABLE` in `Install.sql`, and from `Install.sql`'s named `INSERT` (column list and value). The upgrade script runs `ALTER TABLE ... DROP COLUMN` (plain DDL, version-gated).

`seed.sql` names its columns, so a dropped column makes its `INSERT` fail with `Unknown column`. Refresh the data instead of editing tuples: load the old `Install.sql` and `seed.sql` into a scratch database, run the new upgrade script, then dump it:

```bash
mariadb-dump --no-create-info --complete-insert --order-by-primary --skip-comments --skip-add-locks --skip-disable-keys scratch_db
```

Replace the `INSERT` statements in `seed.sql` with that output (keep its header, the `DELETE FROM` lines and the `ALTER`/`CREATE TABLE` lines for custom-field and `groupprop_N` tables).

### Adding a column

`seed.sql` needs nothing: its `INSERT` statements name their columns, so the new column takes its `DEFAULT`. Add a seeded value only when a spec reads it. `npm run lint` (`seed data only`) fails a `CREATE TABLE` for an installed table or an `INSERT` without a column list.

### Adding a table

After adding a `<table>` to `orm/schema.xml`, generate the model and commit only the two skeleton subclasses (`<PhpName>.php`, `<PhpName>Query.php`); `Base/` and `Map/` are gitignored and CI regenerates them:

```bash
cp -n orm/propel.php.dist orm/propel.php
cd src && composer run orm-gen
```

The test database creates the table from `Install.sql`. Add rows to `cypress/data/seed.sql` (named columns) only if specs read them. Reference: `email_log_eml`.

### Regenerating the ORM

```bash
cp orm/propel.php.dist orm/propel.php     # orm/propel.php is gitignored
npm run build:orm
npm run build:php:validate:orm
```

`scripts/validate-orm-base-classes.js` passes silently when `Base/` is absent, so run `composer install` first.

## MySQL-Compatible SQL

Supported databases are the LTS releases in `supported-databases.json`, the one place the versions are written. To change one, edit that file and run `node scripts/sync-supported-databases.js`; `npm run lint` fails when a file is out of date. The docs repo (`requirements.json`) and the website (`data/requirements.json`) mirror the file: update both. CI runs the MariaDB version, and the nightly runs the MySQL version plus `mysql:lts` and `mysql:latest`. Every `.sql` change (upgrade script, `Install.sql`, `seed.sql`) must run on both MariaDB and MySQL. Before you ask to commit, apply it to MariaDB (`docker/docker-compose.yaml`) and to a MySQL 8 container, and run it a second time where it should be idempotent. Syntax that only one server accepts fails on the other; CI finds it only in the nightly matrix.

Column-level `IF EXISTS` / `IF NOT EXISTS` on `ADD COLUMN`, `DROP COLUMN`, `CHANGE COLUMN`, and `MODIFY COLUMN` is MariaDB-only. MySQL rejects it with `SQLSTATE[42000] 1064`, and only the nightly MySQL matrix (`docker/docker-compose.mysql.yaml`) runs against MySQL. `scripts/validate-mysql-upgrade-syntax.js` checks this in CI (`.github/workflows/code-quality.yml`) and in `.githooks/pre-commit`.

Use plain DDL when version gating guarantees the column's state (a script that ADDs a column runs only on installs that lack it):

```sql
ALTER TABLE my_table ADD COLUMN new_col INT;
ALTER TABLE my_table DROP COLUMN old_col;
```

When more than one upgrade path can reach the script, guard with `information_schema`:

```sql
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

Table- and index-level guards work on both: `CREATE TABLE IF NOT EXISTS`, `DROP TABLE IF EXISTS`, `ADD INDEX IF NOT EXISTS`, `DROP INDEX IF EXISTS`.

For row numbering use `ROW_NUMBER() OVER (ORDER BY ...)`; `@var:=expr` inside DML is deprecated since MySQL 8.0.22.

## Charset

Tables storing user text use `utf8mb4_unicode_ci` (3-byte `utf8` fails on emoji with `SQLSTATE[22007]`).

```sql
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `note_nte` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Foreign Keys

`<foreign-key onDelete="cascade|setnull|restrict">` in `schema.xml` declares the relation for Propel. `Install.sql` enforces a constraint only where a `FOREIGN KEY` clause is written there by hand, exactly as in the upgrade script; no tooling compares it with `schema.xml`. The test database is built from `Install.sql`, so it enforces the same constraints.

- Constraints that exist fire: `ON DELETE CASCADE` / `SET NULL` / `RESTRICT` act, and an orphan insert fails with `ER_NO_REFERENCED_ROW_2`
- MariaDB needs an index on every FK column; declare it explicitly (or lead a composite key with the column) so the server does not create an auto-named one that breaks an `_idx` / `_uidx` naming check
- Create the parent table before anything references it within one script
- `schema.xml` may reference a table declared later in the file; raw SQL may not. For an FK from an existing table to a new one, put the column and its `KEY` in the existing `CREATE TABLE` and the constraint in a trailing `ALTER TABLE` after the parent is created, in `Install.sql`:

```sql
-- inside the existing CREATE TABLE
  `event_ministry_id` int(11) DEFAULT NULL,
  KEY `event_ministry_idx` (`event_ministry_id`)

-- at the end of the file, after volunteer_ministry_vmin exists
ALTER TABLE `events_event`
    ADD CONSTRAINT `events_event_FK_ministry` FOREIGN KEY (`event_ministry_id`)
    REFERENCES `volunteer_ministry_vmin` (`vmin_ID`) ON DELETE SET NULL;
```

Verify by loading `Install.sql` into a scratch database and diffing `SHOW CREATE TABLE` against the upgrade path's result.

## A New Column and `fromArray($input)`

Several API write handlers hydrate an entity from the request body (`$Event->fromArray($input)` in `src/api/routes/calendar/events.php`). Propel's `fromArray()` copies every column whose phpName appears in the array, so a migration that adds a column makes it writable by anyone who reaches that endpoint, without validation or authorization.

Before merging a migration on a table an API writes, grep for the entity's `fromArray(` call sites. When the new column carries a rule (a foreign key that must exist, an authorization decision, an enum domain), resolve it before saving and overwrite what `fromArray()` copied:

```php
$ministryId = resolveEventMinistryId($response, $input, $currentMinistryId); // validates + authorizes
if ($ministryId instanceof Response) { return $ministryId; }

$Event->fromArray($input);
$Event->setId($id);
$Event->setMinistryId($ministryId);   // authoritative
```

Resolve before `save()` so a refused value leaves no row behind.
