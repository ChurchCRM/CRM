# Community plugin database migrations

This development branch proposes forward-only schema migrations for approved
community plugins. No upstream release containing this API is assigned. Pin the
reviewed feature commit and require its migration capability during development;
stock ChurchCRM 7.6.4 does not provide it. Core validates and executes declared SQL before loading plugin PHP.
Ordinary request handlers, `boot()`, and `activate()` must not execute schema SQL.
All application data access, including plugin-owned tables, uses Propel/Perpl.

This API deliberately uses the same controlled SQL approach as core upgrades.
There is no PHP migration script, down migration, production model generator,
or plugin package manager. Community SQL still requires security review.

## Declare a migration

Add these fields to the normal `plugin.json`:

```json
{
  "id": "migration-example",
  "type": "community",
  "version": "1.0.0",
  "permissions": ["db.migrate", "db.read", "db.write"],
  "migrations": "migrations/migrations.json"
}
```

The excerpt omits the usual name, mainClass and minimumCRMVersion fields. Set the
minimum version to the release that actually ships this API once known. The
fixture's 7.7.0 matches this checkout's development version, not a release promise. The approved
registry entry must match the ID/version and declare `permissions: ["db.migrate",
...]`, `risk: "high"`, and a riskSummary explaining schema modification and data
retention. `db.write` alone does not authorize migrations. High risk requires two
maintainer reviews under the existing review process.

`migrations/migrations.json`:

```json
{
  "migrations": [
    {"id": "0001_create_entries", "file": "migrations/0001_create_entries.sql"},
    {"id": "0002_add_note", "file": "migrations/0002_add_note.sql"}
  ]
}
```

Both the manifest path and SQL paths are relative to the **plugin root**. IDs
must match `[0-9]{4,14}_[a-z0-9_]{1,80}`, be unique, and appear in strictly
increasing ASCII order. Use a fixed-width numeric prefix. Each resource is used
once; append new IDs at the end. The limits are 100 migrations, 2 MiB per file,
8 MiB total SQL, and 50,000 tokens per statement.
Only the keys shown above are supported in the migration manifest.

Paths must use forward slashes and plain ASCII filename characters. Absolute
paths, traversal, hidden path segments, stream wrappers, and symlinks are rejected.
The execution service also binds the descriptor to the discovered installed
plugin directory, refusing descriptors pointing to a different package. Core
reads the files into memory, validates them, and hashes their exact bytes
with SHA-256; those same buffered bytes are executed.

`0001_create_entries.sql`:

