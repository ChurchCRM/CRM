# Community Plugins Directory

Third-party community plugins are installed into this directory at
runtime. **Do not commit plugins to this directory** — it is a
runtime-managed folder, not a release artifact.

## Installing a plugin

Use the admin-only URL installer. Raw disk installs skip the safety
checks and are strongly discouraged.

```
POST /plugins/api/plugins/install
Content-Type: application/json
x-api-key: {admin key}

{ "downloadUrl": "https://example.org/releases/example-plugin-1.0.0.zip" }
```

The installer enforces:

1. The URL must be listed in `../approved-plugins.json`.
2. Your ChurchCRM version meets the plugin's `minimumCRMVersion`.
3. The download is HTTPS and ≤ 20 MB.
4. The zip's SHA-256 matches the approved registry entry.
5. The zip has no ZIP-Slip paths, no disallowed extensions, and exactly
   one top-level directory matching the plugin id.
6. Extracted `plugin.json` matches the registry entry.

After install you still have to click **Enable** in the admin UI — the
installer never auto-enables.

## Orphan scan

`src/plugins/community/` is excluded from `AppIntegrityService`'s
orphan-file scan (and from `scripts/generate-signatures-node.js`) so
community plugin files never show up as "orphans". This is guarded by
the Cypress regression test
`cypress/e2e/api/private/admin/private.admin.orphaned-files.plugins.spec.js`.

## Plugin localization

Community plugins do **not** go through POeditor. Ship PHP `.mo` files
under `{plugin}/locale/textdomain/{locale}/LC_MESSAGES/{plugin-id}.mo`
and JS `{plugin}/locale/i18n/{locale}.json` flat key/value maps. See
[`plugin-development.md → Plugin Localization`](../../../.agents/skills/churchcrm/plugin-development.md#plugin-localization-independent-of-poeditor).

## Before you install anything

Read these three skill files in order:

1. [`plugin-compliance.md`](../../../.agents/skills/churchcrm/plugin-compliance.md) — admin audit guide: how to read the approved list, check risk, and run monthly/quarterly scans.
2. [`plugin-security-scan.md`](../../../.agents/skills/churchcrm/plugin-security-scan.md) — the review checklist every approved plugin has passed before reaching the registry.
3. [`plugin-system.md`](../../../.agents/skills/churchcrm/plugin-system.md) — runtime architecture, so you know what a plugin is allowed to do on your server.
