---
title: "Core Plugin Migration Guidelines"
intent: "Checklist for updating the core plugins shipped in src/plugins/core/ when core ChurchCRM APIs, routes, or conventions change"
tags: ["plugins","migration","core","compatibility"]
prereqs: ["[[plugin-system]]"]
complexity: "intermediate"
---

# Core Plugin Migration Guidelines

Scope: plugins in `src/plugins/core/` (list with `ls src/plugins/core/`). Community plugins live in `src/plugins/community/` and follow [`plugin-create.md`](./plugin-create.md).

Run this checklist when a core change touches a contract core plugins depend on:

- `PluginInterface`, `AbstractPlugin`, or `Hooks` constants change
- a core route a plugin calls (`/api/...`, `/admin/api/...`) moves or changes its response
- a `SystemConfig` key a plugin reads is renamed or deleted
- middleware on a route group the plugin uses changes
- `plugin.json` gains a required field

## Per-plugin checklist

For every directory under `src/plugins/core/`:

- [ ] `plugin.json` carries every required manifest field.
- [ ] The plugin class matches the current `PluginInterface` signatures, including overridden `AbstractPlugin` methods.
- [ ] Hook constants match `Hooks.php` (grep each plugin for the changed name).
- [ ] URLs are `SystemURLs::getRootPath()`-relative.
- [ ] Renamed `SystemConfig` keys are read under the new name.
- [ ] `POST /plugins/api/plugins/{id}/test` succeeds.
- [ ] `help.json` labels match the UI.

## Plugin config in SQL

Core plugin settings are `plugin.{id}.*` rows in `config_cfg` (seeded in `src/mysql/upgrade/7.0.0.sql`). Change them only in the next numbered upgrade script:

- Add settings with `INSERT INTO config_cfg ... ON DUPLICATE KEY UPDATE ...`.
- Rename a key by copying the old value to the new key, then deleting the old row.
- Remove a key from `config_cfg` and from `plugin.json`'s `settings`.

## Routes file

When a route file moves, update `plugin.json`'s `routesFile` and the file's `use` imports. Plugin routes inherit `AuthMiddleware`, `ChurchInfoRequiredMiddleware` and `VersionMiddleware` from `src/plugins/index.php`. `PluginManager` is static: use `getPlugin()` and `isPluginActive()`.

## Integrity checks

1. `npm run build:signatures` regenerates `src/admin/data/signatures.json`.
2. Run `cypress/e2e/api/private/admin/private.admin.orphaned-files.plugins.spec.js`.
3. `npm run lint`.
4. Toggle the plugin off and on through the admin API.

## Release notes

Write the changelog entry in terms of what an admin sees in Admin → Plugins.

Related: [Plugin System](./plugin-system.md), [Plugin Development](./plugin-development.md), [Plugin Create](./plugin-create.md), [DB Schema Migration](./db-schema-migration.md).