```sql
CREATE TABLE plugin_migration_example__entries (
  id INT NOT NULL AUTO_INCREMENT,
  label VARCHAR(100) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`0002_add_note.sql`:

```sql
ALTER TABLE plugin_migration_example__entries ADD note VARCHAR(100) NULL;
```

Every SQL file contains **one** `CREATE TABLE` or `ALTER TABLE` statement. Plugin
IDs use canonical kebab-case (`[a-z0-9]+(?:-[a-z0-9]+)*`), with no repeated or
trailing hyphens, and at most 26 characters (leaving room
for core lifecycle keys in the existing 50-character configuration key column). Owned table
names begin with `plugin_`, the ID with hyphens replaced by underscores, then
`__`; the suffix contains lowercase letters, digits or underscores. MySQL's
64-character table-name limit applies. Foreign keys can reference only tables
with that same prefix; use Propel queries/joins to relate to core records.

CREATE must explicitly specify `ENGINE=InnoDB`. Do not change to another engine.
Database-qualified names, table renames, CREATE LIKE/SELECT, partition operations,
file/tablespace options, executable comments, stored scripts and multiple
statements are unsupported. Single-quoted literals use doubled quotes (`'it''s'`)
without backslash escapes; double-quoted literals are unsupported so parsing is
independent of ANSI_QUOTES and NO_BACKSLASH_ESCAPES. Plain comments and an optional
final semicolon are accepted. ALTER may remove columns, so review data-loss risk
as carefully as a core upgrade. Do not use MariaDB-only syntax in a package that
claims MySQL compatibility.

## Lifecycle and immutable history

| Operation | Behavior |
|---|---|
| Install | The approved URL installer verifies the archive SHA-256 and validates migration resources without executing plugin PHP or DDL. The plugin remains disabled. |
| Enable | An explicit Admin enable validates approval, installed resource fingerprint, full history and checksums, runs pending migrations in order, then loads/boots and activates the plugin. Browser requests require a CSRF token; authenticated Admin API keys remain supported. |
| Ordinary requests/restart | Validate schema readiness and approval before loading the plugin. Never execute pending DDL; a pending, drifted or failed plugin remains unavailable with an error in plugin management. |
| Disable | Existing deactivate behavior; core leaves tables and history intact. Plugin deactivate code must preserve application data. |
| Upgrade | Use the existing uninstall/reinstall flow for an approved newer release, then enable. The unchanged historical prefix is recognized; only appended migrations execute. A replacement already marked enabled is withheld from boot until Admin enable applies pending migrations. |
| Uninstall | Delete package files and `plugin.{id}.*` settings, retaining application tables and the core ledger. For plugins declaring migrations **or** with any history, skip both legacy deactivate and uninstall callbacks during removal, so they cannot destroy persistent data. |

Enable/migration, disable and uninstall share a per-database/per-plugin MySQL named lock.
It survives DDL implicit commits and excludes concurrent lifecycle requests.
Uninstall hooks for older plugins without migrations/history keep their existing
behavior. Migration plugins needing external cleanup must provide a separate,
explicit action before uninstall; never use removal as a database purge.

For migrating plugins, core captures action/filter registrations made during
boot, activation and route registration. Disable, quarantine, failed enable,
reset and uninstall detach those callbacks without requiring an uninstall hook.
Uninstall persists disabled state before deleting files and removes the loaded
instance. Already-built Slim route collectors and already-running requests are
not revoked across processes; stop and drain them for maintenance. Fresh requests
will not load removed, pending or failed plugins. There is no generic external
job scheduler or service-container teardown contract: plugin authors must provide
explicit cleanup for external schedules/resources and guard long-lived work with
fresh enabled/readiness checks. Callbacks registered later outside captured
lifecycle calls are not automatically owned. Reviewed plugin PHP is trusted;
this cleanup is not universal protection against arbitrary plugin code.

Never edit, rename, remove or reorder attempted/applied migrations, even whitespace
or line endings. Keep the entire history in subsequent releases, including after
uninstall/reinstall. An already-applied checksum change or downgrade fails closed.
The installer also pins an ordered resource fingerprint in ordinary installation
provenance so editing a **pending** migration on disk cannot authorize new SQL.
Provenance is removable config; it is not the durable migration ledger.

## Core ledger and failure semantics

`plugin_migration_pmg` is created through `src/mysql/upgrade.json` and
`src/mysql/upgrade/7.7.0-plugin-migrations.sql`, and is included in fresh-install
SQL, the test seed, the ORM schema, and the core Perpl map loader.

| Column | Type | Meaning |
|---|---|---|
| `pmg_PluginId` | ASCII binary varchar(40) | Stable plugin identity |
| `pmg_MigrationId` | ASCII binary varchar(95) | Ordered migration ID |
| `pmg_Checksum` | ASCII binary char(64) | SHA-256 of SQL bytes |
| `pmg_StartedAt` | datetime, required | UTC durable attempt timestamp |
| `pmg_AppliedAt` | datetime, nullable | UTC success timestamp; NULL is unresolved |

The composite primary key is `(pmg_PluginId, pmg_MigrationId)`. There is no foreign
key to plugin configuration and no auto-increment/version allocation. Core uses
generated Propel models for all ledger reads/writes; only locking and DDL use
controlled infrastructure SQL.

Successful migrations execute once. Failed or interrupted migrations are never
marked applied and never automatically retried. MySQL/MariaDB DDL implicitly
commits: even MySQL's atomic DDL is not a transaction that includes ledger writes
([MySQL documentation](https://dev.mysql.com/doc/refman/8.0/en/atomic-ddl.html)).
Consequently the runner commits a durable attempt **before** SQL, then records
completion. A crash before SQL and a crash after SQL can both leave an unresolved
attempt. This API provides at-most-one automatic attempt and refuses ambiguous
replay; it cannot promise an atomic exactly-once DDL-plus-ledger transaction.

Follow the [coordinated recovery procedure](migration-recovery.md) before an
upgrade. Validation failures that create no new attempt need correction of the
package, approval or core upgrade; they do not themselves require a database
restore. An unresolved durable attempt blocks automatic replay and requires
maintainer assistance. This implementation proposes coordinated backup restore
as its conservative recovery policy; other reconciliation designs would need
separate review. Do not clear ledger rows, mark them applied manually, or edit
migration bytes to force a retry. No automatic rollback or repair API is provided.
Running migrations inside an application transaction is rejected without
committing the caller's work.

## Ship generated Propel/Perpl models

Generate models during plugin development using the Perpl version from the
target ChurchCRM release (`src/composer.lock`). Use datasource `default`, a
namespace beneath the main plugin class namespace, and plugin-local output:

```xml
<database name="default" namespace="ChurchCRM\Plugins\MigrationExample\Model"
          package="Model" defaultIdMethod="native">
  <table name="plugin_migration_example__entries" phpName="Entry">
    <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true" required="true"/>
    <column name="label" type="VARCHAR" size="100" required="true"/>
  </table>
