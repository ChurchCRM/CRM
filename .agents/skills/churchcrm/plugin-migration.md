---
title: "Plugin Migration Guidelines"
intent: "Checklist for plugin authors migrating to new ChurchCRM route, API, installer, or localization conventions"
tags: ["plugins","migration","compatibility","localization","security"]
prereqs: ["plugin-development.md","plugin-security-scan.md","api-compatibility-and-deprecation.md"]
complexity: "intermediate"
---

# Plugin Migration Guidelines

Checklist and patterns for plugin authors when core routes, APIs, or
conventions change. Run through every section on every core version bump.

---

## 1. Manifest & Routes

- Update `plugin.json` if new entry points, hooks, or settings are added.
- Avoid hardcoded route paths; read `SystemURLs::getRootPath()` and
  compose from there.
- When core provides a shim for a deprecated route, prefer calling the
  new official service method rather than keeping the old route alive.
- Plugin route files must return JSON from `/plugins/{id}/api/...` and
  HTML from `/plugins/{id}/...`. The core entry point only loads routes
  for active plugins, so you never need your own enable check.

---

## 2. Community Install Flow <!-- learned: 2026-04-13 -->

Plugins installed by end users now go through the URL-based installer.
If your plugin is community-distributed, check every item below before
publishing a new release:

- **Immutable release URL.** The `downloadUrl` in
  `src/plugins/approved-plugins.json` must point at a tagged release
  artifact with a reproducible SHA-256 — never a `main` tarball.
- **Single top-level directory.** The zip must contain exactly one
  top-level directory whose name equals your `id`. `PluginInstaller`
  rejects anything else.
- **No disallowed files.** Strip `.phar`, `.phtml`, `.sh`, `.exe`,
  `.so`, `.dll`, symlinks, `.git/`, and binary blobs you can't explain.
- **Manifest matches registry.** Your `plugin.json` `id`, `version`,
  and `type: "community"` must match the approved registry entry
  exactly — the installer cross-checks every field.
- **Risk level.** Every registry entry declares `risk` and `riskSummary`.
  If your release changes what the plugin does (new outbound calls,
  new hook subscriptions), re-run
  [`plugin-security-scan.md`](./plugin-security-scan.md) and bump the
  risk level if the rubric says so. **Re-reviews are required on every
  version bump** — a patch release still gets scanned fresh.

---

## 3. Localization Migration <!-- learned: 2026-04-13 -->

If your plugin shipped translations through the old POeditor-merged
workflow, move them into the plugin directory:

- Move `.mo` files to `{plugin}/locale/textdomain/{locale}/LC_MESSAGES/{pluginId}.mo`.
- Move `.json` i18next files to `{plugin}/locale/i18n/{locale}.json`
  as flat `key => string` maps (nested objects are dropped).
- Replace every `gettext('...')` / `_('...')` call in plugin PHP with
  `dgettext('{pluginId}', '...')` so strings resolve from your plugin
  textdomain, not the core `messages` domain.
- Replace every `i18next.t('key')` in plugin JS with either a direct
  lookup in `window.CRM.plugins['{pluginId}'].i18n[key]` or a scoped
  i18next namespace via `addResourceBundle`. See
  [`plugin-development.md → Plugin Localization`](./plugin-development.md#plugin-localization-independent-of-poeditor).
- Update your `xgettext` extraction command to use
  `--keyword=dgettext:2` so plugin strings end up in your own `.po`
  file.

**Do not** submit strings to the core `locale/messages.po` or
`locale/i18n/*.json` — community plugins never enter POeditor.

---

## 4. Compatibility

- Document every breaking change in your plugin's own release notes.
- Include a migration snippet in your plugin README showing the old
  call vs the new call.
- Run your plugin against a staging CRM with the new routes and with
  `PluginManager::reset()` + `init()` between installs so you are
  certain the new manifest is actually loaded.

---

## 5. Security Parity

- Plugin route middleware must enforce the same auth checks as the
  core route it replaces — if you migrated a route that required admin
  role, your plugin route still requires admin role.
- Plugin config access is sandboxed to `plugin.{id}.*` keys; never
  read or write other plugins' config.
- Re-run [`plugin-security-scan.md`](./plugin-security-scan.md) before
  updating the approved registry entry for a new version.

---

**Related skills:**

- [Plugin Development](./plugin-development.md) — full build guide
- [Plugin System](./plugin-system.md) — runtime architecture
- [Plugin Security Scan](./plugin-security-scan.md) — review checklist
- [Plugin Compliance (Admin)](./plugin-compliance.md) — admin-side audit
- [API Compatibility & Deprecation](./api-compatibility-and-deprecation.md)
