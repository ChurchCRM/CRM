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