</database>
```

The [fixture build configuration](../../tests/fixtures/plugins/community/migration-example/orm/propel.php)
sets `generator.namespaceAutoPackage` to false, `paths.schemaDir` to its `orm`
directory and `paths.phpDir` to its `src` directory. From a ChurchCRM development
checkout, after installing its Composer dependencies:

```sh
php src/vendor/bin/propel --config-dir=tests/fixtures/plugins/community/migration-example/orm model:build
```

For a real plugin, substitute its own `orm` path. Distribute generated
`src/Model/Base`, `src/Model/Map`, model and query classes with the release ZIP.
Include all relations' generated classes. Do not ship another Propel runtime or
replace ChurchCRM's core model/map files. Production installers do not run
`npm run orm-gen`, Composer, or a model generator.

Keep the application's locked Perpl runtime intact: Perpl 2.6 runtime code
imports `Generator/Model/PropelTypes.php`. The test removes generator commands
to prove they are unnecessary; removing the entire Generator directory breaks
ordinary core ORM queries too. Do not recommend pruning shared vendor classes.

The existing plugin namespace autoloader already resolves these classes. Current
Perpl additionally requires registering the plugin's generated table maps before
queries, normally at the start of `boot()`:

```php
use ChurchCRM\Plugins\MigrationExample\Model\Map\EntryTableMap;
use Propel\Runtime\Propel;

Propel::getServiceContainer()->initDatabaseMaps(['default' => [EntryTableMap::class]]);
```

This registers ORM metadata; it executes no DDL. Then use `EntryQuery::create()`
and model `save()` normally. Do not load a generated database configuration that
overrides core connection settings. Match the schema XML to the cumulative SQL
schema of each release and test generated models against both supported database
families. No generic autoloader extension is necessary.

## Security review and testing

`db.migrate` is a distinct, high-risk capability in both manifests and the
approved registry; unverified/manual migration installs are rejected. The
existing compliance UI displays the tag and risk summary, and the migration
notice explains backups and uninstall retention. Installation requires the
current matching approved release; registry revocation, unavailable registry
data or version drift also withhold migration plugins from boot until resolved.

Run `php scripts/plugin-scan.php <plugin-dir>`. It uses the core read-only
manifest/path/SQL validator, reports migration checksums and the capability, and
flags SQL shipped without declarations. Review every SQL resource and generated
model diff, including forward destructive changes. The scanner flags direct SQL
sinks in PHP for investigation; it never executes plugin code. Plugin request
handlers must continue using Propel. This is a reviewed-code system, **not a
sandbox**: approved PHP shares the application's database privileges.

See [integration test instructions](../../tests/plugin-migrations/README.md).
The fixture is test-only and deliberately includes a destructive uninstall
callback to prove core does not invoke it. Do not publish that fixture as a plugin.
