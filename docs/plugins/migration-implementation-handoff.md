# Community plugin migrations: implementation handoff

Review status: development proposal, not upstream-approved or released. This
handoff supersedes the initial local Task 02A report and incorporates Task 02B
failure, lifecycle and recovery validation. No receipt plugin is implemented.

## Revision and publication

- Branch: `feature/community-plugin-migrations`.
- Exact upstream base: `caacc36a865a454f4f5b935daa940f500927f72a`.
- Publication target: [mitk5/CRM](https://github.com/mitk5/CRM), branch
  `feature/community-plugin-migrations`. GitHub API requests using the local Git
  Credential Manager credential verified account `mitk5`, fork parent
  `ChurchCRM/CRM`, and push/admin access. Fork Actions is enabled. Repository-local
  author identity is `mitk5 <326325718+mitk5@users.noreply.github.com>`; `origin`
  targets the fork and `upstream` preserves ChurchCRM/CRM.
- Published implementation: [`ddcfa3965388e8f0c21c04669275713fdf463fb1`](https://github.com/mitk5/CRM/commit/ddcfa3965388e8f0c21c04669275713fdf463fb1),
  containing the reviewed 53-file change. The Linux evidence below tests that exact
  commit. This handoff's evidence update is a separate documentation-only commit.
- [Published branch](https://github.com/mitk5/CRM/tree/feature/community-plugin-migrations)
  and [implementation diff](https://github.com/mitk5/CRM/compare/caacc36a865a454f4f5b935daa940f500927f72a...ddcfa3965388e8f0c21c04669275713fdf463fb1).
- No upstream issue, PR, merge, release or deployment has been performed.
- [Linux CI run 34299382732](https://github.com/mitk5/CRM/actions/runs/34299382732)
  completed successfully on the fork's feature-branch push. Both database jobs
  and both dependency modes passed, including the mandatory symlink subcase.

## Architecture and contract

Core executes reviewed forward SQL during explicit Admin enable, before loading
plugin PHP. The normal core schema path creates the durable ledger. Propel/Perpl
models handle ledger and application data access; controlled SQL is limited to
schema execution and connection-scoped locking. There is no migration scripting,
down migration, package-manager redesign or production plugin model generation.

The exact declaration added to normal `plugin.json` is:

```json
{
  "permissions": ["db.migrate", "db.read", "db.write"],
  "migrations": "migrations/migrations.json"
}
```

The approved registry entry must match the plugin ID/version and include
`db.migrate` with `risk: "high"`. Archive provenance pins the ordered resource
fingerprint. Unverified migration installs are rejected. A new manifest cannot
silently inherit an older release's approval for new migration resources.

The migration JSON is exactly an object with an ordered `migrations` array:

```json
{"migrations":[
  {"id":"0001_create_entries","file":"migrations/0001_create_entries.sql"},
  {"id":"0002_add_note","file":"migrations/0002_add_note.sql"}
]}
```

Paths are plugin-root-relative; ID order is strictly increasing ASCII. Every SQL
file contains one owned-table CREATE or ALTER statement; CREATE requires InnoDB.
The prefix for `migration-example` is `plugin_migration_example__`. Migrating IDs
are canonical kebab-case, maximum 26 characters; repeated/trailing hyphens are
rejected to prevent prefix overlap. Traversal, symlinks, external descriptors,
multiple statements and unsupported operations fail preflight. SQL bytes are
buffered once, validated, SHA-256 hashed and executed from that same buffer.

Ledger `plugin_migration_pmg` has composite primary key
`(pmg_PluginId, pmg_MigrationId)`, ASCII-binary varchar(40)/varchar(95) IDs,
ASCII-binary char(64) `pmg_Checksum`, required UTC datetime `pmg_StartedAt` and
nullable UTC datetime `pmg_AppliedAt`. It has no config foreign key or MAX()+1.

Full author/model contract: [database-migrations.md](database-migrations.md).
Operational recovery contract: [migration-recovery.md](migration-recovery.md).

## Lifecycle and failure evidence

Installation validates approved resources and leaves the plugin disabled. Admin
enable runs pending migrations; ordinary restart validates readiness without
DDL. Upgrades append immutable resources. Disable preserves data/history.
Uninstall disables first, detaches captured hooks and loaded state, deletes
package/config, and retains data/history. It skips legacy deactivate/uninstall
callbacks when the package declares migrations **or** durable history exists.
Reinstallation must establish approval/provenance again and reuse that history.

In [PluginMigrationManager.php](../../src/ChurchCRM/Plugin/PluginMigrationManager.php):

- Lines 67–75 acquire/release the per-database/per-plugin connection lock.
- Line 94 commits the attempt **before** line 97 executes DDL.
- Line 99 records successful completion.
- Line 189 rejects unresolved attempts after reconnect.

No row means never started. StartedAt plus NULL AppliedAt means uncertain,
whether interrupted before DDL, during DDL, or after DDL before completion.
Both timestamps mean success. The unresolved phases are intentionally not
distinguishable from the ledger alone. This is not crash-atomic exactly-once DDL.

[FaultConnection.php](../../tests/plugin-migrations/FaultConnection.php) supplies
test-only pipe barriers; [failure-recovery.php](../../tests/plugin-migrations/failure-recovery.php)
terminates real workers, checks durable state from another connection and
restarts. Other requests cannot enable/disable/uninstall at those barriers,
including after the DDL implicit commit. Other plugin/database lock names remain
independent. Process death releases the lock but never clears uncertain history.
Between resources, only the never-started resource resumes; completed SQL is not
replayed. The production service has no test hook or automatic repair switch.

Full backup recovery uses the normal locked Mysqldump dependency and core empty
schema/import helpers. It restores a recognizable plugin entry, unrelated core
configuration, ledger/provenance and matching package metadata; a post-backup
column and table disappear. A subsequent approved upgrade succeeds. Newer core
and plugin writes deliberately disappear too, proving the cost of restoring
without quiescing all writers. See the recovery guide for the consistent unit.

## Executed validation

Commands run from the repository root with a guarded disposable database DSN.
No real church records, donor data, production credentials or outbound services
are used. Temporary packages and database dumps are not part of the source diff.

| Environment/check | Result | Skips / limitations |
|---|---|---|
| Windows, PHP 8.4.25, MariaDB 11.4.5; `php tests/plugin-migrations/run.php` | 40 passed, 0 failed; exit 0 | One filesystem symlink subcase unavailable on Windows |
| Same engine, locked production dependencies, no PHPStan/Rector or generator commands; same suite | 40 passed, 0 failed; exit 0 | Same Windows symlink subcase |
| `npm run lint` | 74 files passed; exit 0 | Existing Windows TypeScript CRLF normalized for check and restored; no TS changes |
| `npm run build:php` | Passed; exit 0 | Normal core model generation; unrelated generated skeletons excluded |
| `php -l` on changed/new PHP | 29 files passed; exit 0 | Includes test/fixture code outside production syntax scanner |
| PHPStan level 6, three migration infrastructure classes | No errors; exit 0 | Targeted analysis |
| Plugin security scanner | 0 errors, 2 reviewed warnings; exit 0 | Generated Perpl prepared statements; documentation hostname is not an outbound call |
| Workflow YAML, both ORM XML schemas, migration JSON; `git diff --check` | Passed | Static format checks |
| Linux, PHP 8.4.25, MySQL 8.0.46; development dependencies | 40 passed, 0 failed; exit 0 | No skips; symlink subcase executed |
| Linux, PHP 8.4.25, MySQL 8.0.46; production dependencies, generator commands absent | 40 passed, 0 failed; exit 0 | No skips; symlink subcase executed |
| Linux, PHP 8.4.25, MariaDB 10.11.19-MariaDB-ubu2204; development dependencies | 40 passed, 0 failed; exit 0 | No skips; symlink subcase executed |
| Linux, PHP 8.4.25, MariaDB 10.11.19-MariaDB-ubu2204; production dependencies, generator commands absent | 40 passed, 0 failed; exit 0 | No skips; symlink subcase executed |
| Full Cypress browser suite | **Not run** | Targeted real Slim route/view tests were used |

Hosted job logs: [MySQL 8.0](https://github.com/mitk5/CRM/actions/runs/34299382732/job/102302860281)
and [MariaDB 10.11](https://github.com/mitk5/CRM/actions/runs/34299382732/job/102302860108).
Both recorded Linux kernel `6.17.0-1022-azure`, the implementation SHA and exact
PHP/database versions above. Both also passed core Composer install/model build,
the security scanner (0 errors, 2 reviewed generated-code warnings), and targeted
PHPStan level 6. Every validation step completed successfully; none was skipped.

Each job runs `php tests/plugin-migrations/run.php`, then installs locked
production dependencies with `composer install --no-dev --no-scripts --no-plugins
--no-interaction --prefer-dist`, removes generator commands from the runtime and
runs the same suite with `PLUGIN_MIGRATION_TEST_PRODUCTION=1`. Logs include passing
process-death boundaries, cross-DDL lock exclusion, uninstall retention/cleanup,
shipped model reads/writes, and coordinated full backup restore of plugin and
unrelated core records in both modes. There is no Linux symlink-unavailable note;
the harness fails on Linux if that subcase cannot execute.

The inspected fork Actions setting was enabled; the migration workflow was active.
Its actual trigger was `push`. Branch filters include this feature branch and
master; pull-request path filters and workflow_dispatch also remain available.
No job condition suppresses fork validation. Workflow permissions are read-only,
checkout credentials are not persisted, and services use only disposable test
credentials. MariaDB 10.11 matches the baseline Docker configuration; MySQL 8.0
supplies a fixed MySQL family target. No upstream PR was needed to run validation.

## Findings, fixes and remaining review decisions

| Priority | Finding | Disposition |
|---|---|---|
| P1 | Repeated/trailing hyphens allowed overlapping owned-table prefixes | Reproduced; canonical migrating plugin IDs enforced and regression tested |
| P1 | A community descriptor could replace a discovered core ID | Reproduced; core identity preserved and regression tested |
| P2 | Skipping destructive callbacks left same-process cron hooks and loaded instances alive | Reproduced; core captures/removes lifecycle action/filter registrations; disables before removal |
| P2 | Failed re-enable approval left an existing loaded instance operational | Reproduced; failure detaches instance/hooks and marks readiness error |
| P2 | Linux/MySQL/MariaDB CI evidence was missing | Resolved: both actual Linux jobs passed in both dependency modes, including symlinks |
| P2 | Non-atomic DDL needs conservative full recovery and writer quiescence | Local and both Linux engine process/restore tests pass; recovery policy remains an unresolved maintainer decision |
| P2 | Already-versioned development databases do not rerun the current core upgrade block | Maintainers must select the actual shipping upgrade slot; do not assume a future release |
| P2 | External workers and already-running requests outlive core-owned callback cleanup | External-worker cleanup and maintenance draining remain unresolved maintainer decisions |
| P3 | Removing all Perpl Generator classes breaks runtime queries | Invalid test packaging assumption corrected: retain required Model/PropelTypes, remove commands only |

Core-managed cleanup cannot revoke an already-built route collector, already
running request, external job or arbitrary trusted PHP side effect. It captures
registrations made during boot/activate/routes; later registrations outside that
scope remain the plugin author's responsibility. Drain/restart workers during
maintenance. Core plugins and legacy community plugins keep prior callback
behavior; the suite exercises that compatibility path.

Package authors ship generated namespaced Base/Map/collection/model/query files
and register maps with `initDatabaseMaps()` in boot. Existing plugin autoloading
is sufficient. Production sites do not generate plugin models or ship a second
ORM runtime. Keep the locked Perpl package's shared runtime types intact.

Maintainer agreement is still needed on: high-risk reviewed SQL; durable-data
uninstall semantics and external cleanup; map registration/model distribution;
forward-only failure recovery; release/upgrade slot; and current approved-registry
availability/version requirements at boot. Permission validation now rejects
unknown plugin capability names, a deliberate compatibility restriction.

Recommendation: **ready for upstream design and code review**, with the shipping
upgrade slot, conservative recovery policy and external-worker cleanup explicitly
unresolved. This is not a release or deployment recommendation. A subsequent
feature PR also needs the repository-required linked user documentation issue;
neither that issue nor a PR is authorized in this task.

## Scoped file inventory

### Documentation (12)

- [.agents/skills/churchcrm/database-operations.md](../../.agents/skills/churchcrm/database-operations.md)
- [.agents/skills/churchcrm/db-schema-migration.md](../../.agents/skills/churchcrm/db-schema-migration.md)
- [.agents/skills/churchcrm/plugin-compliance.md](../../.agents/skills/churchcrm/plugin-compliance.md)
- [.agents/skills/churchcrm/plugin-create.md](../../.agents/skills/churchcrm/plugin-create.md)
- [.agents/skills/churchcrm/plugin-development.md](../../.agents/skills/churchcrm/plugin-development.md)
- [.agents/skills/churchcrm/plugin-security-scan.md](../../.agents/skills/churchcrm/plugin-security-scan.md)
- [.agents/skills/churchcrm/plugin-system.md](../../.agents/skills/churchcrm/plugin-system.md)
- [.agents/skills/churchcrm/SKILL.md](../../.agents/skills/churchcrm/SKILL.md)
- [.agents/skills/churchcrm/testing.md](../../.agents/skills/churchcrm/testing.md)
- [docs/plugins/database-migrations.md](../../docs/plugins/database-migrations.md)
- [docs/plugins/migration-implementation-handoff.md](../../docs/plugins/migration-implementation-handoff.md)
- [docs/plugins/migration-recovery.md](../../docs/plugins/migration-recovery.md)

### Fixture source and manifests (8)

- [tests/fixtures/plugins/community/migration-example/migrations/0001_create_entries.sql](../../tests/fixtures/plugins/community/migration-example/migrations/0001_create_entries.sql)
- [tests/fixtures/plugins/community/migration-example/migrations/0002_add_note.sql](../../tests/fixtures/plugins/community/migration-example/migrations/0002_add_note.sql)
- [tests/fixtures/plugins/community/migration-example/migrations/migrations.json](../../tests/fixtures/plugins/community/migration-example/migrations/migrations.json)
- [tests/fixtures/plugins/community/migration-example/orm/propel.php](../../tests/fixtures/plugins/community/migration-example/orm/propel.php)
- [tests/fixtures/plugins/community/migration-example/orm/schema.xml](../../tests/fixtures/plugins/community/migration-example/orm/schema.xml)
- [tests/fixtures/plugins/community/migration-example/plugin.json](../../tests/fixtures/plugins/community/migration-example/plugin.json)
- [tests/fixtures/plugins/community/migration-example/routes/routes.php](../../tests/fixtures/plugins/community/migration-example/routes/routes.php)
- [tests/fixtures/plugins/community/migration-example/src/MigrationExamplePlugin.php](../../tests/fixtures/plugins/community/migration-example/src/MigrationExamplePlugin.php)

### Generated ORM (8)

- [src/ChurchCRM/model/ChurchCRM/PluginMigration.php](../../src/ChurchCRM/model/ChurchCRM/PluginMigration.php)
- [src/ChurchCRM/model/ChurchCRM/PluginMigrationQuery.php](../../src/ChurchCRM/model/ChurchCRM/PluginMigrationQuery.php)
- [tests/fixtures/plugins/community/migration-example/src/Model/Base/Collection/EntryCollection.php](../../tests/fixtures/plugins/community/migration-example/src/Model/Base/Collection/EntryCollection.php)
- [tests/fixtures/plugins/community/migration-example/src/Model/Base/Entry.php](../../tests/fixtures/plugins/community/migration-example/src/Model/Base/Entry.php)
- [tests/fixtures/plugins/community/migration-example/src/Model/Base/EntryQuery.php](../../tests/fixtures/plugins/community/migration-example/src/Model/Base/EntryQuery.php)
- [tests/fixtures/plugins/community/migration-example/src/Model/Entry.php](../../tests/fixtures/plugins/community/migration-example/src/Model/Entry.php)
- [tests/fixtures/plugins/community/migration-example/src/Model/EntryQuery.php](../../tests/fixtures/plugins/community/migration-example/src/Model/EntryQuery.php)
- [tests/fixtures/plugins/community/migration-example/src/Model/Map/EntryTableMap.php](../../tests/fixtures/plugins/community/migration-example/src/Model/Map/EntryTableMap.php)

### Handwritten production and core schema (18)

- [cypress/data/seed.sql](../../cypress/data/seed.sql)
- [orm/schema.xml](../../orm/schema.xml)
- [scripts/plugin-scan.php](../../scripts/plugin-scan.php)
- [src/ChurchCRM/Plugin/ApprovedPluginRegistry.php](../../src/ChurchCRM/Plugin/ApprovedPluginRegistry.php)
- [src/ChurchCRM/Plugin/Hook/HookManager.php](../../src/ChurchCRM/Plugin/Hook/HookManager.php)
- [src/ChurchCRM/Plugin/PluginInstaller.php](../../src/ChurchCRM/Plugin/PluginInstaller.php)
- [src/ChurchCRM/Plugin/PluginInterface.php](../../src/ChurchCRM/Plugin/PluginInterface.php)
- [src/ChurchCRM/Plugin/PluginManager.php](../../src/ChurchCRM/Plugin/PluginManager.php)
- [src/ChurchCRM/Plugin/PluginMetadata.php](../../src/ChurchCRM/Plugin/PluginMetadata.php)
- [src/ChurchCRM/Plugin/PluginMigrationException.php](../../src/ChurchCRM/Plugin/PluginMigrationException.php)
- [src/ChurchCRM/Plugin/PluginMigrationManager.php](../../src/ChurchCRM/Plugin/PluginMigrationManager.php)
- [src/ChurchCRM/Plugin/PluginMigrationManifest.php](../../src/ChurchCRM/Plugin/PluginMigrationManifest.php)
- [src/Include/LoadDatabaseMap.php](../../src/Include/LoadDatabaseMap.php)
- [src/mysql/install/Install.sql](../../src/mysql/install/Install.sql)
- [src/mysql/upgrade.json](../../src/mysql/upgrade.json)
- [src/mysql/upgrade/7.7.0-plugin-migrations.sql](../../src/mysql/upgrade/7.7.0-plugin-migrations.sql)
- [src/plugins/routes/api/management.php](../../src/plugins/routes/api/management.php)
- [src/plugins/views/management.php](../../src/plugins/views/management.php)

### Test harness (6)

- [tests/plugin-migrations/bootstrap.php](../../tests/plugin-migrations/bootstrap.php)
- [tests/plugin-migrations/failure-recovery.php](../../tests/plugin-migrations/failure-recovery.php)
- [tests/plugin-migrations/FaultConnection.php](../../tests/plugin-migrations/FaultConnection.php)
- [tests/plugin-migrations/README.md](../../tests/plugin-migrations/README.md)
- [tests/plugin-migrations/run.php](../../tests/plugin-migrations/run.php)
- [tests/plugin-migrations/worker.php](../../tests/plugin-migrations/worker.php)

### Workflow (1)

- [.github/workflows/plugin-migrations.yml](../../.github/workflows/plugin-migrations.yml)
