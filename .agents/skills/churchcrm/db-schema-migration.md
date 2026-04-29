---
title: "DB Schema Migration"
intent: "Safe procedures for database schema changes and versioning"
tags: ["database","migration","schema","backup"]
prereqs: ["database-operations.md","development-workflows.md"]
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

### Removing a Dead Table: Full Checklist <!-- learned: 2026-04-27 -->

When removing an unused DB table, touch **all four locations** — missing any leaves dead schema or broken installs:

| File | Action |
|------|--------|
| `src/mysql/install/Install.sql` | Delete the `CREATE TABLE` block (new installs must not create it) |
| `cypress/data/seed.sql` | Delete the `DROP/CREATE TABLE` + data block (Cypress resets must not recreate it) |
| `orm/schema.xml` | Delete the `<table>` element (or its commented-out wrapper if already disabled) |
| `src/mysql/upgrade/X.Y.Z-cleanup.sql` | Add `DROP TABLE IF EXISTS` (existing installs need the cleanup on upgrade) |

Register the cleanup script in `upgrade.json` `current` block using the two-block pattern above. Use `DROP TABLE IF EXISTS` so the script is idempotent on fresh installs.
