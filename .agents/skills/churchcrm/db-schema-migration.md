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
1. Write the upgrade script and update `orm/schema.xml`, `Install.sql`, and (after asking) `seed.sql`
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
| `cypress/data/seed.sql` | Same change; ask the user first (it holds Cypress test data) |
| `orm/schema.xml` | Column/table attributes |

### Removing a table

Delete its `CREATE TABLE` from `Install.sql`, its `DROP/CREATE` plus data from `seed.sql`, its `<table>` from `schema.xml`, and add `DROP TABLE IF EXISTS` to the upgrade script (idempotent on fresh installs).

### Adding a column to seed.sql

`seed.sql` is a `mysqldump`: its `INSERT` statements are positional (no column list). A new column in `CREATE TABLE` needs a value at the matching ordinal in every tuple of every `INSERT` for that table, or the load fails with `Column count doesn't match value count`. Do it mechanically: split tuples on top-level commas outside single-quoted strings, insert the default, then verify the `CREATE TABLE` column count equals the value count in every tuple. `Install.sql` inserts for `user_usr` name their columns, so a new column with a `DEFAULT` needs no edit there.

Match each file's local column types (`seed.sql` uses `tinyint(3)` for permission booleans where `Install.sql` uses `tinyint(1)`); nothing validates the files against each other.

### Adding a table

After adding a `<table>` to `orm/schema.xml`, generate the model and commit only the two skeleton subclasses (`<PhpName>.php`, `<PhpName>Query.php`); `Base/` and `Map/` are gitignored and CI regenerates them:

```bash
cp -n orm/propel.php.dist orm/propel.php
cd src && composer run orm-gen
```

Add the table to `cypress/data/seed.sql` too (with seeded rows if specs read it); the test DB loads only from that file. Reference: `email_log_eml`.

### Regenerating the ORM

```bash
cp orm/propel.php.dist orm/propel.php     # orm/propel.php is gitignored
npm run build:orm
npm run build:php:validate:orm
```

`scripts/validate-orm-base-classes.js` passes silently when `Base/` is absent, so run `composer install` first.

## MySQL-Compatible SQL

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

`<foreign-key onDelete="cascade|setnull|restrict">` in `schema.xml` declares the relation for Propel. `Install.sql` and `seed.sql` enforce a constraint only where a `FOREIGN KEY` clause is written there by hand, exactly as in the upgrade script; no tooling compares the three files.

- Constraints that exist fire: `ON DELETE CASCADE` / `SET NULL` / `RESTRICT` act, and an orphan insert fails with `ER_NO_REFERENCED_ROW_2`
- MariaDB needs an index on every FK column; declare it explicitly (or lead a composite key with the column) so the server does not create an auto-named one that breaks an `_idx` / `_uidx` naming check
- Create the parent table before anything references it within one script
- `schema.xml` may reference a table declared later in the file; raw SQL may not. For an FK from an existing table to a new one, put the column and its `KEY` in the existing `CREATE TABLE` and the constraint in a trailing `ALTER TABLE` after the parent is created, in both `Install.sql` and `seed.sql`:

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
