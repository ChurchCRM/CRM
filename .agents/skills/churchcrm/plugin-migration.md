---
title: "Core Plugin Migration Guidelines"
intent: "Checklist for updating the core plugins shipped in src/plugins/core/ when core ChurchCRM APIs, routes, or conventions change"
tags: ["plugins","migration","core","compatibility"]
prereqs: ["plugin-system.md","api-compatibility-and-deprecation.md"]
complexity: "intermediate"
---

# Core Plugin Migration Guidelines <!-- learned: 2026-04-13 -->

> **Scope — core plugins only.** This skill covers the plugins shipped
> in `src/plugins/core/` (mailchimp, vonage, gravatar, openlp,
> google-analytics, external-backup, custom-links) and any future
> plugin maintained in this repository. **Community plugins do not
> use this document** — they live at `src/plugins/community/`, are
> installed through the URL installer, and have their own create
> workflow in [`plugin-create.md`](./plugin-create.md).

Use this checklist whenever a core ChurchCRM change lands that
affects the contract core plugins depend on — a `PluginInterface`
method gains a parameter, a `Hooks::` constant is renamed, a
middleware moves, a `SystemConfig` key is deprecated, or a route file
is relocated.

---

## 1. Triggering events

Run through this skill when any of the following happens in the core
repository:

- `src/ChurchCRM/Plugin/PluginInterface.php` gains, renames, or drops
  a method.
- `src/ChurchCRM/Plugin/AbstractPlugin.php` changes default behaviour
  for a method a core plugin relies on.
- `src/ChurchCRM/Plugin/Hooks.php` adds, renames, or removes a hook
  constant that a core plugin listens on.
- A core route a plugin called (`/api/...`, `/admin/api/...`) moves or
  changes its response shape.
- A `SystemConfig` key a core plugin reads is renamed or deleted.
- A middleware (Auth, Admin, ChurchInfoRequired, Version, Cors) is
  added to or removed from a route group the plugin touches.
- The `plugin.json` schema gains a new required field.

---

## 2. Per-plugin checklist

For **every** directory under `src/plugins/core/`:

- [ ] Update `plugin.json` if any new manifest field is required.
- [ ] Update the plugin class to match the new `PluginInterface`
      signature. `AbstractPlugin` usually absorbs the change, but
      double-check methods the plugin overrides.
- [ ] Replace any removed or renamed hook constants with the new
      ones. Grep each plugin for the old constant name.
- [ ] Replace hardcoded route paths with
      `SystemURLs::getRootPath()`-relative URLs.
- [ ] If the plugin reads a renamed `SystemConfig` key, read the new
      one and leave a one-line comment pointing at the migration SQL
      file.
- [ ] Rerun the core plugin's test button via
      `POST /plugins/api/plugins/{id}/test` to confirm the plugin
      still boots after the change.
- [ ] Update `help.json` if UI labels moved, so admins get accurate
      inline documentation.

---

## 3. Schema migrations for plugin config

Core plugins store their config in the `config_cfg` table with
`plugin.{id}.*` keys (seeded in `src/mysql/upgrade/7.0.0.sql`,
lines 12–46). When a core plugin gains, renames, or drops a setting:

- Add a new `INSERT INTO config_cfg ... ON DUPLICATE KEY UPDATE ...`
  block in the next numbered upgrade script.
- If you are **renaming** a key, copy the old value into the new key
  first and **only then** delete the old row, so existing installs do
  not lose their configuration.
- If you are **removing** a key, also remove it from
  `plugin.json`'s `settings` array.
- Do not try to do this rename from PHP at boot — upgrade SQL is the
  only supported path.

Mirror the `7.0.0.sql` migration pattern: seed defaults first, copy
legacy keys into the new names, then delete the legacy rows.

---

## 4. Routes-file migration

If a core plugin's route file moves (for example, out of `src/` or
into a different Slim group), also update:

- `plugin.json`'s `routesFile` field.
- Any `use` imports inside the route file — core services may have
  new namespaces.
- The middleware stack: plugin routes inherit
  `AuthMiddleware + ChurchInfoRequiredMiddleware + VersionMiddleware`
  from `src/plugins/index.php`. Do **not** add the stack again inside
  the plugin route file.
- Any references to `PluginManager::getInstance()` — it does not
  exist. Use the static methods (`getPlugin`, `isPluginActive`).

---

## 5. Integrity checks

After any core plugin migration:

1. Run `npm run build:php` to regenerate `src/admin/data/signatures.json`
   and confirm the renamed or moved files show up.
2. Run the Cypress test that guards the orphan-scan community
   exclusion so you know the generator didn't accidentally pull in
   `plugins/community/`:
   `cypress/e2e/api/private/admin/private.admin.orphaned-files.plugins.spec.js`.
3. Run `npm run lint` for Biome lint parity with the pre-push hook.
4. Toggle the plugin off and on via the admin API and confirm the
   `enablePlugin` / `disablePlugin` round-trip still works.

---

## 6. Release notes

Core plugin migrations that affect user-visible settings must land
in the changelog for the next release. Write the changelog entry in
terms of what an admin sees in **Admin → Plugins**, not in terms of
internal method names.

---

**Related skills:**

- [Plugin System](./plugin-system.md) — runtime architecture
- [Plugin Development](./plugin-development.md) — building a plugin
- [Plugin Create (Community)](./plugin-create.md) — the community
  plugin quickstart and submission flow
- [API Compatibility & Deprecation](./api-compatibility-and-deprecation.md)
- [DB Schema Migration](./db-schema-migration.md) — upgrade SQL patterns
